<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Validator;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ChargedCurrency;
use Magento\Framework\Exception\NoSuchEntityException;
use Adyen\Payment\Helper\Data;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\QuoteRepository;

class InstallmentRequestValidator extends AbstractValidator
{
    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Config $configHelper
     * @param SerializerInterface $serializer
     * @param QuoteRepository $quoteRepository
     * @param ChargedCurrency $chargedCurrency
     * @param Data $adyenHelper
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly Config $configHelper,
        private readonly SerializerInterface $serializer,
        private readonly QuoteRepository $quoteRepository,
        private readonly ChargedCurrency $chargedCurrency,
        private readonly Data $adyenHelper
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     * @throws NoSuchEntityException
     */
    public function validate(array $validationSubject): ResultInterface
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

            $ccType = $this->adyenHelper->getMagentoCreditCartType($payment->getAdditionalInformation('cc_type'));

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

        return $this->createResult($isValid, !$isValid ? $fails : []);
    }
}
