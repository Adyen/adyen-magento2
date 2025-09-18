<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\CheckoutAnalytics;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * PHPUnit 10-compliant tests for CheckoutAnalytics helper
 */
class CheckoutAnalyticsTest extends AbstractAdyenTestCase
{
    private Config $configHelperMock;
    private PlatformInfo $platformInfoMock;
    private StoreManagerInterface $storeManagerMock;
    private AdyenLogger $loggerMock;
    private ClientInterface $httpClient;

    private const STORE_ID   = 1;
    private const CLIENT_KEY = 'client_key_mock_XYZ1234567890';
    private const LIVE_URL   = 'https://checkoutanalytics.adyen.com//checkoutanalytics/v3/analytics';
    private const TEST_URL   = 'https://checkoutanalytics-test.adyen.com//checkoutanalytics/v3/analytics';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configHelperMock  = $this->createMock(Config::class);
        $this->platformInfoMock  = $this->createMock(PlatformInfo::class);
        $this->storeManagerMock  = $this->createMock(StoreManagerInterface::class);
        $this->loggerMock        = $this->createMock(AdyenLogger::class);
        $this->httpClient        = $this->createMock(ClientInterface::class);

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
    }

    private function makeSut(): CheckoutAnalytics
    {
        return new CheckoutAnalytics(
            $this->configHelperMock,
            $this->platformInfoMock,
            $this->storeManagerMock,
            $this->loggerMock,
            $this->httpClient
        );
    }

    public function testInitiateCheckoutAttempt_Live_SendsExpectedPayload_AndParsesResponse(): void
    {
        $this->configHelperMock->method('isDemoMode')->with(self::STORE_ID)->willReturn(false);
        $this->configHelperMock->method('getClientKey')->with('live', self::STORE_ID)->willReturn(self::CLIENT_KEY);

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

        $expectedUrl = sprintf('%s?clientKey=%s', self::LIVE_URL, self::CLIENT_KEY);

        $this->httpClient->expects($this->once())->method('post')
            ->with($expectedUrl, json_encode($expectedPayload));

        // Response body
        $this->httpClient->method('getBody')->willReturn('{"checkoutAttemptId":"abc123"}');

        $sut = $this->makeSut();
        $this->assertSame('abc123', $sut->initiateCheckoutAttempt());
    }

    public function testInitiateCheckoutAttempt_TestEnv_Works(): void
    {
        $this->configHelperMock->method('isDemoMode')->with(self::STORE_ID)->willReturn(true);
        $this->configHelperMock->method('getClientKey')->with('test', self::STORE_ID)->willReturn(self::CLIENT_KEY);

        $expectedUrl = sprintf('%s?clientKey=%s', self::TEST_URL, self::CLIENT_KEY);

        // We don't assert payload again here; the previous test covers it.
        $this->httpClient->expects($this->once())->method('post')
            ->with($expectedUrl, $this->anything());

        $this->httpClient->method('getBody')->willReturn('{"checkoutAttemptId":"test_env"}');

        $sut = $this->makeSut();
        $this->assertSame('test_env', $sut->initiateCheckoutAttempt());
    }

    public function testInitiateCheckoutAttempt_IncorrectResponse_LogsError_AndReturnsNull(): void
    {
        $this->configHelperMock->method('isDemoMode')->with(self::STORE_ID)->willReturn(false);
        $this->configHelperMock->method('getClientKey')->with('live', self::STORE_ID)->willReturn(self::CLIENT_KEY);

        $this->httpClient->method('getBody')->willReturn('{"someOtherKey":"x"}');

        $this->loggerMock->expects($this->once())->method('error');

        $sut = $this->makeSut();
        $this->assertNull($sut->initiateCheckoutAttempt());
    }

    public function testInitiateCheckoutAttempt_MissingClientKey_LogsError_AndReturnsNull(): void
    {
        $this->configHelperMock->method('isDemoMode')->with(self::STORE_ID)->willReturn(false);
        $this->configHelperMock->method('getClientKey')->with('live', self::STORE_ID)->willReturn(null);

        $this->loggerMock->expects($this->once())->method('error');

        $sut = $this->makeSut();
        $this->assertNull($sut->initiateCheckoutAttempt());
    }

    public function testSendAnalytics_BuildsPayloadWithCaps_AndPosts(): void
    {
        $this->configHelperMock->method('isDemoMode')->with(self::STORE_ID)->willReturn(false);
        $this->configHelperMock->method('getClientKey')->with('live', self::STORE_ID)->willReturn(self::CLIENT_KEY);

        $checkoutAttemptId = 'attempt_0123456789';
        $expectedUrl = sprintf('%s/%s?clientKey=%s', self::LIVE_URL, $checkoutAttemptId, self::CLIENT_KEY);

        // Build 55 info-capable events and 7 unexpectedEnd (errors-capable)
        $baseCreatedAt = new \DateTimeImmutable('@1700000000'); // 1700000000 seconds -> 1700000000000 ms
        $events = [];

        // 55 informational (various types) – should cap to 50 in 'info'
        $types = ['expectedStart', 'unexpectedStart', 'expectedEnd'];
        for ($i = 0; $i < 55; $i++) {
            $events[] = [
                'createdAt'  => $baseCreatedAt->modify("+{$i} seconds"),
                'uuid'       => "uuid-info-{$i}",
                'topic'      => "component-{$i}",
                'type'       => $types[$i % count($types)],
                'relationId' => "rel-{$i}",
            ];
        }

        // 7 unexpectedEnd (should cap to 5 in 'errors')
        for ($j = 0; $j < 7; $j++) {
            $events[] = [
                'createdAt'  => $baseCreatedAt->modify("+{$j} minutes"),
                'uuid'       => "uuid-err-{$j}",
                'topic'      => "component-err-{$j}",
                'type'       => 'unexpectedEnd',
                'relationId' => "rel-err-{$j}",
            ];
        }

        // One malformed event (missing relationId) -> must be skipped
        $events[] = [
            'createdAt' => $baseCreatedAt,
            'uuid'      => 'uuid-bad',
            'topic'     => 'component-bad',
            'type'      => 'expectedStart',
            // 'relationId' missing
        ];

        // We’ll capture the actual JSON body passed to the HTTP client to assert caps & mapping.
        $this->httpClient->expects($this->once())->method('post')
            ->with(
                $expectedUrl,
                $this->callback(function (string $json) use ($baseCreatedAt) {
                    $payload = json_decode($json, true);

                    // Basic required fields
                    if (($payload['channel'] ?? null) !== 'Web') return false;
                    if (($payload['platform'] ?? null) !== 'Web') return false;

                    // Caps
                    if (!isset($payload['info']) || count($payload['info']) !== 50) return false;
                    if (!isset($payload['errors']) || count($payload['errors']) !== 5) return false;

                    // Spot-check first info item mapping
                    $first = $payload['info'][0];
                    // createdAt base is 1700000000 -> ms string
                    if ($first['timestamp'] !== (string)(1700000000 * 1000)) return false;
                    if (!isset($first['type'])) return false;
                    if (!isset($first['target'])) return false;
                    if (!isset($first['id'])) return false;
                    if (!isset($first['component'])) return false;

                    // Spot-check an errors item mapping (must have errorType Plugin, no type/target)
                    $err = $payload['errors'][0];
                    if (($err['errorType'] ?? null) !== 'Plugin') return false;
                    if (isset($err['type']) || isset($err['target'])) return false;

                    return true;
                })
            );

        // Response body irrelevant for send; just make it non-empty to avoid null
        $this->httpClient->method('getBody')->willReturn('{"ok":true}');

        $sut = $this->makeSut();
        $sut->sendAnalytics($checkoutAttemptId, $events);
    }

    public function testSendAnalytics_MissingClientKey_LogsError(): void
    {
        $this->configHelperMock->method('isDemoMode')->with(self::STORE_ID)->willReturn(false);
        $this->configHelperMock->method('getClientKey')->with('live', self::STORE_ID)->willReturn(null);

        $this->loggerMock->expects($this->once())->method('error');

        $sut = $this->makeSut();
        $sut->sendAnalytics('attempt_X', [
            [
                'createdAt'  => new \DateTimeImmutable('@1700000000'),
                'uuid'       => 'uuid-1',
                'topic'      => 'component-1',
                'type'       => 'expectedStart',
                'relationId' => 'rel-1',
            ]
        ]);
    }

    public function testSendAnalytics_EmptyEvents_LogsError(): void
    {
        $this->configHelperMock->method('isDemoMode')->with(self::STORE_ID)->willReturn(false);

        $this->loggerMock->expects($this->once())->method('error');

        $sut = $this->makeSut();
        $sut->sendAnalytics('attempt_X', []); // should trigger InvalidArgumentException and be logged
    }

    public function testSendAnalytics_SkipsMalformedEvents_ButStillSendsIfAnyValid(): void
    {
        $this->configHelperMock->method('isDemoMode')->with(self::STORE_ID)->willReturn(false);
        $this->configHelperMock->method('getClientKey')->with('live', self::STORE_ID)->willReturn(self::CLIENT_KEY);

        $checkoutAttemptId = 'attempt_valid_partial';
        $expectedUrl = sprintf('%s/%s?clientKey=%s', self::LIVE_URL, $checkoutAttemptId, self::CLIENT_KEY);

        $events = [
            // malformed (missing uuid)
            [
                'createdAt'  => new \DateTimeImmutable('@1700000000'),
                'topic'      => 'component-x',
                'type'       => 'expectedStart',
                'relationId' => 'rel-x',
            ],
            // valid
            [
                'createdAt'  => new \DateTimeImmutable('@1700000001'),
                'uuid'       => 'uuid-ok',
                'topic'      => 'component-ok',
                'type'       => 'expectedEnd',
                'relationId' => 'rel-ok',
            ],
        ];

        $this->httpClient->expects($this->once())->method('post')
            ->with(
                $expectedUrl,
                $this->callback(function (string $json) {
                    $payload = json_decode($json, true);
                    return isset($payload['info']) && count($payload['info']) === 1
                        && !isset($payload['errors']); // expectedEnd -> not an error
                })
            );

        $this->httpClient->method('getBody')->willReturn('{"ok":true}');

        $sut = $this->makeSut();
        $sut->sendAnalytics($checkoutAttemptId, $events);
    }
}
