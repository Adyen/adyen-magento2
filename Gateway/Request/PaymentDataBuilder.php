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

/**
 * Payment Data Builder
 */
class PaymentDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Requests
     */
    private $adyenRequestsHelper;

    /**
     * PaymentDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Requests $adyenRequestsHelper
     */
    public function __construct(\Adyen\Payment\Helper\Requests $adyenRequestsHelper)
    {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
    }
    
    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);

        $order = $paymentDataObject->getOrder();
        $payment = $paymentDataObject->getPayment();
        $fullOrder = $payment->getOrder();

        $currencyCode = $fullOrder->getOrderCurrencyCode();
        $amount = $fullOrder->getGrandTotal();
        $reference = $order->getOrderIncrementId();
        $paymentMethod = $payment->getMethod();

        $request['body'] = $this->adyenRequestsHelper->buildPaymentData([], $amount, $currencyCode, $reference, $paymentMethod);

        $request['headers'] = $this->adyenRequestsHelper->addIdempotencyKey([], $paymentMethod, $reference);

        return $request;
    }
}
