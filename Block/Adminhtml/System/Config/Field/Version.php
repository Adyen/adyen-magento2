<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

/**
 * Field renderer for Adyen module version
 */

namespace Adyen\Payment\Block\Adminhtml\System\Config\Field;

use Adyen\Payment\Helper\PlatformInfo;
use Magento\Backend\Block\Template\Context;

class Version extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * Version constructor.
     *
     * @param PlatformInfo $platformInfo
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        PlatformInfo $platformInfo,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_platformInfo = $platformInfo;
    }

    /**
     * Retrieve the setup version of the extension
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->_platformInfo->getModuleVersion();
    }
}
