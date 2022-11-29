<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Adyen\Service\Validator\CheckoutStateDataValidator;
use Adyen\Service\Validator\DataArrayValidator;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Store\Model\StoreManagerInterface;

class AdyenHppDataAssignObserver extends AbstractDataAssignObserver
{
    final const BRAND_CODE = 'brand_code';
    final const DF_VALUE = 'df_value';
    final const GUEST_EMAIL = 'guestEmail';
    final const STATE_DATA = 'stateData';
    final const RETURN_URL = 'returnUrl';

    /**
     * Approved root level keys from additional data array
     *
     * @var array
     */
    private static $approvedAdditionalDataKeys = [
        self::BRAND_CODE,
        self::DF_VALUE,
        self::GUEST_EMAIL,
        self::STATE_DATA,
        self::RETURN_URL,
    ];

    /**
     * @var CheckoutStateDataValidator
     */
    protected $checkoutStateDataValidator;

    /**
     * @var Collection
     */
    protected $stateDataCollection;
    /**
     * @var StateData
     */
    private $stateData;

    /** @var StoreManagerInterface */
    private $storeManager;

    /**
     * AdyenHppDataAssignObserver constructor.
     *
     * @param CheckoutStateDataValidator $checkoutStateDataValidator
     * @param Collection $stateDataCollection
     * @param StateData $stateData
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CheckoutStateDataValidator $checkoutStateDataValidator,
        Collection $stateDataCollection,
        StateData $stateData,
        StoreManagerInterface $storeManager
    ) {
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->stateDataCollection = $stateDataCollection;
        $this->stateData = $stateData;
        $this->storeManager = $storeManager;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $additionalDataToSave = [];
        // Get request fields
        $data = $this->readDataArgument($observer);
        $paymentInfo = $this->readPaymentModelArgument($observer);

        // Remove remaining brand_code information from the previous payment
        $paymentInfo->unsAdditionalInformation('brand_code');

        // Get additional data array
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        // Get a validated additional data array
        $additionalData = DataArrayValidator::getArrayOnlyWithApprovedKeys(
            $additionalData,
            self::$approvedAdditionalDataKeys
        );

        // JSON decode state data from the frontend or fetch it from the DB entity with the quote ID
        if (!empty($additionalData[self::STATE_DATA])) {
            $stateData = json_decode((string) $additionalData[self::STATE_DATA], true);
        } else {
            $stateData = $this->stateDataCollection->getStateDataArrayWithQuoteId($paymentInfo->getData('quote_id'));
        }
        // Get validated state data array
        if (!empty($stateData)) {
            $stateData = $this->checkoutStateDataValidator->getValidatedAdditionalData($stateData);
            // Set stateData in a service and remove from payment's additionalData
            $this->stateData->setStateData($stateData, $paymentInfo->getData('quote_id'));
        }


        unset($additionalData[self::STATE_DATA]);

        // Set additional data in the payment
        foreach (array_merge($additionalData, $additionalDataToSave) as $key => $data) {
            $paymentInfo->setAdditionalInformation($key, $data);
        }

        // Set ccType. If payment method is tokenizable, update additional information
        if (!empty($additionalData[self::BRAND_CODE])) {
            $paymentMethod = $additionalData[self::BRAND_CODE];
            $paymentInfo->setCcType($paymentMethod);
        }
    }
}
