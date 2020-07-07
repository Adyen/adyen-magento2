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

use Adyen\Payment\Observer\Adminhtml\AdyenSateDataValidator;
use Magento\Framework\Event\Observer;

class AdyenHppDataAssignObserver extends AdyenAbstractDataAssignObserver
{
    /**
     * @var \Adyen\Service\Validator\CheckoutStateDataValidator
     */
    public $checkoutStateDataValidator;

    public $adyenHelper;

    public function __construct(
        \Adyen\Service\Validator\CheckoutStateDataValidator $checkoutStateDataValidator

    ) {
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->adyenHelper =$adyenHelper;
    }

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
        if (!empty($additionalData[self::STATE_DATA])) {
            $additionalData[self::STATE_DATA] = $this->checkoutStateDataValidator->getValidatedAdditionalData(
                $additionalData[self::STATE_DATA]
            );
        }
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
