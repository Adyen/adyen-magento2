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
     * CaptureDataBuilder constructor.
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
        $request = [];

        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();

        if ($payment->getAdditionalInformation('customer_interaction')) {
            $shopperInteraction = "Ecommerce";
        } else {
            $shopperInteraction = "ContAuth";
        }

        $request['shopperInteraction'] = $shopperInteraction;
        $request['paymentMethod']['recurringDetailReference'] = $payment->getAdditionalInformation(AdyenOneclickDataAssignObserver::RECURRING_DETAIL_REFERENCE);

        // if it is a sepadirectdebit set selectedBrand to sepadirectdebit in the case of oneclick
        if ($payment->getCcType() == "sepadirectdebit") {
            $request['selectedBrand'] = "sepadirectdebit";
        }

        /*
         * For recurring Ideal and Sofort needs to be converted to SEPA
         * for this it is mandatory to set selectBrand to sepadirectdebit
         */
        if (!$payment->getAdditionalInformation('customer_interaction')) {
            if ($payment->getCcType() == "directEbanking" || $payment->getCcType() == "ideal") {
                $request['selectedBrand'] = "sepadirectdebit";
            }
        }

        return $request;
    }
}
