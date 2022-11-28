<?php
/**
 * @package     VladFlonta\WebApiLog
 * @author      Vlad Flonta
 * @copyright   Copyright © 2022
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace VladFlonta\WebApiLog\Model\Config\Source;

class Services implements \Magento\Framework\Option\ArrayInterface
{
    /** @var \Magento\Webapi\Model\Config */
    protected $config;

    /** @var array */
    protected $options;

    /**
     * Services constructor.
     * @param \Magento\Webapi\Model\Config $config
     */
    public function __construct(
        \Magento\Webapi\Model\Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Get options in "value-label" format
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getOptionsArray($this->getOptions());
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getOptions();
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        if (!isset($this->options)) {
            $this->options = [];
            $this->options[(string)__('-- None --')] = '';
            $options = $this->config->getServices()[\Magento\Webapi\Model\Config\Converter::KEY_ROUTES];
            foreach ($options as $route => $methods) {
                $label = trim(ucwords(preg_replace('/^\/([^\/]+)\/([^\/]+).*/', '$1 $2', $route)));
                $this->options[$label][$route] = trim($route, '/');
            }
        }

        return $this->options;
    }

    /**
     * @param array $options
     * @return array
     */
    protected function getOptionsArray(array $options)
    {
        $optionArray = [];
        foreach ($options as $label => $value) {
            $optionArray[] = [
                'value' => is_array($value) ? $this->getOptionsArray($value) : $value,
                'label' => $label,
            ];
        }
        return $optionArray;
    }
}
