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
use Magento\Quote\Api\Data\PaymentInterface;

class AdyenHppDataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * Approved root level keys from additional data array
     *
     * @var array
     */
    protected $approvedAdditionalDataKeys = [
        self::STATE_DATA,
        self::BRAND_CODE
    ];

    /**
     * Approved root level keys from the checkout component's state data object
     *
     * @var array
     */
    protected $approvedStateDataKeys = [
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

        $additionalData = $this->getValidatedAdditionalData($data);

        // Set additional data in the payment
        $paymentInfo = $this->readPaymentModelArgument($observer);
        foreach ($additionalData as $key => $data) {
            $paymentInfo->setAdditionalInformation($key, $data);
        }

        // Set BrandCode into CCType
        if (isset($additionalData[self::BRAND_CODE])) {
            $paymentInfo->setCcType($additionalData[self::BRAND_CODE]);
        }
    }
}
