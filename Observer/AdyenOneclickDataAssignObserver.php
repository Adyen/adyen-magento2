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

use Adyen\Service\Validator\CheckoutStateDataValidator;
use Adyen\Service\Validator\DataArrayValidator;
use Magento\Framework\Event\Observer;
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
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * AdyenCcDataAssignObserver constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        CheckoutStateDataValidator $checkoutStateDataValidator,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Model\Context $context
    ) {
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->adyenHelper = $adyenHelper;
        $this->appState = $context->getAppState();
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
        if (!empty($stateData[self::PAYMENT_METHOD][self::BRAND])) {
            $ccType = $this->adyenHelper->getMagentoCreditCartType($stateData[self::PAYMENT_METHOD][self::BRAND]);
            $paymentInfo->setCcType($ccType)->setAdditionalInformation(self::CC_TYPE,$ccType);
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
