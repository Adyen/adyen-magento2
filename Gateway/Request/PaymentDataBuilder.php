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

use Adyen\Payment\Helper\ChargedCurrency;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentDataBuilder implements BuilderInterface
{
    /**
     * @var \Adyen\Payment\Helper\Requests
     */
    private $adyenRequestsHelper;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * PaymentDataBuilder constructor.
     *
     * @param \Adyen\Payment\Helper\Requests $adyenRequestsHelper
     * @param ChargedCurrency $chargedCurrency
     */
    public function __construct(
        \Adyen\Payment\Helper\Requests $adyenRequestsHelper,
        ChargedCurrency $chargedCurrency
    ) {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
        $this->chargedCurrency = $chargedCurrency;
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

        $amountCurrency = $this->chargedCurrency->getOrderAmountCurrency($fullOrder);
        $currencyCode = $amountCurrency->getCurrencyCode();
        $amount = $amountCurrency->getAmount();
        $reference = $order->getOrderIncrementId();
        $paymentMethod = $payment->getMethod();

        $request['body'] = $this->adyenRequestsHelper->buildPaymentData(
            $amount,
            $currencyCode,
            $reference,
            $paymentMethod,
            []
        );

        return $request;
    }
}
