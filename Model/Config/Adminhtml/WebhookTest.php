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

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManager;
use Adyen\Payment\Helper\Config;

class WebhookTest extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_template = 'Adyen_Payment::config/webhook_test.phtml';

    /**
     * @var StoreManager
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $configHelper;

    public function __construct(
        Context $context,
        Config $configHelper,
        StoreManager $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
    }

    public function render(AbstractElement $element)
    {
        // Remove scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl()
    {
        return $this->getUrl('adyen/configuration/webhookTest');
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'adyen_webhook_test',
                'label' => __('Test Webhook'),
            ]
        );

        return $button->toHtml();
    }

    public function isWebhookIdConfigured(): bool
    {
        $storeId = $this->storeManager->getStore()->getId();

        return boolval($this->configHelper->getWebhookId($storeId));
    }
}
