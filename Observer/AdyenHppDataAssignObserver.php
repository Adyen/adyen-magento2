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
class AdyenHppDataAssignObserver extends AbstractDataAssignObserver
{
    const BRAND_CODE = 'brand_code';
    const ISSUER_ID = 'issuer_id';
    const GENDER = 'gender';
    const DOB = 'dob';
    const TELEPHONE = 'telephone';
    const DF_VALUE = 'df_value';
    const SSN = 'ssn';
    const OWNER_NAME = 'ownerName';
    const BANK_ACCOUNT_OWNER_NAME = 'bankAccountOwnerName';
    const IBAN_NUMBER = 'ibanNumber';
    const BANK_ACCOUNT_NUMBER = 'bankAccountNumber';
    const BANK_LOCATIONID = 'bankLocationId';


    /**
     * @var array
     */
    protected $additionalInformationList = [
        self::BRAND_CODE,
        self::ISSUER_ID,
        self::GENDER,
        self::DOB,
        self::TELEPHONE,
        self::DF_VALUE,
        self::SSN,
        self::OWNER_NAME,
        self::BANK_ACCOUNT_OWNER_NAME,
        self::IBAN_NUMBER,
        self::BANK_ACCOUNT_NUMBER,
        self::BANK_LOCATIONID
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

        if (isset($additionalData[self::BRAND_CODE])) {
            $paymentInfo->setCcType($additionalData[self::BRAND_CODE]);
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
