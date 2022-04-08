<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Adminhtml;

use Adyen\Payment\Helper\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class WebhookCredentials extends Field
{
    /**
     * @var Config
     */
    private $configHelper;

    public function __construct(
        Config $configHelper,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
        $this->configHelper = $configHelper;
    }

    public function render(AbstractElement $element)
    {
        $webhookId = $this->configHelper->getWebhookId();
        $tooltip = $element->getData('tooltip');
        $tooltip .= empty($webhookId)
            ? "If changed, new webhook will be created in Adyen Customer Area. 
                Navigate to Developers => Webhooks to disable the old webhook."
            : "If changed, your webhook will be updated in Adyen Customer Area.";
        $element->setData('tooltip', $tooltip);

        return parent::render($element);
    }
}