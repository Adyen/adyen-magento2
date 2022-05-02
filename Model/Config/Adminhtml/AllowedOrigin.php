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
use Magento\Framework\Data\Form\Element\AbstractElement;

class AllowedOrigin extends Field
{
    protected $_template = 'Adyen_Payment::config/allowed_origin.phtml';

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->toHtml() . parent::_getElementHtml($element);
    }

    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'adyen_configure_allowed_origin',
                'label' => __('Configure Allowed Origin'),
            ]
        );

        return $button->toHtml();
    }

    public function getAjaxUrl()
    {
        return $this->getUrl('adyen/configuration/allowedorigin');
    }

    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }
}
