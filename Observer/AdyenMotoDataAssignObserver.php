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

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Helper\Util\DataArrayValidator;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class AdyenMotoDataAssignObserver extends AbstractDataAssignObserver
{
    const CC_TYPE = 'cc_type';
    const NUMBER_OF_INSTALLMENTS = 'number_of_installments';
    const STORE_CC = 'store_cc';
    const GUEST_EMAIL = 'guestEmail';
    const COMBO_CARD_TYPE = 'combo_card_type';
    const STATE_DATA = 'stateData';
    const STORE_PAYMENT_METHOD = 'storePaymentMethod';
    const MOTO_MERCHANT_ACCOUNT = 'motoMerchantAccount';

    /**
     * Approved root level keys from additional data array
     *
     * @var array
     */
    private static $approvedAdditionalDataKeys = [
        self::STATE_DATA,
        self::GUEST_EMAIL,
        self::COMBO_CARD_TYPE,
        self::NUMBER_OF_INSTALLMENTS,
        self::CC_TYPE,
        self::MOTO_MERCHANT_ACCOUNT
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

    /**
     * AdyenCcDataAssignObserver constructor.
     * @param CheckoutStateDataValidator $checkoutStateDataValidator
     * @param Collection $stateDataCollection
     * @param StateData $stateData
     */
    public function __construct(
        CheckoutStateDataValidator $checkoutStateDataValidator,
        Collection $stateDataCollection,
        StateData $stateData
    ) {
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->stateDataCollection = $stateDataCollection;
        $this->stateData = $stateData;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        // Get request fields
        $data = $this->readDataArgument($observer);
        $paymentInfo = $this->readPaymentModelArgument($observer);

        // Remove cc_type information from the previous payment
        $paymentInfo->unsAdditionalInformation('cc_type');

        // Get additional data array
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        // Remove cc_type information from the previous payment
        $paymentInfo->unsAdditionalInformation('cc_type');

        // Get a validated additional data array
        $additionalData = DataArrayValidator::getArrayOnlyWithApprovedKeys(
            $additionalData,
            self::$approvedAdditionalDataKeys
        );

        // JSON decode state data from the frontend or fetch it from the DB entity with the quote ID
        if (!empty($additionalData[self::STATE_DATA])) {
            $orderStateData = json_decode((string) $additionalData[self::STATE_DATA], true);
        }

        // Get validated state data array
        if (!empty($orderStateData)) {
            $orderStateData = $this->checkoutStateDataValidator->getValidatedAdditionalData($orderStateData);
            // Set stateData in a service and remove from payment's additionalData
            $this->stateData->setStateData($orderStateData, $paymentInfo->getData('quote_id'));
        }

        unset($additionalData[self::STATE_DATA]);

        // Set additional data in the payment
        foreach ($additionalData as $key => $data) {
            $paymentInfo->setAdditionalInformation($key, $data);
        }

        // set ccType
        if (!empty($additionalData[self::CC_TYPE])) {
            $paymentInfo->setCcType($additionalData[self::CC_TYPE]);
        }

        // set storeCc
        if (!empty($orderStateData[self::STORE_PAYMENT_METHOD])) {
            $paymentInfo->setAdditionalInformation(self::STORE_CC, $orderStateData[self::STORE_PAYMENT_METHOD]);
        }

        // set MOTO merchant account
        if (!empty($orderStateData[self::MOTO_MERCHANT_ACCOUNT])) {
            $paymentInfo->setAdditionalInformation(self::MOTO_MERCHANT_ACCOUNT, $orderStateData[self::MOTO_MERCHANT_ACCOUNT]);
        }
    }
}
