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

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Model\Context;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RecurringDataBuilder implements BuilderInterface
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $appState;

    /**
     * @var Requests
     */
    private $adyenRequestsHelper;

    /**
     * @var PaymentMethods
     */
    private $paymentMethodsHelper;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var AdyenOrderPayment
     */
    private $adyenOrderPayment;

    /**
     * RecurringDataBuilder constructor.
     *
     * @param Context $context
     * @param Requests $adyenRequestsHelper
     * @param PaymentMethods $paymentMethodsHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Context $context,
        Requests $adyenRequestsHelper,
        PaymentMethods $paymentMethodsHelper,
        AdyenLogger $adyenLogger,
        AdyenOrderPayment $adyenOrderPayment
    ) {
        $this->appState = $context->getAppState();
        $this->adyenRequestsHelper = $adyenRequestsHelper;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->adyenLogger = $adyenLogger;
        $this->adyenOrderPayment = $adyenOrderPayment;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $body = [];
        /** @var \Magento\Payment\Gateway\Data\PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        if ($this->paymentMethodsHelper->isCardPayment($payment)) {
            $body = $this->adyenRequestsHelper->buildCardRecurringData($storeId, $payment);
        } elseif ($this->paymentMethodsHelper->isAlternativePayment($payment)) {
            $body = $this->adyenRequestsHelper->buildAlternativePaymentRecurringData($storeId, $payment);
        } elseif ($this->paymentMethodsHelper->isTokenPayment($payment)) {
            $body = $this->adyenRequestsHelper->buildTokenizedPaymentRecurringData($storeId, $payment);
        } else {
            $this->adyenLogger->addAdyenWarning(
                sprintf('Unknown payment method: %s', $payment->getMethod()),
                $this->adyenOrderPayment->getLogOrderContext($order)
            );
        }

        return [
            'body' => $body
        ];
    }
}
