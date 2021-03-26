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

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Observer\AdyenOneclickDataAssignObserver;
use Magento\Payment\Gateway\Request\BuilderInterface;

class OneclickAuthorizationDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * OneclickAuthorizationDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(\Adyen\Payment\Helper\Data $adyenHelper)
    {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param array $buildSubject
     * @return mixed
     */
    public function build(array $buildSubject)
    {
        $requestBody = [];

        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();

        // We override the previously set shopperInteraction in the buildRecurringData() to ContAuth here because if
        // it's a payment with a stored method we should always send ContAuth
        $requestBody['shopperInteraction'] = 'ContAuth';

        // if it is a sepadirectdebit set selectedBrand to sepadirectdebit in the case of oneclick
        if ($payment->getCcType() == "sepadirectdebit") {
            $requestBody['selectedBrand'] = "sepadirectdebit";
        }

        /*
         * For recurring Ideal and Sofort needs to be converted to SEPA
         * for this it is mandatory to set selectBrand to sepadirectdebit
         */
        if (!$payment->getAdditionalInformation('customer_interaction')) {
            if ($payment->getCcType() == "directEbanking" || $payment->getCcType() == "ideal") {
                $requestBody['selectedBrand'] = "sepadirectdebit";
            }
        }

        // if installments is set add it into the request
        if ($payment->getAdditionalInformation(AdyenOneclickDataAssignObserver::NUMBER_OF_INSTALLMENTS) > 0) {
            $requestBody['installments']['value'] = $payment->getAdditionalInformation(
                AdyenOneclickDataAssignObserver::NUMBER_OF_INSTALLMENTS
            );
        }

        $request['body'] = $requestBody;
        return $request;
    }
}
