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
use Magento\Framework\UrlInterface;
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
    const EXTRA_PARAMS_INIT_ENDPOINT = [
        'version',
        'channel',
        'platform',
        'component',
        'deviceModel',
        'deviceBrand',
        'systemVersion'
    ];
    const MESSAGE_PARAMS = [
        'errors',
        'info',
        'logs'
    ];

    /**
     * @param Config $configHelper
     * @param Data $adyenHelper
     * @param StoreManagerInterface $storeManager
     * @param AdyenLogger $adyenLogger
     * @param Locale $locale
     * @param UrlInterface $urlHelper
     * @param ClientInterface $curl
     */
    public function __construct(
        private readonly Config $configHelper,
        private readonly Data $adyenHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdyenLogger $adyenLogger,
        private readonly Locale $locale,
        private readonly UrlInterface $urlHelper,
        private readonly ClientInterface $curl
    ) { }

    /**
     * Makes the initial API call to CheckoutAnalytics to obtain checkoutAttemptId
     *
     * @param array $extraParams
     * @return string|null
     */
    public function initiateCheckoutAttempt(array $extraParams = []): ?string
    {
        try {
            $request = $this->buildInitiateCheckoutRequest($extraParams);
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
     * @param array $message Contains `info`, `log` and `errors` objects for payload
     * @param string|null $channel
     * @param string|null $platform
     * @return void
     */
    public function sendAnalytics(
        string $checkoutAttemptId,
        array $message,
        string $channel = null,
        string $platform = null
    ): void {
        try {
            $request = $this->buildSendAnalyticsRequest($message, $channel, $platform);
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
     * @param array $message Contains `info`, `log` and `errors` objects for payload
     * @param string|null $channel
     * @param string|null $platform
     * @return array
     * @throws InvalidArgumentException
     */
    private function buildSendAnalyticsRequest(
        array $message,
        string $channel = null,
        string $platform = null
    ): array {
        if (empty($message)) {
            throw new InvalidArgumentException(__('Message can not be empty!'));
        }

        $request = [
            'channel' => $channel ?? 'Web',
            'platform' => $platform ?? 'Web'
        ];

        $isMessageParamAdded = false;

        foreach (self::MESSAGE_PARAMS as $key) {
            if (isset($message[$key])) {
                $request[$key] = $message[$key];
                $isMessageParamAdded = true;
            }
        }

        if (!$isMessageParamAdded) {
            throw new InvalidArgumentException(__('Message does not contain required fields!'));
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
     * For extra fields, see constant EXTRA_PARAMS_INIT_ENDPOINT.
     *
     * @param array $extraParams
     * @return array
     * @throws NoSuchEntityException
     */
    private function buildInitiateCheckoutRequest(array $extraParams = []): array
    {
        $storeId = $this->storeManager->getStore()->getId();
        $platformData = $this->adyenHelper->getMagentoDetails();
        $storeLocale = $this->adyenHelper->getStoreLocale($storeId);
        $mappedLocale = $this->locale->mapLocaleCode($storeLocale);
        $url = $this->urlHelper->getCurrentUrl();

        $request = [
            'locale' => $mappedLocale,
            'flavor' => self::FLAVOR_COMPONENT,
            'referrer' => $url,
            'applicationInfo' => [
                'merchantApplication' => [
                    'name' => $this->adyenHelper->getModuleName(),
                    'version' => $this->adyenHelper->getModuleVersion()
                ],
                'externalPlatform' => [
                    'name' => $platformData['name'],
                    'version' => $platformData['version'],
                    'integrator' => self::INTEGRATOR_ADYEN
                ]
            ]
        ];

        foreach (self::EXTRA_PARAMS_INIT_ENDPOINT as $key) {
            if (array_key_exists($key, $extraParams)) {
                $request[$key] = $extraParams[$key];
            }
        }

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
}
