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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use \Adyen\Service\Validator\CheckoutStateDataValidator;
use \Adyen\Service\Validator\DataArrayValidator;

class AdyenCcDataAssignObserver extends AbstractDataAssignObserver
{
    const CC_TYPE = 'cc_type';
    const NUMBER_OF_INSTALLMENTS = 'number_of_installments';
    const STORE_CC = 'store_cc';
    const GUEST_EMAIL = 'guestEmail';
    const COMBO_CARD_TYPE = 'combo_card_type';
    const STATE_DATA = 'stateData';
    const STORE_PAYMENT_METHOD = 'storePaymentMethod';

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
        self::CC_TYPE
    ];

    /**
     * @var CheckoutStateDataValidator
     */
    protected $checkoutStateDataValidator;

    /**
     * AdyenCcDataAssignObserver constructor.
     *
     * @param CheckoutStateDataValidator $checkoutStateDataValidator
     */
    public function __construct(
        CheckoutStateDataValidator $checkoutStateDataValidator
    ) {
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
    }

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
        $additionalData = DataArrayValidator::getArrayOnlyWithApprovedKeys(
            $additionalData,
            self::$approvedAdditionalDataKeys
        );

        // json decode state data
        $stateData = [];
        if (!empty($additionalData[self::STATE_DATA])) {
            $stateData = json_decode($additionalData[self::STATE_DATA], true);
        }

        // Get validated state data array
        if (!empty($stateData)) {
            $stateData = $this->checkoutStateDataValidator->getValidatedAdditionalData(
                $stateData
            );
        }

        // Replace state data with the decoded and validated state data
        $additionalData[self::STATE_DATA] = $stateData;

        // Set additional data in the payment
        $paymentInfo = $this->readPaymentModelArgument($observer);
        foreach ($additionalData as $key => $data) {
            $paymentInfo->setAdditionalInformation($key, $data);
        }

        // set ccType
        if (!empty($additionalData[self::CC_TYPE])) {
            $paymentInfo->setCcType($additionalData[self::CC_TYPE]);
        }

        // set storeCc
        if (!empty($stateData[self::STORE_PAYMENT_METHOD])) {
            $paymentInfo->setAdditionalInformation(self::STORE_CC, $stateData[self::STORE_PAYMENT_METHOD]);
        }
    }
}
