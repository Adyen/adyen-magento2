<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Enum\PaymentDetailsError;
use Adyen\Payment\Exception\AdyenPaymentDetailsException;
use Adyen\Payment\Helper\Util\DataArrayValidator;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Sales\Api\Data\OrderInterface;

class PaymentsDetails
{
    const PAYMENTS_DETAILS_KEYS = [
        'details',
        'paymentData',
        'threeDSAuthenticationOnly'
    ];

    const REQUEST_HELPER_PARAMETERS =  [
        'isAjax',
        'merchantReference'
    ];

    private Session $checkoutSession;
    private Data $adyenHelper;
    private AdyenLogger $adyenLogger;
    private Idempotency $idempotencyHelper;

    public function __construct(
        Session $checkoutSession,
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        Idempotency $idempotencyHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->idempotencyHelper = $idempotencyHelper;
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ValidatorException
     */
    public function initiatePaymentDetails(OrderInterface $order, array $payload): array
    {
        $request = $this->cleanUpPaymentDetailsPayload($payload);

        try {
            $client = $this->adyenHelper->initializeAdyenClient($order->getStoreId());
            $service = $this->adyenHelper->createAdyenCheckoutService($client);

            $requestOptions['idempotencyKey'] = $this->idempotencyHelper->generateIdempotencyKey($request);
            $requestOptions['headers'] = $this->adyenHelper->buildRequestHeaders();

            $response = $service->paymentsDetails($request, $requestOptions);
        } catch (AdyenException $e) {
            $this->adyenLogger->error("Payment details call failed: " . $e->getMessage());
            $this->checkoutSession->restoreQuote();

            throw new AdyenPaymentDetailsException(
                __('Payment details call failed'),
                PaymentDetailsError::ApiCallFailed,
            );
        }

        return $response;
    }

    private function cleanUpPaymentDetailsPayload(array $payload): array
    {
        $payload = DataArrayValidator::getArrayOnlyWithApprovedKeys(
            $payload,
            self::PAYMENTS_DETAILS_KEYS
        );

        foreach (self::REQUEST_HELPER_PARAMETERS as $helperParam) {
            if (array_key_exists($helperParam, $payload['details'])) {
                unset($payload['details'][$helperParam]);
            }
        }

        return $payload;
    }
}
