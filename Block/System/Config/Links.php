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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\System\Config;

class Links extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'Adyen_Payment::system/config/adyen_header_links.phtml';

    /**
     * Links to show below the Adyen payment method header
     *
     * @var array
     */
    protected $links = array(
        [
            "label" => "Docs",
            "url" => "https://docs.adyen.com/developers/plug-ins-and-partners/magento-2"
        ],
        [
            "label" => "FAQs",
            "url" => "https://support.adyen.com/hc/en-us/sections/360000809984-Plugins"
        ],
        [
            "label" => "Support",
            "url" => "https://support.adyen.com/hc/en-us/requests/new?ticket_form_id=78764"
        ],
        [
            "label" => "GitHub",
            "url" => "https://github.com/Adyen/adyen-magento2/releases"
        ],
        [
            "label" => "Magento Marketplace",
            "url" => "https://marketplace.magento.com/adyen-module-payment.html"
        ]
    );

    /**
     * Render fieldset html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->_decorateRowHtml($element, "<td colspan='4'>" . $this->toHtml() . '</td>');
    }

    /**
     * Outputs information links in format
     *
     * @return string
     */
    public function outputLinks()
    {
        foreach ($this->links as $link) {
            $anchorTags[] = '<a href="' . $link["url"] . '"  target="_blank">' . $link["label"] . '</a>';
        }

        return '<div class="adyen-header-links">' . implode(' | ', $anchorTags) . '</div>';
    }

}
