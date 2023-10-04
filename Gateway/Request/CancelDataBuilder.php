<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Config;

/**
 * Class CustomerDataBuilder
 */
class CancelDataBuilder implements BuilderInterface
{
    /** @var Payment $adyenPaymentResourceModel */
    private $adyenPaymentResourceModel;

    /** @var Config $configHelper */
    private $configHelper;

    /** @var Data $adyenHelper */
    private $adyenHelper;

    public function __construct(
        Payment      $adyenPaymentResourceModel,
        Data         $adyenHelper,
        Config $configHelper
    ){
        $this->adyenPaymentResourceModel = $adyenPaymentResourceModel;
        $this->adyenHelper = $adyenHelper;
        $this->configHelper = $configHelper;
    }

    /**
     * Create cancel_or_refund request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $order = $paymentDataObject->getOrder();
        $payment = $paymentDataObject->getPayment();

        $storeId = $order->getStoreId();
        $method = $payment->getMethod();

        if (isset($method) && $method === 'adyen_moto') {
            $merchantAccount = $payment->getAdditionalInformation('motoMerchantAccount');
        } else {
            $merchantAccount = $this->adyenHelper->getAdyenMerchantAccount($method, $storeId);
        }
        $pspReferences = $this->adyenPaymentResourceModel->getLinkedAdyenOrderPayments(
            $payment->getEntityId()
        );

        $requests['body'] = [];

        foreach ($pspReferences as $pspReference) {
            $request = [
                "paymentPspReference" => $pspReference['pspreference'],
                "reference" => $order->getOrderIncrementId(),
                "merchantAccount" => $merchantAccount
            ];

            $requests['body'][] = $request;
        }
        $requests['clientConfig'] = ["storeId" => $order->getStoreId()];

        return $requests;
    }
}
