<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Plugin;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class PaymentInformationResetOrderId
{
    /**
     * Quote repository.
     *
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * Payment methods helper
     *
     * @var PaymentMethods
     */
    protected $paymentMethodsHelper;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * PaymentInformationResetOrderId constructor.
     * @param CartRepositoryInterface $quoteRepository
     * @param PaymentMethods $paymentMethodsHelper
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        PaymentMethods $paymentMethodsHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param PaymentInformationManagementInterface $subject
     * @param $cartId
     * @return null
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId
    ) {
        try {
            $quote = $this->quoteRepository->get($cartId);
            $method = strval($quote->getPayment()->getMethod());

            if ($this->paymentMethodsHelper->isAdyenPayment($method)) {
                $quote->setReservedOrderId(null);
            }
        } catch (Exception $e) {
            $this->adyenLogger->error("Failed to reset reservedOrderId " . $e->getMessage());
        }
        return null;
    }
}
