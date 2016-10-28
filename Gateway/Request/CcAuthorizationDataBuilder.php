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

use Magento\Payment\Gateway\Request\BuilderInterface;

class CcAuthorizationDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * CcAuthorizationDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\Model\Context $context
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Model\Context $context
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->appState = $context->getAppState();
    }

    /**
     * @param array $buildSubject
     * @return mixed
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $paymentDataObject->getOrder();
        $storeId = $order->getStoreId();
        $request = [];

        if ($this->adyenHelper->getAdyenCcConfigDataFlag('cse_enabled', $storeId)) {
            $request['additionalData']['card.encrypted.json'] =
                $payment->getAdditionalInformation("encrypted_data");
        } else {
            $requestCreditCardDetails = [
                "expiryMonth" => $payment->getCcExpMonth(),
                "expiryYear" => $payment->getCcExpYear(),
                "holderName" => $payment->getCcOwner(),
                "number" => $payment->getCcNumber(),
                "cvc" => $payment->getCcCid(),
            ];
            $cardDetails['card'] = $requestCreditCardDetails;
            $request = array_merge($request, $cardDetails);
        }

        /**
         * if MOTO for backend is enabled use MOTO as shopper interaction type
         */
        $enableMoto = $this->adyenHelper->getAdyenCcConfigDataFlag('enable_moto', $storeId);
        if ($this->appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE &&
            $enableMoto) {
            $request['shopperInteraction'] = "Moto";
        }
        // if installments is set add it into the request
        if ($payment->getAdditionalInformation('number_of_installments') &&
            $payment->getAdditionalInformation('number_of_installments')  > 0) {
            $request['installments']['value'] = $payment->getAdditionalInformation('number_of_installments');
        }
        
        return $request;
    }
}