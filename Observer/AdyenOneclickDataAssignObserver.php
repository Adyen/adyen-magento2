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

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Adyen\Service\Validator\CheckoutStateDataValidator;
use Adyen\Service\Validator\DataArrayValidator;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Model\Context;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class AdyenOneclickDataAssignObserver extends AbstractDataAssignObserver
{
    const CC_TYPE = 'cc_type';
    const BRAND = 'brand';
    const NUMBER_OF_INSTALLMENTS = 'number_of_installments';
    const STATE_DATA = 'stateData';
    const PAYMENT_METHOD = 'paymentMethod';

    /**
     * Approved root level keys from additional data array
     *
     * @var array
     */
    private static $approvedAdditionalDataKeys = [
        self::STATE_DATA,
        self::NUMBER_OF_INSTALLMENTS,
    ];

    /**
     * @var CheckoutStateDataValidator
     */
    private $checkoutStateDataValidator;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var Collection
     */
    private $stateDataCollection;
    /**
     * @var StateData
     */
    private $stateData;

    /**
     * AdyenCcDataAssignObserver constructor.
     *
     * @param CheckoutStateDataValidator $checkoutStateDataValidator
     * @param Data $adyenHelper
     * @param Context $context
     * @param Collection $stateDataCollection
     */
    public function __construct(
        CheckoutStateDataValidator $checkoutStateDataValidator,
        Data $adyenHelper,
        Context $context,
        Collection $stateDataCollection,
        StateData $stateData
    ) {
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->adyenHelper = $adyenHelper;
        $this->appState = $context->getAppState();
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
            $stateData = json_decode($additionalData[self::STATE_DATA], true);
        } else {
            $stateData = $this->stateDataCollection->getStateDataArrayWithQuoteId($paymentInfo->getData('quote_id'));
        }
        // Get validated state data array
        if (!empty($stateData)) {
            $stateData = $this->checkoutStateDataValidator->getValidatedAdditionalData($stateData);
        }
        // Set stateData in a service and remove from payment's additionalData
        $this->stateData->setStateData($stateData, $paymentInfo->getData('quote_id'));
        unset($additionalData[self::STATE_DATA]);

        // Set additional data in the payment
        foreach ($additionalData as $key => $data) {
            $paymentInfo->setAdditionalInformation($key, $data);
        }

        // set ccType
        if (!empty($stateData[self::PAYMENT_METHOD][self::BRAND])) {
            $ccType = $this->adyenHelper->getMagentoCreditCartType($stateData[self::PAYMENT_METHOD][self::BRAND]);
            $paymentInfo->setCcType($ccType)->setAdditionalInformation(self::CC_TYPE, $ccType);
        }

        // set customerInteraction
        $recurringContractType = $this->getRecurringPaymentType();
        if ($recurringContractType == \Adyen\Payment\Model\RecurringType::ONECLICK) {
            $paymentInfo->setAdditionalInformation('customer_interaction', true);
        } else {
            $paymentInfo->setAdditionalInformation('customer_interaction', false);
        }
    }

    /**
     * For admin use RECURRING contract for front-end get it from configuration
     *
     * @return mixed|string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getRecurringPaymentType()
    {
        // For admin always use Recurring
        if ($this->appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            return \Adyen\Payment\Model\RecurringType::RECURRING;
        } else {
            return $this->adyenHelper->getAdyenOneclickConfigData('recurring_payment_type');
        }
    }
}
