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

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutAnalyticsTest extends AbstractAdyenTestCase
{
    private $configHelperMock;
    private $adyenHelper;
    private $storeManagerMock;
    private $loggerMock;
    private $localeMock;
    private $urlHelperMock;
    private $httpClient;

    const STORE_ID = 1;
    const STORE_LOCALE = 'nl_NL';
    const CLIENT_KEY = 'client_key_mock_XYZ1234567890';

    protected function setUp(): void
    {
        $this->configHelperMock = $this->createMock(Config::class);
        $this->adyenHelper = $this->createMock(Data::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->loggerMock = $this->createMock(AdyenLogger::class);
        $this->localeMock = $this->createPartialMock(Locale::class, []);
        $this->urlHelperMock = $this->createMock(UrlInterface::class);
        $this->httpClient = $this->createMock(ClientInterface::class);

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(self::STORE_ID);

        $this->storeManagerMock->method('getStore')->willReturn($storeMock);
        $this->adyenHelper->method('getStoreLocale')
            ->with(self::STORE_ID)
            ->willReturn(self::STORE_LOCALE);
        $this->adyenHelper->method('getMagentoDetails')->willReturn([
            'name' => 'Adobe Commerce',
            'version' => '2.x.x'
        ]);
    }

    protected function generateClass(): CheckoutAnalytics
    {
        return new CheckoutAnalytics(
            $this->configHelperMock,
            $this->adyenHelper,
            $this->storeManagerMock,
            $this->loggerMock,
            $this->localeMock,
            $this->urlHelperMock,
            $this->httpClient
        );
    }

    public function testSuccessfulInitiateCheckoutAttemptWithoutExtraParams() {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $this->configHelperMock->method('getClientKey')
            ->with('live', self::STORE_ID)
            ->willReturn(self::CLIENT_KEY);

        $expectedResponse = '{"checkoutAttemptId":"test_response"}';
        $this->httpClient->method('getBody')->willReturn($expectedResponse);

        $checkoutAnalytics = $this->generateClass();

        $this->assertEquals('test_response', $checkoutAnalytics->initiateCheckoutAttempt());
    }

    public function testSuccessfulInitiateCheckoutAttemptWithExtraParams() {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(true);

        $this->configHelperMock->method('getClientKey')
            ->with('test', self::STORE_ID)
            ->willReturn(self::CLIENT_KEY);

        $expectedResponse = '{"checkoutAttemptId":"test_response"}';
        $this->httpClient->method('getBody')->willReturn($expectedResponse);

        $checkoutAnalytics = $this->generateClass();

        $extraParams = [
            'version' => '1.0.0',
            'channel' => 'Web',
            'platform' => 'Web',
            'component' => 'plugin',
            'deviceModel' => 'testDeviceModel',
            'deviceBrand' => 'testBrand',
            'systemVersion' => '1.0.0'
        ];

        $this->assertEquals('test_response', $checkoutAnalytics->initiateCheckoutAttempt($extraParams));
    }

    public function testInitiateCheckoutAttemptIncorrectResponse() {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $this->configHelperMock->method('getClientKey')
            ->with('live', self::STORE_ID)
            ->willReturn(self::CLIENT_KEY);

        $expectedResponse = '{"someOtherKey":"test_response"}';
        $this->httpClient->method('getBody')->willReturn($expectedResponse);

        $this->loggerMock->expects($this->once())->method('error');

        $checkoutAnalytics = $this->generateClass();
        $checkoutAnalytics->initiateCheckoutAttempt();
    }

    public function testInitiateCheckoutAttemptMissingClientKey() {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $this->loggerMock->expects($this->once())->method('error');

        $checkoutAnalytics = $this->generateClass();
        $result = $checkoutAnalytics->initiateCheckoutAttempt();

        $this->assertNull($result);
    }

    public function testSuccessfulSendAnalytics() {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $this->configHelperMock->method('getClientKey')
            ->with('live', self::STORE_ID)
            ->willReturn(self::CLIENT_KEY);

        $checkoutAttemptId = 'attempt_0123456789';
        $message = [
            'errors' => [
                'key' => 'value'
            ]
        ];
        $expectedRequest = [
            'channel' => 'Web',
            'platform' => 'Web',
            'errors' => [
                'key' => 'value'
            ]
        ];
        $expectedUrl = sprintf(
            "%s/%s?clientKey=%s",
            'https://checkoutanalytics.adyen.com//checkoutanalytics/v3/analytics',
            $checkoutAttemptId,
            self::CLIENT_KEY
        );

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with($expectedUrl, json_encode($expectedRequest));

        $checkoutAnalytics = $this->generateClass();
        $checkoutAnalytics->sendAnalytics($checkoutAttemptId, $message, 'Web', 'Web');
    }

    public function testSendAnalyticsWithMissingClientKey() {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $checkoutAttemptId = 'attempt_0123456789';
        $message = [
            'errors' => [
                'key' => 'value'
            ]
        ];

        $this->loggerMock->expects($this->once())->method('error');

        $checkoutAnalytics = $this->generateClass();
        $checkoutAnalytics->sendAnalytics($checkoutAttemptId, $message, 'Web', 'Web');
    }

    public function testSendAnalyticsWithEmptyMessageParams() {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $checkoutAttemptId = 'attempt_0123456789';
        $message = [];

        $this->loggerMock->expects($this->once())->method('error');

        $checkoutAnalytics = $this->generateClass();
        $checkoutAnalytics->sendAnalytics($checkoutAttemptId, $message, 'Web', 'Web');
    }

    public function testSendAnalyticsWithIncorrectMessageParams() {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(false);

        $checkoutAttemptId = 'attempt_0123456789';
        $message = [
            'wrongKey' => [
                'key' => 'value'
            ]
        ];

        $this->loggerMock->expects($this->once())->method('error');

        $checkoutAnalytics = $this->generateClass();
        $checkoutAnalytics->sendAnalytics($checkoutAttemptId, $message, 'Web', 'Web');
    }
}
