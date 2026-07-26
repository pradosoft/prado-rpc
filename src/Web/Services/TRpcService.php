<?php

/**
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @link https://github.com/pradosoft/prado-rpc
 * @license https://github.com/pradosoft/prado/blob/master/LICENSE
 * @since 3.2.0
 */

namespace Prado\Web\Services;

use Prado\Exceptions\TConfigurationException;
use Prado\Exceptions\THttpException;
use Prado\Prado;
use Prado\Web\THttpHeaderName;
use Prado\Web\TMediaType;
use Prado\Xml\TXmlElement;

/**
 * TRpcService class
 *
 * TRpcService routes incoming HTTP POST requests to the appropriate RPC provider.
 *
 * **Server** — a {@see \Prado\TModule} subclassing {@see \Prado\Web\Services\TRpcServer} that moves
 * data between the service and the provider. The base class suits most needs; subclass
 * it for logging, filtering, or request/response transformation.
 *
 * **Protocol** — a {@see \Prado\TModule} subclassing {@see \Prado\Web\Services\TRpcProtocol} that
 * implements the wire format. The supported protocols are:
 * - {@see \Prado\Web\Services\TJsonRpcProtocol} — JSON-RPC 1.0 and 2.0 (`application/json`)
 * - {@see \Prado\Web\Services\TXmlRpcProtocol} — XML-RPC (`text/xml`)
 *
 * **Provider** — a subclass of {@see \Prado\Web\Services\TRpcApiProvider} that declares the
 * available methods via {@see \Prado\Web\Services\TRpcApiProvider::registerMethods()}.
 *
 * Request flow: `TRpcService → TRpcServer → TRpcProtocol → TRpcApiProvider`
 *
 * XML configuration:
 * ```xml
 * <service id="rpc" class="Prado\Web\Services\TRpcService">
 *     <rpcapi id="customers" class="Application.Api.CustomersApi" />
 * </service>
 * ```
 *
 * PHP configuration:
 * ```php
 * return [
 *     'services' => [
 *         'rpc' => [
 *             'class' => 'Prado\Web\Services\TRpcService',
 *             'apis' => [
 *                 'customers' => ['class' => 'Application\Api\CustomersApi'],
 *             ],
 *         ],
 *     ],
 * ];
 * ```
 *
 * Each `<rpcapi>` element requires an `id` attribute and a `class` in dot-notation or
 * namespace format. On match, the class is instantiated to handle the request.
 *
 * @author Robin J. Rogge <rrogge@bigpoint.net>
 * @since 3.2.0
 **/
class TRpcService extends \Prado\TService
{
	/**
	 * const string base api provider class which every API must extend
	 */
	public const BASE_API_PROVIDER = \Prado\Web\Services\TRpcApiProvider::class;

	/**
	 * const string base RPC server implementation
	 */
	public const BASE_RPC_SERVER = \Prado\Web\Services\TRpcServer::class;

	/**
	 * @var array containing mimetype to protocol handler mappings
	 */
	protected $protocolHandlers = [
		TMediaType::JSON => TJsonRpcProtocol::class,
		TMediaType::XML_TEXT => TXmlRpcProtocol::class,
	];

	/**
	 * @var array containing API provider and their configured properties
	 */
	protected $apiProviders = [];

	// methods

	/**
	 * Creates the API provider instance for the current request
	 * @param TRpcProtocol $protocolHandler instance
	 * @param string $providerId
	 */
	public function createApiProvider(TRpcProtocol $protocolHandler, $providerId)
	{
		$_properties = clone $this->apiProviders[$providerId];

		if (($_providerClass = $_properties->remove('class')) === null) {
			throw new TConfigurationException('rpcservice_apiprovider_required');
		}

		$_providerClassName = Prado::usingClass($_providerClass);
		if (!is_string($_providerClassName) || !is_subclass_of($_providerClassName, self::BASE_API_PROVIDER)) {
			throw new TConfigurationException('rpcservice_apiprovider_invalid', $_providerClass);
		}

		if (($_rpcServerClass = $_properties->remove('server')) === null) {
			$_rpcServerClass = self::BASE_RPC_SERVER;
		}

		$_rpcServerClassName = Prado::usingClass($_rpcServerClass);
		if (!is_string($_rpcServerClassName) || ($_rpcServerClassName !== self::BASE_RPC_SERVER && !is_subclass_of($_rpcServerClassName, self::BASE_RPC_SERVER))) {
			throw new TConfigurationException('rpcservice_rpcserver_invalid', $_rpcServerClass);
		}

		$_apiProvider = Prado::createComponent($_providerClassName, Prado::createComponent($_rpcServerClassName, $protocolHandler));
		$_apiProvider->setId($providerId);

		foreach ($_properties as $_key => $_value) {
			$_apiProvider->setSubProperty($_key, $_value);
		}

		return $_apiProvider;
	}

	/**
	 * Initializes the service
	 * @param null|array|\Prado\Xml\TXmlElement $config containing the module configuration
	 */
	public function init($config)
	{
		$this->loadConfig($config);
		parent::init($config);
	}

	/**
	 * Loads the service configuration.
	 * @param mixed $config XML element or null/array for PHP-style configuration
	 */
	public function loadConfig($config)
	{
		if (!$config instanceof TXmlElement) {
			return;
		}
		foreach ($config->getElementsByTagName('rpcapi') as $_apiProviderXml) {
			$_properties = $_apiProviderXml->getAttributes();

			if (($_id = $_properties->remove('id')) === null || $_id == "") {
				throw new TConfigurationException('rpcservice_apiproviderid_required');
			}

			if (isset($this->apiProviders[$_id])) {
				throw new TConfigurationException('rpcservice_apiproviderid_duplicated');
			}

			$this->apiProviders[$_id] = $_properties;
		}
	}

	/**
	 * Runs the service.
	 * @throws THttpException when the provider id is missing, unknown, the HTTP method is not POST, or the content-type is unsupported.
	 */
	public function run()
	{
		$_request = $this->getRequest();

		if (($_providerId = $_request->getServiceParameter()) == "") {
			throw new THttpException(400, 'rpcservice_apiproviderid_missing');
		}

		if (!isset($this->apiProviders[$_providerId])) {
			throw new THttpException(404, 'rpcservice_apiprovider_unknown', $_providerId);
		}

		if (($_method = $_request->getRequestType()) != 'POST') {
			$_response = $this->getResponse();
			$_response->appendHeader(THttpHeaderName::Allow, 'POST');
			throw new THttpException(405, 'rpcservice_method_not_post', $_method);
		}

		if (($_mimeType = $_request->getContentType()) === null) {
			throw new THttpException(415, 'rpcservice_content_type_required');
		}

		if (!isset($this->protocolHandlers[$_mimeType])) {
			throw new THttpException(415, 'rpcservice_content_type_unsupported', $_mimeType);
		}

		$_protocolHandlerClass = $this->protocolHandlers[$_mimeType];
		$_protocolHandler = Prado::createComponent($_protocolHandlerClass);

		$_result = $this->createApiProvider($_protocolHandler, $_providerId)->processRequest();
		if ($_result !== null && $_result !== '') {
			$_response = $this->getResponse();
			$_protocolHandler->createResponseHeaders($_response);
			$_response->write($_result);
		}
	}
}
