<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Adminhtml;

use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Helper\Js;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class GettingStarted extends Fieldset
{

    private UrlInterface $urlBuilder;

    public function __construct(
        Context             $context,
        Session             $authSession,
        Js                  $jsHelper,
        UrlInterface        $urlBuilder,
        array               $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    )
    {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $authSession, $jsHelper, $data, $secureRenderer);
    }

    protected function _getHeaderCommentHtml($element): string
    {
        $supportFormUrl = $this->urlBuilder->getUrl('adyen/support/configurationsettings');
        $text = '
        <ul class="adyen-list">
            <li>Adyen Adobe Commerce plugin integration demo in GitHub <a href="https://github.com/adyen-examples/adyen-magento-plugin-demo" target="_blank">integration demo in GitHub</a></li>
            <li><a target="_blank" href="https://docs.adyen.com/plugins/adobe-commerce">Documentation for setting up the module</a></li>
            <li>Adyen Customer Area for <a target="_blank" href="https://ca-test.adyen.com">TEST</a> and Adyen Customer Area for <a target="_blank" href="https://ca-live.adyen.com">LIVE</a></li>
            <li>For questions, reach out to our support team by filling out the <a target="_parent" href="' . $supportFormUrl . '">support form</a></li>
        </ul>
        ';
        $element->setData("comment", $text);
        return parent::_getHeaderCommentHtml($element);
    }
}