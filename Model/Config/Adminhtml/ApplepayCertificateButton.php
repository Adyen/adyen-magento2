<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Adminhtml;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Helper\Data;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ApplepayCertificateButton extends Field
{
    const APPLEPAY_BUTTON = 'Adyen_Payment::config/applepay_button.phtml';

    /**
     * @var Data
     */
    protected $backendHelper;

    /**
     * @param Context $context
     * @param Data $backendHelper
     * @param array $data
     */
     public function __construct(
        Context $context,
        Data $backendHelper,
        array $data = []
    ) {
        $this->backendHelper = $backendHelper;
        parent::__construct($context, $data);
    }

    /**
     * @return $this|ApplepayCertificateButton
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        if (!$this->getTemplate()) {
            $this->setTemplate(static::APPLEPAY_BUTTON);
        }

        return $this;
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->addData([
            'id' => 'addbutton_button',
            'button_label' => _(''),
            'onclick' => 'javascript:check(); return false;'
        ]);

        return $this->toHtml();
    }

    /**
     * @return string
     *
    public function getActionUrl(): string
    {
            return $this->backendHelper->getUrl("adyen/configuration/DownloadApplePayCertificate");
    }
}
