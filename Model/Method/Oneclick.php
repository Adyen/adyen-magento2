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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Method;

/**
 * Adyen CreditCard payment method
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Oneclick extends \Adyen\Payment\Model\Method\Cc
{
    const METHOD_CODE = 'adyen_oneclick';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = 'Adyen\Payment\Block\Form\Oneclick';
    protected $_infoBlockType = 'Adyen\Payment\Block\Info\Oneclick';

//    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
//      return true;
//    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $infoInstance = $this->getInfoInstance();


        // save value remember details checkbox
        $infoInstance->setAdditionalInformation('recurring_detail_reference', $data['recurring_detail_reference']);

        $recurringPaymentType = $this->_adyenHelper->getAdyenOneclickConfigData('recurring_payment_type');
        if($recurringPaymentType == \Adyen\Payment\Model\RecurringType::ONECLICK) {
            $customerInteraction = true;
        } else {
            $customerInteraction = false;
        }

        $infoInstance->setAdditionalInformation('customer_interaction', $customerInteraction);

        return $this;
    }

}