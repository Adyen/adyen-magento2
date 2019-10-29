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
namespace Adyen\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class DataAssignObserver
 */
class AdyenCcDataAssignObserver extends AbstractDataAssignObserver
{
    const CC_TYPE = 'cc_type';
    const NUMBER_OF_INSTALLMENTS = 'number_of_installments';
    const STORE_CC = 'store_cc';
    const ENCRYPTED_CREDIT_CARD_NUMBER = 'number';
    const ENCRYPTED_SECURITY_CODE = 'cvc';
    const ENCRYPTED_EXPIRY_MONTH = 'expiryMonth';
    const ENCRYPTED_EXPIRY_YEAR = 'expiryYear';
    const HOLDER_NAME = 'holderName';
    const VARIANT = 'variant';
    const JAVA_ENABLED = 'java_enabled';
    const SCREEN_COLOR_DEPTH = 'screen_color_depth';
    const SCREEN_WIDTH = 'screen_width';
    const SCREEN_HEIGHT = 'screen_height';
    const TIMEZONE_OFFSET = 'timezone_offset';
    const LANGUAGE = 'language';
    const GUEST_EMAIL = 'guestEmail';

    /**
     * @var array
     */
    protected $additionalInformationList = [
        self::CC_TYPE,
        self::NUMBER_OF_INSTALLMENTS,
        self::STORE_CC,
        self::ENCRYPTED_CREDIT_CARD_NUMBER,
        self::ENCRYPTED_SECURITY_CODE,
        self::ENCRYPTED_EXPIRY_MONTH,
        self::ENCRYPTED_EXPIRY_YEAR,
        self::HOLDER_NAME,
        self::VARIANT,
        self::JAVA_ENABLED,
        self::SCREEN_COLOR_DEPTH,
        self::SCREEN_WIDTH,
        self::SCREEN_HEIGHT,
        self::TIMEZONE_OFFSET,
        self::LANGUAGE,
        self::GUEST_EMAIL
    ];

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        // set ccType
        if (!empty($additionalData['cc_type'])) {
            $paymentInfo->setCcType($additionalData['cc_type']);
        }

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
