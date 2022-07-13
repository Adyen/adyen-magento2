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

namespace Adyen\Payment\Block\Info;

class Moto extends AbstractInfo
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::info/adyen_moto.phtml';

    /**
     * Return credit card type
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCcTypeName()
    {
        $types = $this->_adyenHelper->getAdyenCcTypes();
        $ccType = $this->getInfo()->getCcType();

        if (isset($types[$ccType])) {
            return $types[$ccType]['name'];
        }
        // TODO::Refactor this block after tokenization of the alternative payment methods.
        // This elseif block should be removed after the tokenization of the alternative payment methods (In progress: PW-6764). More general approach is required.
        // Also remove `sepadirectdebit` from translation files.
        elseif ($ccType == 'sepadirectdebit') {
            return __('sepadirectdebit');
        }
        else {
            return __('Unknown');
        }
    }
}
