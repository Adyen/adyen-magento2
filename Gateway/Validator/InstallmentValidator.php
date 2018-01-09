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
 * Adyen Payment Module
 *
 * Copyright (c) 2017 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */


namespace Adyen\Payment\Gateway\Validator;


use Magento\Payment\Gateway\Validator\AbstractValidator;

class InstallmentValidator extends AbstractValidator
{
    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $session;

    /**
     * InstallmentValidator constructor.
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\App\ObjectManager $objectManager
     */
    public function __construct(
        \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Checkout\Model\Session $session
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        $this->session = $session;
        parent::__construct($resultFactory);
    }


    public function validate(array $validationSubject)
    {
        $isValid = true;
        $fails = [];
        $payment = $validationSubject['payment'];
        $quote = $this->session->getQuote();
        if ($quote) {
            $grandTotal = $quote->getGrandTotal();
            $installmentsAvailable = $this->adyenHelper->getAdyenCcConfigData('installments');
            $installmentSelected = $payment->getAdditionalInformation('number_of_installments');
            $ccType = $payment->getAdditionalInformation('cc_type');
            if ($installmentsAvailable) {
                $installments = unserialize($installmentsAvailable);
            }
            if ($installmentSelected && $installmentsAvailable) {
                $isValid = false;
                $fails[] = __('Installments not valid.');
                if ($installments) {
                    foreach ($installments as $ccTypeInstallment => $installment) {
                        if ($ccTypeInstallment == $ccType) {
                            foreach ($installment as $amount => $installmentsData) {
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
        return $this->createResult($isValid, $fails);
    }
}
