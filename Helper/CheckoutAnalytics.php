<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutAnalytics
{
    const CHECKOUT_ANALYTICS_TEST_ENDPOINT =
        'https://checkoutanalytics-test.adyen.com//checkoutanalytics/v3/analytics';
    const CHECKOUT_ANALYTICS_LIVE_ENDPOINT =
        'https://checkoutanalytics.adyen.com//checkoutanalytics/v3/analytics';
    const CHECKOUT_ATTEMPT_ID = 'checkoutAttemptId';
    const FLAVOR_COMPONENT = 'component';
    const INTEGRATOR_ADYEN = 'Adyen';

    /**
     * @param Config $configHelper
     * @param PlatformInfo $platformInfoHelper
     * @param StoreManagerInterface $storeManager
     * @param AdyenLogger $adyenLogger
     * @param ClientInterface $curl
     */
    public function __construct(
        private readonly Config $configHelper,
        private readonly PlatformInfo $platformInfoHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdyenLogger $adyenLogger,
        private readonly ClientInterface $curl
    ) { }

    /**
     * Makes the initial API call to CheckoutAnalytics to obtain checkoutAttemptId
     *
     * @return string|null
     */
    public function initiateCheckoutAttempt(): ?string
    {
        try {
            $request = $this->buildInitiateCheckoutRequest();
            $endpoint = $this->getInitiateAnalyticsUrl();

            $response = $this->sendRequest($endpoint, $request);

            if ($this->validateInitiateCheckoutAttemptResponse($response)) {
                return $response[self::CHECKOUT_ATTEMPT_ID];
            }
        } catch (Exception $exception) {
            $errorMessage = __('Error while initiating checkout attempt: %s.', $exception->getMessage());
            $this->adyenLogger->error($errorMessage);
        }

        return null;
    }

    /**
     * Sends info, log or error messages to CheckoutAnalytics
     *
     * @param string $checkoutAttemptId
     * @param array $events
     * @param string|null $channel
     * @param string|null $platform
     * @return void
     */
    public function sendAnalytics(
        string $checkoutAttemptId,
        array $events,
        ?string $channel = null,
        ?string $platform = null
    ): void {
        try {
            $request = $this->buildSendAnalyticsRequest($events, $channel, $platform);
            $endpoint = $this->getSendAnalyticsUrl($checkoutAttemptId);
            $this->sendRequest($endpoint, $request);
        } catch (Exception $exception) {
            $errorMessage = __('Error while sending checkout analytic metrics: %s', $exception->getMessage());
            $this->adyenLogger->error($errorMessage);
        }
    }


    /**
     * Builds the endpoint URL for sending analytics messages to CheckoutAnalytics
     *
     * @param string $checkoutAttemptId
     * @return string
     * @throws NoSuchEntityException
     * @throws AdyenException
     */
    private function getSendAnalyticsUrl(string $checkoutAttemptId): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isDemoMode = $this->configHelper->isDemoMode($storeId);
        $mode = $isDemoMode ? 'test' : 'live';
        $clientKey = $this->configHelper->getClientKey($mode, $storeId);

        if (is_null($clientKey)) {
            throw new AdyenException("clientKey is not configured!");
        }

        return sprintf(
            "%s/%s?clientKey=%s",
            $this->getEndpointUrl($isDemoMode),
            $checkoutAttemptId,
            $clientKey
        );
    }

    /**
     * Builds the request for sending analytics messages to CheckoutAnalytics
     *
     * @param array $events
     * @param string|null $channel
     * @param string|null $platform
     * @return array
     * @throws InvalidArgumentException
     */
    // Replace buildSendAnalyticsRequest() with this
    private function buildSendAnalyticsRequest(
        array $events,
        ?string $channel = null,
        ?string $platform = null
    ): array {
        if (empty($events)) {
            throw new InvalidArgumentException(__('Events array cannot be empty!'));
        }

        $info   = [];
        $errors = [];

        foreach ($events as $event) {
            $createdAt  = $this->getField($event, 'createdAt');
            $uuid       = (string)$this->getField($event, 'uuid');
            $topic      = (string)$this->getField($event, 'topic');
            $type       = (string)$this->getField($event, 'type');
            $relationId = (string)$this->getField($event, 'relationId');

            // Validate required bits (per schema mapping)
            if ($createdAt === null || $uuid === '' || $topic === '' || $type === '' || $relationId === '') {
                // Skip malformed event instead of failing the batch
                continue;
            }

            $timestampMs = $this->toUnixMillisString($createdAt);

            // INFO: cap at 50
            if (count($info) < 50) {
                $info[] = [
                    'timestamp' => $timestampMs,
                    'type'      => $type,
                    'target'    => $relationId,
                    'id'        => $uuid,
                    'component' => $topic,
                ];
            }

            // ERRORS: only for unexpectedEnd, cap at 5
            if ($type === 'unexpectedEnd' && count($errors) < 5) {
                $errors[] = [
                    'timestamp' => $timestampMs,
                    'id'        => $uuid,
                    'component' => $topic,
                    'errorType' => 'Plugin',
                ];
            }
        }

        if (empty($info) && empty($errors)) {
            throw new InvalidArgumentException(__('No valid analytics events to send!'));
        }

        $request = [
            'channel'  => $channel ?? 'Web',
            'platform' => $platform ?? 'Web',
        ];

        if (!empty($info)) {
            $request['info'] = $info;
        }
        if (!empty($errors)) {
            $request['errors'] = $errors;
        }

        return $request;
    }

    /**
     * Generates the endpoint URL for initializing the checkout attempt
     *
     * @return string
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    private function getInitiateAnalyticsUrl(): string
    {
        $storeId = $this->storeManager->getStore()->getId();
        $isDemoMode = $this->configHelper->isDemoMode($storeId);
        $mode = $isDemoMode ? 'test' : 'live';
        $clientKey = $this->configHelper->getClientKey($mode, $storeId);

        if (is_null($clientKey)) {
            throw new AdyenException("clientKey is not configured!");
        }

        return sprintf("%s?clientKey=%s", $this->getEndpointUrl($isDemoMode), $clientKey);
    }

    /**
     * Builds the request array for initiate checkout attempt
     *
     * @return array
     */
    private function buildInitiateCheckoutRequest(): array
    {
        $platformData = $this->platformInfoHelper->getMagentoDetails();

        $request = [
            'channel' => 'Web',
            'platform' => 'Web',
            'pluginVersion' => $this->platformInfoHelper->getModuleVersion(),
            'plugin' => 'adobeCommerce',
            'applicationInfo' => [
                'merchantApplication' => [
                    'name' => $this->platformInfoHelper->getModuleName(),
                    'version' => $this->platformInfoHelper->getModuleVersion()
                ],
                'externalPlatform' => [
                    'name' => $platformData['name'],
                    'version' => $platformData['version'],
                    'integrator' => self::INTEGRATOR_ADYEN
                ]
            ]
        ];

        return $request;
    }

    /**
     * @param $response
     * @return bool
     * @throws InvalidArgumentException
     */
    private function validateInitiateCheckoutAttemptResponse($response): bool
    {
        if(!array_key_exists('checkoutAttemptId', $response)) {
            throw new InvalidArgumentException(__('checkoutAttemptId is missing in the response!'));
        }

        return true;
    }

    /**
     * Returns the CheckoutAnalytics endpoint URL depending on the store mode
     *
     * @param bool $isDemoMode
     * @return string
     */
    private function getEndpointUrl(bool $isDemoMode): string
    {
        if ($isDemoMode) {
            $apiUrl = self::CHECKOUT_ANALYTICS_TEST_ENDPOINT;
        } else {
            $apiUrl = self::CHECKOUT_ANALYTICS_LIVE_ENDPOINT;
        }

        return $apiUrl;
    }

    /**
     * Sends the payload to the given endpoint using Magento cUrl client
     *
     * @param string $endpoint
     * @param array $payload
     * @return array|null
     */
    private function sendRequest(string $endpoint, array $payload): ?array
    {
        $this->curl->addHeader('Content-Type', 'application/json');

        $this->curl->post($endpoint, json_encode($payload));
        $result = $this->curl->getBody();

        if (empty($result)) {
            return null;
        } else {
            return json_decode($result, true);
        }
    }

    // Add these helpers in the class

    /**
     * Get a field from an event that may be an object (with getter/public prop) or array.
     */
    private function getField($event, string $name)
    {
        $getter = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
        if (is_object($event)) {
            if (method_exists($event, $getter)) {
                return $event->{$getter}();
            }
            if (isset($event->{$name})) {
                return $event->{$name};
            }
        } elseif (is_array($event)) {
            return $event[$name] ?? null;
        }
        return null;
    }

    /**
     * Convert DateTimeInterface|string|int to milliseconds since epoch as a string (schema requires string).
     */
    private function toUnixMillisString($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return (string)($value->getTimestamp() * 1000);
        }
        if (is_int($value)) {
            // if seconds (10 digits), convert; if already ms (13 digits), keep
            return strlen((string)$value) >= 13 ? (string)$value : (string)($value * 1000);
        }
        // string: try to detect ms vs seconds vs date string
        $trim = trim((string)$value);
        if (ctype_digit($trim)) {
            return strlen($trim) >= 13 ? $trim : (string)((int)$trim * 1000);
        }
        $ts = strtotime($trim);
        return $ts !== false ? (string)($ts * 1000) : '0';
    }


}
