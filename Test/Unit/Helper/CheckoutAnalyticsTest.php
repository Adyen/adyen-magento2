<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Api\Data\AnalyticsEventInterface;
use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * PHPUnit 10-compliant tests for CheckoutAnalytics helper
 */
class CheckoutAnalyticsTest extends AbstractAdyenTestCase
{
    protected CheckoutAnalytics $checkoutAnalytics;
    protected Config|MockObject $configHelperMock;
    protected PlatformInfo|MockObject $platformInfoMock;
    protected StoreManagerInterface|MockObject $storeManagerMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected ClientInterface|MockObject $httpClient;

    protected const STORE_ID = 1;
    protected const CLIENT_KEY = 'client_key_mock_XYZ1234567890';
    protected const LIVE_URL = 'https://checkoutanalytics.adyen.com/checkoutanalytics/v3/analytics';
    protected const TEST_URL = 'https://checkoutanalytics-test.adyen.com/checkoutanalytics/v3/analytics';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configHelperMock = $this->createMock(Config::class);
        $this->platformInfoMock = $this->createMock(PlatformInfo::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->httpClient = $this->createMock(ClientInterface::class);

        // Store
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(self::STORE_ID);
        $this->storeManagerMock->method('getStore')->willReturn($store);

        // Platform info
        $this->platformInfoMock->method('getMagentoDetails')->willReturn([
            'name'    => 'Adobe Commerce',
            'version' => '2.4.x',
        ]);
        $this->platformInfoMock->method('getModuleName')->willReturn('Adyen_Payment');
        $this->platformInfoMock->method('getModuleVersion')->willReturn('9.9.9');

        $this->checkoutAnalytics = new CheckoutAnalytics(
            $this->configHelperMock,
            $this->platformInfoMock,
            $this->storeManagerMock,
            $this->adyenLoggerMock,
            $this->httpClient
        );
    }

    /**
     * @return void
     * @throws AdyenException
     */
    public function testClientKeyNotSet(): void
    {
        $this->expectException(AdyenException::class);

        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(true);
        $this->configHelperMock->method('getClientKey')
            ->with('test', self::STORE_ID)
            ->willReturn(null);

        $this->checkoutAnalytics->initiateCheckoutAttempt();
    }

    /**
     * @return array
     */
    public static function initiateCheckoutAttemptDataProvider(): array
    {
        return [
            ['isDemoMode' => false],
            ['isDemoMode' => true]
        ];
    }

    /**
     * @dataProvider initiateCheckoutAttemptDataProvider
     *
     * @param bool $isDemoMode
     * @return void
     * @throws AdyenException
     */
    public function testInitiateCheckoutAttempt(bool $isDemoMode): void
    {
        $environment = $isDemoMode ? 'test' : 'live';

        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn($isDemoMode);
        $this->configHelperMock->method('getClientKey')
            ->with($environment, self::STORE_ID)
            ->willReturn(self::CLIENT_KEY);

        // Expect POST with the exact payload
        $expectedPayload = [
            'channel'       => 'Web',
            'platform'      => 'Web',
            'pluginVersion' => '9.9.9',
            'plugin'        => 'adobeCommerce',
            'applicationInfo' => [
                'merchantApplication' => [
                    'name'    => 'Adyen_Payment',
                    'version' => '9.9.9'
                ],
                'externalPlatform' => [
                    'name'       => 'Adobe Commerce',
                    'version'    => '2.4.x',
                    'integrator' => 'Adyen'
                ]
            ]
        ];

        $endpoint = $isDemoMode ? self::TEST_URL : self::LIVE_URL;
        $expectedUrl = sprintf('%s?clientKey=%s', $endpoint, self::CLIENT_KEY);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with($expectedUrl, json_encode($expectedPayload));

        // Response body
        $this->httpClient->method('getBody')->willReturn('{"checkoutAttemptId":"abc123"}');
        $this->httpClient->method('getStatus')->willReturn(200);

        $this->assertSame('abc123', $this->checkoutAnalytics->initiateCheckoutAttempt());
    }

    public static function failingHttpStatusDataProvider(): array
    {
        return [
            ['response' => 'Invalid request!'],
            ['response' => '']
        ];
    }

    /**
     * @dataProvider failingHttpStatusDataProvider
     *
     * @param string $response
     * @return void
     * @throws AdyenException
     */
    public function testFailingHttpStatus(string $response): void
    {
        $this->expectException(AdyenException::class);

        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(true);
        $this->configHelperMock->method('getClientKey')
            ->with('test', self::STORE_ID)
            ->willReturn(self::CLIENT_KEY);

        $this->httpClient->method('getBody')->willReturn($response);
        $this->httpClient->method('getStatus')->willReturn(400);

        $this->checkoutAnalytics->initiateCheckoutAttempt();
    }

    /**
     * @return void
     * @throws AdyenException
     */
    public function testInitiateCheckoutAttemptHandleException(): void
    {
        $this->expectException(AdyenException::class);

        $this->adyenLoggerMock->expects($this->once())->method('error');
        $this->platformInfoMock->method('getMagentoDetails')->willThrowException(new Exception());

        $this->checkoutAnalytics->initiateCheckoutAttempt();
    }

    /**
     * @return array[]
     */
    public static function validateInitiateCheckoutAttemptResponseDataProvider(): array
    {
        return [
            ['response' => '{"checkoutAttemptId":""}'],
            ['response' => '{"result":"Success"}']
        ];
    }

    /**
     * @dataProvider validateInitiateCheckoutAttemptResponseDataProvider
     *
     * @param string $response
     * @return void
     * @throws AdyenException
     */
    public function testValidateInitiateCheckoutAttemptResponse(string $response): void
    {
        $this->expectException(AdyenException::class);

        $this->httpClient->method('getBody')->willReturn($response);
        $this->httpClient->method('getStatus')->willReturn(200);

        $this->checkoutAnalytics->initiateCheckoutAttempt();
    }

    public static function validateEventsAndContextDataProvider(): array
    {
        return [
            ['context' => 'errors'],
            ['context' => 'logs'],
            ['context' => 'info']
        ];
    }

    /**
     * @dataProvider validateEventsAndContextDataProvider
     *
     * @param string $context
     * @return void
     */
    public function testValidateMaxNumberOfEvents(string $context): void
    {
        switch ($context) {
            case CheckoutAnalytics::CONTEXT_TYPE_ERRORS:
                $maxNumberOfEvents = CheckoutAnalytics::CONTEXT_MAX_ITEMS[CheckoutAnalytics::CONTEXT_TYPE_ERRORS];
                break;
            case CheckoutAnalytics::CONTEXT_TYPE_INFO:
                $maxNumberOfEvents = CheckoutAnalytics::CONTEXT_MAX_ITEMS[CheckoutAnalytics::CONTEXT_TYPE_INFO];
                break;
            case CheckoutAnalytics::CONTEXT_TYPE_LOGS:
                $maxNumberOfEvents = CheckoutAnalytics::CONTEXT_MAX_ITEMS[CheckoutAnalytics::CONTEXT_TYPE_LOGS];
                break;
        }

        $events = [];
        for ($i = 0; $i <= $maxNumberOfEvents; $i++) {
            $events[] = 'MOCK_EVENT';
        }

        $checkoutAttemptId = 'XYZ123456789ABC';

        $result = $this->checkoutAnalytics->sendAnalytics(
            $checkoutAttemptId,
            $events,
            $context
        );

        $this->assertArrayHasKey('error', $result);
    }

    /**
     * @return void
     */
    public function testValidateInvalidContext(): void
    {
        $events[] = 'MOCK_EVENT';
        $context = 'INVALID_CONTEXT';

        $checkoutAttemptId = 'XYZ123456789ABC';

        $result = $this->checkoutAnalytics->sendAnalytics(
            $checkoutAttemptId,
            $events,
            $context
        );

        $this->assertArrayHasKey('error', $result);
    }

    public static function buildSendAnalyticsRequestDataProvider(): array
    {
        return [
            ['context' => 'errors'],
            ['context' => 'logs'],
            ['context' => 'info']
        ];
    }

    /**
     * @dataProvider buildSendAnalyticsRequestDataProvider
     *
     * @param string $context
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testBuildSendAnalyticsRequestInfoContext(string $context): void
    {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(true);
        $this->configHelperMock->method('getClientKey')
            ->with('demo', self::STORE_ID)
            ->willReturn(self::CLIENT_KEY);

        $checkoutAttemptId = 'XYZ123456789ABC';

        $events[] = $this->createConfiguredMock(AnalyticsEventInterface::class, [
            'getCreatedAt' => '2025-01-01 01:00:00',
            'getTopic' => 'MOCK_TOPIC',
            'getUuid' => 'MOCK_UUID',
            'getType' => 'expectedStart',
            'getRelationId' => 'MOCK_TARGET',
            'getMessage' => 'MOCK_MESSAGE',
            'getErrorType' => 'MOCK_ERROR_TYPE',
            'getErrorCode' => 'MOCK_CODE',
        ]);

        $this->checkoutAnalytics->sendAnalytics($checkoutAttemptId, $events, $context);

        switch ($context) {
            case 'errors':
                $payload = [
                    'channel' => 'Web',
                    'platform' => 'Web',
                    'errors' => [
                        'timestamp' => '2025-01-01 01:00:00',
                        'component' => 'MOCK_TOPIC',
                        'id' => 'MOCK_UUID',
                        'message' => 'MOCK_MESSAGE',
                        'errorType' => 'MOCK_ERROR_TYPE',
                        'code' => 'MOCK_CODE'
                    ]
                ];
                break;
            case 'info':
                $payload = [
                    'channel' => 'Web',
                    'platform' => 'Web',
                    'info' => [
                        'timestamp' => '2025-01-01 01:00:00',
                        'component' => 'MOCK_TOPIC',
                        'id' => 'MOCK_UUID',
                        'type' => 'expectedStart',
                        'target' => 'MOCK_TARGET'
                    ]
                ];
                break;
            case 'logs':
                $payload = [
                    'channel' => 'Web',
                    'platform' => 'Web',
                    'logs' => [
                        'timestamp' => '2025-01-01 01:00:00',
                        'component' => 'MOCK_TOPIC',
                        'id' => 'MOCK_UUID',
                        'type' => 'expectedStart',
                        'message' => 'MOCK_MESSAGE'
                    ]
                ];
                break;
        }

        $expectedUrl = sprintf(
            '%s/%s?clientKey=%s',
            self::TEST_URL,
            $checkoutAttemptId,
            self::CLIENT_KEY
        );

        $this->httpClient->method('post')->with($expectedUrl, $payload);
    }

    /**
     * @return void
     */
    public function testClientKeyNotSetSendAnalyticsUrl(): void
    {
        $this->configHelperMock->method('isDemoMode')
            ->with(self::STORE_ID)
            ->willReturn(true);
        $this->configHelperMock->method('getClientKey')
            ->with('test', self::STORE_ID)
            ->willReturn(null);

        $events[] = $this->createConfiguredMock(AnalyticsEventInterface::class, [
            'getCreatedAt' => '2025-01-01 01:00:00',
            'getTopic' => 'MOCK_TOPIC',
            'getUuid' => 'MOCK_UUID',
            'getType' => 'expectedStart',
            'getRelationId' => 'MOCK_TARGET',
            'getMessage' => 'MOCK_MESSAGE',
            'getErrorType' => 'MOCK_ERROR_TYPE',
            'getErrorCode' => 'MOCK_CODE',
        ]);

        $result = $this->checkoutAnalytics->sendAnalytics(
            'XYZ123456789ABC',
            $events,
            'info'
        );

        $this->assertArrayHasKey('error', $result);
    }




}
