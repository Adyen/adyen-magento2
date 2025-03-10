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

use Adyen\Exception\MissingDataException;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Requests;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class PaymentDataBuilder implements BuilderInterface
{
    /**
     * @var Requests
     */
    private $adyenRequestsHelper;

    /**
     * @var ChargedCurrency
     */
    private $chargedCurrency;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * PaymentDataBuilder constructor.
     *
     * @param Requests $adyenRequestsHelper
     * @param ChargedCurrency $chargedCurrency
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Requests $adyenRequestsHelper,
        ChargedCurrency $chargedCurrency,
        CheckoutSession $checkoutSession
    ) {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
        $this->chargedCurrency = $chargedCurrency;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws MissingDataException
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $fullOrder = $payment->getOrder();
        $quote = $this->checkoutSession->getQuote();
        $amountCurrency = $this->chargedCurrency->getOrderAmountCurrency($fullOrder);
        $currencyCode = $amountCurrency->getCurrencyCode();
        $amount = $amountCurrency->getAmount();
        $reference = $fullOrder->getIncrementId();
        $shopperConversionId = $quote->getPayment()->getAdditionalInformation('shopper_conversion_id');

        $request['body'] = $this->adyenRequestsHelper->buildPaymentData(
            $amount,
            $currencyCode,
            $reference,
            $shopperConversionId,
            []
        );

        return $request;
    }
}
