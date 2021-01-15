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
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\AdyenOrderPaymentStatusInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Framework\Exception\NoSuchEntityException;

class AdyenOrderPaymentStatus implements AdyenOrderPaymentStatusInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var Data
     */
    protected $adyenHelper;

    /**
     * @var PaymentResponseHandler
     */
    private $paymentResponseHandler;

    /**
     * AdyenOrderPaymentStatus constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param AdyenLogger $adyenLogger
     * @param Data $adyenHelper
     * @param PaymentResponseHandler $paymentResponseHandler
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        AdyenLogger $adyenLogger,
        Data $adyenHelper,
        PaymentResponseHandler $paymentResponseHandler
    ) {
        $this->orderRepository = $orderRepository;
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        $this->paymentResponseHandler = $paymentResponseHandler;
    }

    /**
     * @param string $orderId
     * @return bool|string
     */
    public function getOrderPaymentStatus($orderId)
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $exception) {
            $this->adyenLogger->addError('Order not found.');
            return json_encode(
                $this->paymentResponseHandler->formatPaymentResponse(PaymentResponseHandler::ERROR)
            );
        }

        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();

        if (empty($additionalInformation['resultCode'])) {
            $this->adyenLogger->addInfo('resultCode is empty in the payment\'s additional information');
            return json_encode(
                $this->paymentResponseHandler->formatPaymentResponse(PaymentResponseHandler::ERROR)
            );
        }

        return json_encode($this->paymentResponseHandler->formatPaymentResponse(
            $additionalInformation['resultCode'],
            !empty($additionalInformation['action']) ? $additionalInformation['action'] : null,
            !empty($additionalInformation['additionalData']) ? $additionalInformation['additionalData'] : null
        ));
    }
}
