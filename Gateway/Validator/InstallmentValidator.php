<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2017 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */


namespace Adyen\Payment\Gateway\Validator;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\QuoteRepository;

class InstallmentValidator extends AbstractValidator
{
    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var \Adyen\Payment\Helper\Config
     */
    private $configHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;


    /**
     * InstallmentValidator constructor.
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param Config $configHelper
     * @param AdyenLogger $adyenLogger
     * @param Data $adyenHelper
     * @param SerializerInterface $serializer
     * @param QuoteRepository $quoteRepository
     * @param ChargedCurrency $chargedCurrency
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \Adyen\Payment\Helper\Config $configHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        ChargedCurrency $chargedCurrency
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        $this->configHelper = $configHelper;
        $this->serializer = $serializer;
        $this->quoteRepository = $quoteRepository;
        $this->chargedCurrency = $chargedCurrency;
        parent::__construct($resultFactory);
    }

    public function validate(array $validationSubject)
    {
        $isValid = true;
        $fails = [];
        $payment = $validationSubject['payment'];
        $quoteId = $payment->getQuoteId();
        //This validator also runs for other payments that don't necesarily have a quoteId
        if ($quoteId) {
            $quote = $this->quoteRepository->get($quoteId);
        } else {
            $quote = false;
        }
        $installmentsEnabled = $this->configHelper->getAdyenCcConfigData('enable_installments');
        if ($quote && $installmentsEnabled) {
            $grandTotal = $this->chargedCurrency->getQuoteAmountCurrency($quote)->getAmount();
            $installmentsAvailable = $this->configHelper->getAdyenCcConfigData('installments');
            $installmentSelected = $payment->getAdditionalInformation('number_of_installments');
            $ccType = $payment->getAdditionalInformation('cc_type');
            if ($installmentsAvailable) {
                $installments = $this->serializer->unserialize($installmentsAvailable);
            }
            if ($installmentSelected && $installmentsAvailable) {
                $isValid = false;
                $fails[] = __('Installments not valid.');
                if ($installments) {
                    foreach ($installments as $ccTypeInstallment => $installment) {
                        if ($ccTypeInstallment == $ccType) {
                            foreach ($installment as $amount => $amountInstallments) {
                                foreach ($amountInstallments as $installmentsData) {
                                    if ($installmentSelected == $installmentsData) {
                                        if ($grandTotal >= $amount) {
                                            $isValid = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $this->createResult($isValid, $fails);
    }
}
