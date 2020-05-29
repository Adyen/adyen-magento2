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
    use AdyenObserverTrait;

    // TODO do we need these?
    const DF_VALUE = 'df_value';

    const BRAND_CODE = 'brand_code';
    const STATE_DATA = 'state_data';
    const BROWSER_INFO = 'browserInfo';
    const PAYMENT_METHOD = 'paymentMethod';
    const RISK_DATA = 'riskData';

    /**
     * Approved root level keys from additional data array
     *
     * @var array
     */
    private static $approvedAdditionalDataKeys = [
        self::STATE_DATA,
        self::BRAND_CODE
    ];

    /**
     * Approved root level keys from the checkout component's state data object
     *
     * @var array
     */
    private static $approvedStateDataKeys = [
        self::BROWSER_INFO,
        self::PAYMENT_METHOD,
        self::RISK_DATA
    ];

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        // Get request fields
        $data = $this->readDataArgument($observer);

        // Get additional data array
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        // Get a validated additional data array
        $additionalData = $this->getArrayOnlyWithApprovedKeys($additionalData, self::$approvedAdditionalDataKeys);

        // json decode state data
        $stateData = [];
        if (!empty($additionalData[self::STATE_DATA])) {
            $stateData = json_decode($additionalData[self::STATE_DATA], true);
        }

        // Get validated state data array
        if (!empty($stateData)) {
            $stateData = $this->getArrayOnlyWithApprovedKeys($stateData, self::$approvedStateDataKeys);
        }

        // Replace state data with the decoded and validated state data
        $additionalData[self::STATE_DATA] = $stateData;

        // Set additional data in the payment
        $paymentInfo = $this->readPaymentModelArgument($observer);
        foreach ($additionalData as $key => $data) {
            $paymentInfo->setAdditionalInformation($key, $data);
        }

        // Is this the correct way of setting it???
        // Set BrandCode into CCType
        if (isset($additionalData[self::BRAND_CODE])) {
            $paymentInfo->setCcType($additionalData[self::BRAND_CODE]);
        }
    }
}
