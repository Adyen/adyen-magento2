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

use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Adyen\Service\Validator\CheckoutStateDataValidator;
use Adyen\Service\Validator\DataArrayValidator;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class AdyenHppDataAssignObserver extends AbstractDataAssignObserver
{
    const BRAND_CODE = 'brand_code';
    const DF_VALUE = 'df_value';
    const GUEST_EMAIL = 'guestEmail';
    const STATE_DATA = 'stateData';

    /**
     * Approved root level keys from additional data array
     *
     * @var array
     */
    private static $approvedAdditionalDataKeys = [
        self::BRAND_CODE,
        self::DF_VALUE,
        self::GUEST_EMAIL,
        self::STATE_DATA
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
     * AdyenHppDataAssignObserver constructor.
     *
     * @param CheckoutStateDataValidator $checkoutStateDataValidator
     */
    public function __construct(
        CheckoutStateDataValidator $checkoutStateDataValidator,
        Collection $stateDataCollection
    ) {
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->stateDataCollection = $stateDataCollection;
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

        // Get additional data array
        $additionalInformation = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalInformation)) {
            return;
        }

        // Get a validated additional data array
        $additionalInformation = DataArrayValidator::getArrayOnlyWithApprovedKeys(
            $additionalInformation,
            self::$approvedAdditionalDataKeys
        );

        // JSON decode state data from the frontend or fetch it from the DB entity with the quote ID
        if (!empty($additionalInformation[self::STATE_DATA])) {
            $stateData = json_decode($additionalInformation[self::STATE_DATA], true);
        } else {
            $stateData = $this->stateDataCollection->getStateDataArrayWithQuoteId($paymentInfo->getData('quote_id'));
        }

        // Get validated state data array
        if (!empty($stateData)) {
            $stateData = $this->checkoutStateDataValidator->getValidatedAdditionalData(
                $stateData
            );
        }

        // Replace state data with the decoded and validated state data
        $additionalInformation[self::STATE_DATA] = $stateData;

        // Set additional data in the payment
        foreach ($additionalInformation as $key => $data) {
            $paymentInfo->setAdditionalInformation($key, $data);
        }

        // set ccType
        if (!empty($additionalInformation[self::BRAND_CODE])) {
            $paymentInfo->setCcType($additionalInformation[self::BRAND_CODE]);
        }
    }
}
