<?php
/**
 * @package     VladFlonta\WebApiLog
 * @author      Vlad Flonta
 * @copyright   Copyright © 2022
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace VladFlonta\WebApiLog\Model\Service;

class Resolver
{
    /** @var array */
    protected $processedRequests = [];

    /** @var \Magento\Framework\App\RequestInterface */
    protected $request;

    /** @var \VladFlonta\WebApiLog\Model\Config */
    protected $config;

    /**
     * Services constructor.
     * @param \VladFlonta\WebApiLog\Model\Config $config
     * @param \Magento\Framework\App\RequestInterface $request
     */
    public function __construct(
        \VladFlonta\WebApiLog\Model\Config $config,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * @return boolean
     */
    public function isExcluded()
    {
        if (!isset($this->request)) {
            return true;
        }
        if (isset($this->processedRequests[$this->request->getPathInfo()])) {
            return $this->processedRequests[$this->request->getPathInfo()];
        }
        $requestPaths = explode('/', trim($this->request->getPathInfo(), '/'));
        foreach ($this->config->getExcludeServices() as $excludeService) {
            list($routeParts, $variables) = $this->getRoutePartsAndVariables($excludeService);
            if (count($requestPaths) !== count($routeParts)) {
                continue;
            }
            $matches = true;
            foreach ($requestPaths as $key => $value) {
                if (!array_key_exists($key, $routeParts)) {
                    $matches = false;
                    break;
                }
                $variable = isset($variables[$key]) ? $variables[$key] : null;
                if (!$variable && $value != $routeParts[$key]) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $this->processedRequests[$this->request->getPathInfo()] = true;
            }
        }

        return $this->processedRequests[$this->request->getPathInfo()] = false;
    }

    /**
     * Split route by parts and variables
     * @param string $route
     * @return array
     */
    protected function getRoutePartsAndVariables($route)
    {
        $result = [];
        $variables = [];
        $routeParts = explode('/', $route);
        foreach ($routeParts as $key => $value) {
            if (substr($value, 0, 1) == ':'
                && substr($value, 1, 1) != ':') {
                $variables[$key] = substr($value, 1);
                $value = null;
            }
            $result[$key] = $value;
        }
        return [$result, $variables];
    }
}
