<?php
/**
 * @package     VladFlonta\WebApiLog
 * @author      Vlad Flonta
 * @copyright   Copyright © 2022
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace VladFlonta\WebApiLog\Model;

class Config
{
    const XML_PATH_WEBAPI_LOGGER = 'webapi/logger/';

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $scopeConfig;

    /**
     * Config constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array
     */
    public function getExcludeServices()
    {
        return array_filter(explode(
            ',',
            $this->scopeConfig->getValue(self::XML_PATH_WEBAPI_LOGGER.'exclude_services', 'store') ?? ''
        ));
    }

    /**
     * @return string
     */
    public function getSavePath()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_WEBAPI_LOGGER.'save_path', 'store');
    }

    /**
     * @return boolean
     */
    public function getEnable()
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_WEBAPI_LOGGER.'enable', 'store');
    }

    /**
     * @return boolean
     */
    public function isIntegrationNameEnabled()
    {
        return (bool)$this->scopeConfig->getValue(self::XML_PATH_WEBAPI_LOGGER.'enable_integration_name', 'store');
    }
}
