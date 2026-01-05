<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutAnalytics
{
    const CHECKOUT_ANALYTICS_TEST_ENDPOINT =
        'https://checkoutanalytics-test.adyen.com/checkoutanalytics/v3/analytics';
    const CHECKOUT_ANALYTICS_LIVE_ENDPOINT =
        'https://checkoutanalytics.adyen.com/checkoutanalytics/v3/analytics';
    const CHECKOUT_ATTEMPT_ID = 'checkoutAttemptId';
    const FLAVOR_COMPONENT = 'component';
    const INTEGRATOR_ADYEN = 'Adyen';
    const PLUGIN_ADOBE_COMMERCE = 'adobeCommerce';
    const CHANNEL_WEB = 'Web';
    const PLATFORM_WEB = 'Web';
    const CONTEXT_TYPE_INFO = 'info';
    const CONTEXT_TYPE_LOGS = 'logs';
    const CONTEXT_TYPE_ERRORS = 'errors';
    const TOPIC_PLUGIN_CONFIGURATION_TIME = 'Adyen-ConfigurationTime';
    const CONTEXT_MAX_ITEMS = [
        self::CONTEXT_TYPE_INFO => 50,
        self::CONTEXT_TYPE_LOGS => 10,
        self::CONTEXT_TYPE_ERRORS => 5
    ];

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
     * @param string|null $version
     * @return string
     * @throws AdyenException
     */
    public function initiateCheckoutAttempt(?string $version = null): string
    {
        try {
            $request = $this->buildInitiateCheckoutRequest($version);
            $endpoint = $this->getInitiateAnalyticsUrl();

            $response = $this->sendRequest($endpoint, $request);
            $this->validateInitiateCheckoutAttemptResponse($response);

            return $response[self::CHECKOUT_ATTEMPT_ID];
        } catch (Exception $exception) {
            $errorMessage = __('Error while initiating checkout attempt: %s.', $exception->getMessage());
            $this->adyenLogger->error($errorMessage);

            throw new AdyenException($errorMessage);
        }
    }

    /**
     * Sends info, log or error messages to CheckoutAnalytics
     *
     * @param string $checkoutAttemptId
     * @param array $events
     * @param string $context
     * @return array|null
     */
    public function sendAnalytics(
        string $checkoutAttemptId,
        array $events,
        string $context
    ): ?array {
        try {
            $request = $this->buildSendAnalyticsRequest($events, $context);
            $endpoint = $this->getSendAnalyticsUrl($checkoutAttemptId);

            return $this->sendRequest($endpoint, $request);
        } catch (Exception $exception) {
            $errorMessage = __('Error while sending checkout analytic metrics: %s', $exception->getMessage());
            $this->adyenLogger->error($errorMessage);

            return [
                'error' => $errorMessage
            ];
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
     * @param AnalyticsEventInterface[] $events
     * @param string $context Type of the analytics event [info, errors, logs]
     * @return array
     * @throws ValidatorException
     * @throws AdyenException
     */
    private function buildSendAnalyticsRequest(
        array $events,
        string $context
    ): array {
        $this->validateEventsAndContext($events, $context);

        $items = [];

        foreach ($events as $event) {
            // Generic fields
            $contextPayload = [
                'timestamp' => strval($event->getCreatedAtTimestamp() * 1000),
                'component' => $event->getTopic(),
                'id' => $event->getUuid()
            ];

            // Context specific fields
            switch ($context) {
                case self::CONTEXT_TYPE_INFO:
                    $contextPayload['type'] = $event->getType();
                    $contextPayload['target'] = $event->getRelationId();
                    break;
                case self::CONTEXT_TYPE_LOGS:
                    $contextPayload['type'] = $event->getType();
                    $contextPayload['message'] = $event->getMessage();
                    break;
                case self::CONTEXT_TYPE_ERRORS:
                    $contextPayload['message'] = $event->getMessage();
                    $contextPayload['errorType'] = $event->getErrorType();
                    $contextPayload['code'] = $event->getErrorCode();
                    break;
                default:
                    throw new AdyenException("Invalid context type: $context");
            }

            $items[] = $contextPayload;
        }

        return [
            'channel'  => self::CHANNEL_WEB,
            'platform' => self::PLATFORM_WEB,
            $context => $items
        ];
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
     * @param string|null $version
     * @return array
     */
    private function buildInitiateCheckoutRequest(?string $version = null): array
    {
        $platformData = $this->platformInfoHelper->getMagentoDetails();

        return [
            'channel' => self::CHANNEL_WEB,
            'platform' => self::PLATFORM_WEB,
            'pluginVersion' => $version ?? $this->platformInfoHelper->getModuleVersion(),
            'plugin' => self::PLUGIN_ADOBE_COMMERCE,
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
    }

    /**
     * @throws ValidatorException
     */
    private function validateInitiateCheckoutAttemptResponse(array $response): void
    {
        if (!array_key_exists('checkoutAttemptId', $response)) {
            throw new ValidatorException(__('checkoutAttemptId is missing in the response!'));
        }

        if (empty($response['checkoutAttemptId'])) {
            throw new ValidatorException(__('checkoutAttemptId is empty in the response!'));
        }
    }

    /**
     * @throws ValidatorException
     */
    private function validateEventsAndContext(array $events, string $context): void
    {
        if (!in_array($context, array_keys(self::CONTEXT_MAX_ITEMS))) {
            throw new ValidatorException(__('The analytics context %1 is invalid!', $context));
        } elseif (count($events) > self::CONTEXT_MAX_ITEMS[$context]) {
            throw new ValidatorException(__(
                'There are too many events provided for %1 analytics context!',
                $context
            ));
        }
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
     * @throws AdyenException
     */
    private function sendRequest(string $endpoint, array $payload): ?array
    {
        $this->curl->addHeader('Content-Type', 'application/json');

        $this->curl->post($endpoint, json_encode($payload));
        $result = $this->curl->getBody();
        $httpStatus = $this->curl->getStatus();

        $hasFailed = !in_array($httpStatus, [200, 201, 202, 204]);

        if ($hasFailed && !empty($result)) {
            throw new AdyenException(__("Checkout Analytics API HTTP request failed (%1): %2", $httpStatus, $result));
        } elseif ($hasFailed && empty($result)) {
            throw new AdyenException(__("Checkout Analytics API HTTP request failed with responseCode: %1)", $httpStatus));
        }

        return json_decode($result, true);
    }
}
