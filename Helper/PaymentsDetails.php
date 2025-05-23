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
use Adyen\Model\Checkout\PaymentDetailsRequest;
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

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * @var AdyenLogger
     */
    private AdyenLogger $adyenLogger;

    /**
     * @var Idempotency
     */
    private Idempotency $idempotencyHelper;

    /**
     * @var PlatformInfo
     */
    private PlatformInfo $platformInfo;

    /**
     * @param Session $checkoutSession
     * @param Data $adyenHelper
     * @param AdyenLogger $adyenLogger
     * @param Idempotency $idempotencyHelper
     * @param PlatformInfo $platformInfo
     */
    public function __construct(
        Session $checkoutSession,
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        Idempotency $idempotencyHelper,
        PlatformInfo $platformInfo
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->idempotencyHelper = $idempotencyHelper;
        $this->platformInfo = $platformInfo;
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
            $service = $this->adyenHelper->initializePaymentsApi($client);

            $requestOptions['idempotencyKey'] = $this->idempotencyHelper->generateIdempotencyKey($request);
            $requestOptions['headers'] = $this->platformInfo->buildRequestHeaders();

            $paymentDetailsObj = $service->paymentsDetails(new PaymentDetailsRequest($request), $requestOptions);
            $response = $paymentDetailsObj->toArray();
        } catch (AdyenException $e) {
            $this->adyenLogger->error("Payment details call failed: " . $e->getMessage());
            $this->checkoutSession->restoreQuote();

            throw new ValidatorException(__('Payment details call failed'));
        }

        return $response;
    }

    /**
     * @param array $payload
     * @return array
     */
    private function cleanUpPaymentDetailsPayload(array $payload): array
    {
        $payload = DataArrayValidator::getArrayOnlyWithApprovedKeys(
            $payload,
            self::PAYMENTS_DETAILS_KEYS
        );

        foreach (self::REQUEST_HELPER_PARAMETERS as $helperParam) {
            if (isset($payload['details']) && array_key_exists($helperParam, $payload['details'])) {
                unset($payload['details'][$helperParam]);
            }
        }

        return $payload;
    }
}
