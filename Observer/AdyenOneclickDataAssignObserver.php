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
class AdyenOneclickDataAssignObserver extends AbstractDataAssignObserver
{
    const RECURRING_DETAIL_REFERENCE = 'recurring_detail_reference';
    const ENCRYPTED_DATA = 'encrypted_data';
    const NUMBER_OF_INSTALLMENTS = 'number_of_installments';

    /**
     * @var array
     */
    protected $additionalInformationList = [
        self::RECURRING_DETAIL_REFERENCE,
        self::ENCRYPTED_DATA,
        self::NUMBER_OF_INSTALLMENTS
    ];

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
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Model\Context $context
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->appState = $context->getAppState();

    }

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

        // set ccType
        $variant = $additionalData['variant'];
        $ccType = $this->adyenHelper->getMagentoCreditCartType($variant);
        $paymentInfo->setCcType($ccType);

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }

        // set customerInteraction
        $recurringContractType = $this->getRecurringPaymentType();
        if ($recurringContractType == \Adyen\Payment\Model\RecurringType::ONECLICK) {
            $paymentInfo->setAdditionalInformation('customer_interaction', true);
        } else {
            $paymentInfo->setAdditionalInformation('customer_interaction', false);
        }

        // set ccType
        $variant = $additionalData['variant'];
        $ccType = $this->adyenHelper->getMagentoCreditCartType($variant);
        $paymentInfo->setAdditionalInformation('cc_type', $ccType);

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
