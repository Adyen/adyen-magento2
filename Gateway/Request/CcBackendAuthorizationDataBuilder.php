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
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;

class CcBackendAuthorizationDataBuilder implements BuilderInterface
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
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $paymentDataObject->getOrder();
        $storeId = $order->getStoreId();
        $requestBody = [];
        // If ccType is set use this. For bcmc you need bcmc otherwise it will fail
        $requestBody['paymentMethod']['type'] = 'scheme';
        if ($cardNumber = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER)) {
            $requestBody['paymentMethod']['encryptedCardNumber'] = $cardNumber;
        }
        if ($expiryMonth = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH)) {
            $requestBody['paymentMethod']['encryptedExpiryMonth'] = $expiryMonth;
        }
        if ($expiryYear = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR)) {
            $requestBody['paymentMethod']['encryptedExpiryYear'] = $expiryYear;
        }
        if ($holderName = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::HOLDER_NAME)) {
            $requestBody['paymentMethod']['holderName'] = $holderName;
        }
        if ($securityCode = $payment->getAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE)) {
            $requestBody['paymentMethod']['encryptedSecurityCode'] = $securityCode;
        }
        // Remove from additional data
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_CREDIT_CARD_NUMBER);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_MONTH);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_EXPIRY_YEAR);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::ENCRYPTED_SECURITY_CODE);
        $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::HOLDER_NAME);
        /**
         * On Backend always use MOTO
         */
        $requestBody['shopperInteraction'] = "Moto";
        // if installments is set add it into the request
        if ($payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) &&
            $payment->getAdditionalInformation(AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS) > 0
        ) {
            $requestBody['installments']['value'] = $payment->getAdditionalInformation(
                AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS
            );
        }
        $request['body'] = $requestBody;

        return $request;
    }
}
