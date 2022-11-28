<?php
/**
 * @package     VladFlonta\WebApiLog
 * @author      Vlad Flonta
 * @copyright   Copyright © 2018
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace VladFlonta\WebApiLog\Plugin\Rest;

class Api
{
    /** @var \VladFlonta\WebApiLog\Logger\Handler */
    protected $apiLogger;

    /** @var \VladFlonta\WebApiLog\Model\Config */
    protected $config;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /** @var array */
    protected $currentRequest;

    /** @var \Magento\Framework\App\RequestInterface */
    protected $request;

    /** @var \VladFlonta\WebApiLog\Model\Service\Resolver */
    protected $serviceResolver;

    /**
     * Rest constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \VladFlonta\WebApiLog\Model\Config $config
     * @param \VladFlonta\WebApiLog\Logger\Handler $apiLogger
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \VladFlonta\WebApiLog\Model\Service\Resolver $serviceResolver
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \VladFlonta\WebApiLog\Model\Config $config,
        \VladFlonta\WebApiLog\Logger\Handler $apiLogger,
        \Magento\Framework\App\RequestInterface $request,
        \VladFlonta\WebApiLog\Model\Service\Resolver $serviceResolver
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->request = $request;
        $this->apiLogger = $apiLogger;
        $this->serviceResolver = $serviceResolver;
    }

    /**
     * @param \Magento\Webapi\Controller\Rest $subject
     * @param callable $proceed
     * @param \Magento\Framework\App\RequestInterface $request
     * @return mixed
     */
    public function aroundDispatch(
        \Magento\Webapi\Controller\Rest $subject,
        callable $proceed,
        \Magento\Framework\App\RequestInterface $request
    ) {
        if (!$this->config->getEnable() || $this->serviceResolver->isExcluded()) {
            return $proceed($request);
        }
        try {
            $this->currentRequest = [
                'is_api' => true,
                'is_auth' => $this->isAuthorizationRequest($request->getPathInfo()),
                'request' => [
                    'method' => $request->getMethod(),
                    'uri' => $request->getRequestUri(),
                    'version' => $request->getVersion(),
                    'headers' => [],
                    'body' => '',
                ],
                'response' => [
                    'headers' => [],
                    'body' => '',
                ],
                'start' => microtime(true),
                'uid' => uniqid(),
            ];
            $currentRequest = &$this->currentRequest['request'];
            foreach ($request->getHeaders()->toArray() as $key => $value) {
                switch($key) {
                    case "Authorization":
                        preg_match('/^(?<type>\S+)\s(?<data>\S+)/', $value, $info);
                        if (count($info) !== 5) {
                            $currentRequest['headers'][$key] = 'SHA256:'.hash('sha256', $value);
                        } else {
                            $currentRequest['headers'][$key] = $info['type'].' SHA256:'.hash('sha256', $info['data']);
                        }
                        break;
                    default:
                        $currentRequest['headers'][$key] = $value;
                }
            }
            $currentRequest['body'] = $this->currentRequest['is_auth'] ?
                'Request body is not available for authorization requests.' :
                $request->getContent();
        } catch (\Exception $exception) {
            $this->logger->debug(sprintf(
                'Exception when logging API request: %s (%s::%s)',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        }

        return $proceed($request);
    }

    /**
     * @param \Magento\Framework\Webapi\Rest\Response $subject
     * @param $result
     * @return mixed
     */
    public function afterSendResponse(
        \Magento\Framework\Webapi\Rest\Response $subject,
        $result
    ) {
        if (!$this->config->getEnable() || $this->serviceResolver->isExcluded()) {
            return $result;
        }
        try {
            $this->currentRequest['response']['is_exception'] = $subject->isException();
            foreach ($subject->getHeaders()->toArray() as $key => $value) {
                $this->currentRequest['response']['headers'][$key] = $value;
            }
            $this->currentRequest['response']['body'] = $this->currentRequest['is_auth'] ?
                'Response body is not available for authorization requests.' :
                $subject->getBody();
            $this->currentRequest['end'] = microtime(true);
            $this->currentRequest['time'] = $this->currentRequest['end'] - $this->currentRequest['start'];
            $this->apiLogger->debug('', $this->currentRequest);
        } catch (\Exception $exception) {
            $this->logger->debug('Exception when logging API response: ' . $exception->getMessage());
        }

        return $result;
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function isAuthorizationRequest($path)
    {
        return preg_match('/integration\/(admin|customer)\/token/', $path) !== 0;
    }
}
