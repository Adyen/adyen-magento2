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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Method\Adapter;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;
use Adyen\Payment\Model\Method\TxVariantFactory;

class VaultTest extends AbstractAdyenTestCase
{
    private Vault $vault;
    private AdyenLogger $adyenLogger;
    private PaymentTokenManagement $paymentTokenManagement;
    private PaymentTokenFactoryInterface $paymentTokenFactory;
    private PaymentTokenRepositoryInterface $paymentTokenRepository;
    private Config $config;
    private PaymentMethods $paymentMethodsHelper;
    private StateData $stateData;
    private PaymentTokenResourceModel $paymentTokenResourceModelMock;
    private OrderPaymentExtensionInterfaceFactory $orderPaymentExtensionInterfaceFactoryMock;
    private TxVariantFactory $txVariantFactory;
    private Data $dataHelper;

    protected function setUp(): void
    {
        $this->stateData = $this->createPartialMock(StateData::class, [
            'getStateData',
            'getStoredPaymentMethodIdFromStateData'
        ]);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->paymentTokenManagement = $this->createMock(PaymentTokenManagement::class);
        $this->paymentTokenFactory = $this->createMock(PaymentTokenFactoryInterface::class);
        $this->paymentTokenRepository = $this->createMock(PaymentTokenRepositoryInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->paymentMethodsHelper = $this->createMock(PaymentMethods::class);
        $this->paymentTokenResourceModelMock = $this->createMock(PaymentTokenResourceModel::class);
        $this->orderPaymentExtensionInterfaceFactoryMock =
            $this->createMock(OrderPaymentExtensionInterfaceFactory::class);
        $this->txVariantFactory = $this->createMock(TxVariantFactory::class);
        $this->dataHelper = $this->createMock(Data::class);

        $this->vault = new Vault(
            $this->adyenLogger,
            $this->paymentTokenManagement,
            $this->paymentTokenFactory,
            $this->paymentTokenRepository,
            $this->paymentTokenResourceModelMock,
            $this->orderPaymentExtensionInterfaceFactoryMock,
            $this->config,
            $this->paymentMethodsHelper,
            $this->stateData,
            $this->txVariantFactory,
            $this->dataHelper
        );
    }

    /**
     * @dataProvider buildPaymentMethodRecurringDataDataProvider
     */
    public function testBuildPaymentMethodRecurringData(
        $storedPaymentMethodId,
        $recurringProcessingModel,
        $storePaymentMethod
    ) {
        $storeId = 1;

        $paymentMock = $this->createConfiguredMock(Order\Payment::class, [
            'getMethodInstance' => $this->createConfiguredMock(Adapter::class, [
                'getCode' => 'adyen_klarna'
            ]),
            'getOrder' => $this->createConfiguredMock(Order::class, [
                'getQuoteId' => 1
            ])
        ]);

        $recurringConfigJson = "{\"adyen_klarna\":{\"enabled\":true,\"recurringProcessingModel\":\"$recurringProcessingModel\"}}";
        $this->config->method('getConfigData')->with(
            Config::XML_RECURRING_CONFIGURATION,
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            false
        )->willReturn($recurringConfigJson);

        $this->stateData->method('getStoredPaymentMethodIdFromStateData')->willReturn($storedPaymentMethodId);
        $request = $this->vault->buildPaymentMethodRecurringData($paymentMock, $storeId);

        if ($storePaymentMethod) {
            $this->assertArrayHasKey('storePaymentMethod', $request);
        }
        $this->assertEquals($recurringProcessingModel, $request['recurringProcessingModel']);
    }

    public static function buildPaymentMethodRecurringDataDataProvider(): array
    {
        return [
            [
                'storedPaymentMethodId' => hash('md5', time()),
                'recurringProcessingModel' => 'CardOnFile',
                'storePaymentMethod' => false
            ],
            [
                'storedPaymentMethodId' => null,
                'recurringProcessingModel' => 'CardOnFile',
                'storePaymentMethod' => true
            ]
        ];
    }

    public function testGetRecurringTypesAndValidation(): void
    {
        $expected = ['CardOnFile', 'Subscription', 'UnscheduledCardOnFile'];
        $this->assertSame($expected, Vault::getRecurringTypes());

        $this->assertTrue($this->vault->validateRecurringProcessingModel('CardOnFile'));
        $this->assertTrue($this->vault->validateRecurringProcessingModel('Subscription'));
        $this->assertTrue($this->vault->validateRecurringProcessingModel('UnscheduledCardOnFile'));
        $this->assertFalse($this->vault->validateRecurringProcessingModel('SomethingElse'));
    }

    public function testGetPaymentMethodRecurringActiveEnabled(): void
    {
        $storeId = 1;
        $json = '{"adyen_klarna":{"enabled":true}}';
        $this->config->method('getConfigData')->willReturn($json);

        $this->assertTrue($this->vault->getPaymentMethodRecurringActive('adyen_klarna', $storeId));
    }

    public function testGetPaymentMethodRecurringActiveDisabled(): void
    {
        $storeId = 1;
        $json = '{"adyen_klarna":{"enabled":false}}';
        $this->config->method('getConfigData')->willReturn($json);

        $this->assertFalse($this->vault->getPaymentMethodRecurringActive('adyen_klarna', $storeId));
    }

    public function testGetPaymentMethodRecurringActiveNoConfig(): void
    {
        $storeId = 1;
        $this->config->method('getConfigData')->willReturn(null);

        $this->assertFalse($this->vault->getPaymentMethodRecurringActive('adyen_klarna', $storeId));
    }

    public function testGetPaymentMethodRecurringProcessingModel(): void
    {
        $storeId = 1;
        $json = '{"adyen_klarna":{"enabled":true,"recurringProcessingModel":"CardOnFile"}}';
        $this->config->method('getConfigData')->willReturn($json);

        $this->assertSame('CardOnFile', $this->vault->getPaymentMethodRecurringProcessingModel('adyen_klarna', $storeId));
    }

    public function testGetPaymentMethodRecurringProcessingModelNullWhenMissing(): void
    {
        $storeId = 1;
        $json = '{"adyen_klarna":{"enabled":true}}';
        $this->config->method('getConfigData')->willReturn($json);

        $this->assertNull($this->vault->getPaymentMethodRecurringProcessingModel('adyen_klarna', $storeId));
    }

    public function testGetPaymentMethodRecurringProcessingModelNullWhenNoConfig(): void
    {
        $storeId = 1;
        $this->config->method('getConfigData')->willReturn(null);

        $this->assertNull($this->vault->getPaymentMethodRecurringProcessingModel('adyen_klarna', $storeId));
    }

    public function testHasRecurringDetailReferenceTrue(): void
    {
        $response = ['additionalData' => [Vault::RECURRING_DETAIL_REFERENCE => 'Ref123']];
        $this->assertTrue($this->vault->hasRecurringDetailReference($response));
    }

    public function testHasRecurringDetailReferenceFalse(): void
    {
        $this->assertFalse($this->vault->hasRecurringDetailReference([]));
        $this->assertFalse($this->vault->hasRecurringDetailReference(['additionalData' => []]));
    }

    public function testGetAdyenTokenType(): void
    {
        $token = $this->createConfiguredMock(\Magento\Vault\Api\Data\PaymentTokenInterface::class, [
            'getTokenDetails' => json_encode(['tokenType' => 'CardOnFile'])
        ]);
        $this->assertSame('CardOnFile', $this->vault->getAdyenTokenType($token));
    }

    public function testGetAdyenTokenTypeNullWhenMissing(): void
    {
        $token = $this->createConfiguredMock(\Magento\Vault\Api\Data\PaymentTokenInterface::class, [
            'getTokenDetails' => '{}'
        ]);
        $this->assertNull($this->vault->getAdyenTokenType($token));
    }

    public function testIsAdyenPaymentCode(): void
    {
        $this->assertTrue($this->vault->isAdyenPaymentCode('adyen_cc'));
        $this->assertFalse($this->vault->isAdyenPaymentCode('checkmo'));
    }

    public function testGetExtensionAttributesReturnsExisting(): void
    {
        $ext = $this->createMock(\Magento\Sales\Api\Data\OrderPaymentExtensionInterface::class);

        $payment = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExtensionAttributes'])
            ->getMock();

        $payment->method('getExtensionAttributes')->willReturn($ext);

        $this->assertSame($ext, $this->vault->getExtensionAttributes($payment));
    }

    public function testGetExtensionAttributesCreatesWhenMissing(): void
    {
        $created = $this->createMock(\Magento\Sales\Api\Data\OrderPaymentExtensionInterface::class);

        $payment = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExtensionAttributes','setExtensionAttributes'])
            ->getMock();

        $payment->method('getExtensionAttributes')->willReturn(null);
        $payment->expects($this->once())->method('setExtensionAttributes')->with($created);

        $this->orderPaymentExtensionInterfaceFactoryMock->method('create')->willReturn($created);

        $this->assertSame($created, $this->vault->getExtensionAttributes($payment));
    }

    public function testCreateVaultTokenForCard(): void
    {
        $order = $this->createConfiguredMock(Order::class, ['getCustomerId' => 10, 'getStoreId' => 1]);

        $payment = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder','getMethodInstance','getAdditionalInformation'])
            ->getMock();

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethodInstance')->willReturn(
            $this->createConfiguredMock(Adapter::class, ['getCode' => PaymentMethods::ADYEN_CC])
        );
        $payment->method('getAdditionalInformation')->willReturnCallback(function ($key) {
            $map = [
                'additionalData' => [
                    'paymentMethod' => 'visa',
                    'cardSummary' => '1111',
                    'expiryDate' => '08/2030'
                ],
                'recurringProcessingModel' => 'CardOnFile'
            ];
            return $map[$key] ?? null;
        });

        $this->paymentTokenManagement->method('getByGatewayToken')->willReturn(null);

        $tokenMock = $this->createMock(\Magento\Vault\Api\Data\PaymentTokenInterface::class);
        $tokenMock->expects($this->once())->method('setGatewayToken')->with('detailRef');
        $tokenMock->expects($this->once())->method('setType')->with(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $tokenMock->expects($this->once())->method('setExpiresAt');
        $tokenMock->expects($this->once())->method('setTokenDetails')
            ->with($this->callback(fn($json) =>
                str_contains($json, '"maskedCC":"1111"') &&
                str_contains($json, '"type":"visa"') &&
                str_contains($json, '"tokenType":"CardOnFile"')
            ));

        $this->paymentTokenFactory->method('create')->willReturn($tokenMock);
        $this->paymentMethodsHelper->method('isWalletPaymentMethod')->willReturn(false);
        $this->paymentMethodsHelper->method('isAlternativePaymentMethod')->willReturn(false);

        $token = $this->vault->createVaultToken($payment, 'detailRef', 'Jane Doe');
        $this->assertSame($tokenMock, $token);
    }

    public function testCreateVaultTokenForWallet(): void
    {
        $order = $this->createConfiguredMock(Order::class, ['getCustomerId' => 11, 'getStoreId' => 1]);
        $adapter = $this->createConfiguredMock(Adapter::class, ['getCode' => 'adyen_googlepay']);

        $payment = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder','getMethodInstance','getCcType','getAdditionalInformation'])
            ->getMock();

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethodInstance')->willReturn($adapter);

        // underscore so TxVariantInterpreter extracts card part ("mc")
        $payment->method('getCcType')->willReturn('mc_googlepay');

        $payment->method('getAdditionalInformation')->willReturnCallback(function ($key) {
            $map = [
                'additionalData' => ['cardSummary' => '9999', 'expiryDate' => '12/2031'],
                'recurringProcessingModel' => 'UnscheduledCardOnFile'
            ];
            return $map[$key] ?? null;
        });

        $this->paymentTokenManagement->method('getByGatewayToken')->willReturn(null);

        $tokenMock = $this->createMock(PaymentTokenInterface::class);
        $tokenMock->expects($this->once())->method('setType')
            ->with(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $tokenMock->expects($this->once())->method('setExpiresAt');

        $tokenMock->expects($this->once())->method('setTokenDetails')
            ->with($this->callback(function ($json) {
                return str_contains($json, '"type":"mc"')
                    && str_contains($json, '"walletType":"googlepay"')
                    && str_contains($json, '"maskedCC":"9999"')
                    && str_contains($json, '"tokenType":"UnscheduledCardOnFile"');
            }));

        $tokenMock->expects($this->once())->method('setGatewayToken')->with('storedRef');
        $this->paymentTokenFactory->method('create')->willReturn($tokenMock);

        // Wallet branch
        $this->paymentMethodsHelper->method('isWalletPaymentMethod')->with($adapter)->willReturn(true);

        $this->paymentMethodsHelper->expects($this->once())
            ->method('getAlternativePaymentMethodTxVariant')
            ->with($adapter)
            ->willReturn('googlepay');

        // Factory returns a TxVariantInterpreter mock
        $validatedVariant = $this->createMock(\Adyen\Payment\Model\Method\TxVariant::class);
        $validatedVariant->expects($this->once())->method('getCard')->willReturn('mc');

        $this->txVariantFactory->expects($this->once())
            ->method('create')
            ->with(['txVariant' => 'mc_googlepay'])
            ->willReturn($validatedVariant);

        $this->vault->createVaultToken($payment, 'storedRef');
    }

    public function testCreateVaultTokenForAlternativePaymentMethod(): void
    {
        $order = $this->createConfiguredMock(Order::class, ['getCustomerId' => 12, 'getStoreId' => 1]);
        $adapter = $this->createConfiguredMock(Adapter::class, ['getCode' => 'adyen_klarna', 'getTitle' => 'Klarna']);
        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getOrder' => $order,
            'getMethodInstance' => $adapter,
            'getAdditionalInformation' => function($key) {
                $map = [
                    'additionalData' => [],
                    'recurringProcessingModel' => 'Subscription'
                ];
                return $map[$key] ?? null;
            }
        ]);

        $this->paymentTokenManagement->method('getByGatewayToken')->willReturn(null);

        $tokenMock = $this->createMock(\Magento\Vault\Api\Data\PaymentTokenInterface::class);
        $tokenMock->expects($this->once())->method('setType')->with(PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT);
        $tokenMock->expects($this->once())->method('setExpiresAt');
        $tokenMock->expects($this->once())->method('setTokenDetails')
            ->with($this->callback(fn($json) =>
                str_contains($json, '"type":"klarna"') || str_contains($json, '"type":"klarna_payments"')
            ));
        $tokenMock->expects($this->once())->method('setGatewayToken')->with('apmRef');

        $this->paymentTokenFactory->method('create')->willReturn($tokenMock);
        $this->paymentMethodsHelper->method('isWalletPaymentMethod')->willReturn(false);
        $this->paymentMethodsHelper->method('isAlternativePaymentMethod')->willReturn(true);
        $this->paymentMethodsHelper->method('getAlternativePaymentMethodTxVariant')->willReturn('klarna');

        $this->vault->createVaultToken($payment, 'apmRef');
        $this->assertTrue(true);
    }

    public function testCreateVaultTokenUpdatesExistingToken(): void
    {
        $order = $this->createConfiguredMock(Order::class, ['getCustomerId' => 99, 'getStoreId' => 1]);
        $adapter = $this->createConfiguredMock(Adapter::class, ['getCode' => PaymentMethods::ADYEN_CC]);

        $payment = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder','getMethodInstance','getAdditionalInformation'])
            ->getMock();

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethodInstance')->willReturn($adapter);
        $payment->method('getAdditionalInformation')->willReturnCallback(function ($key) {
            $map = [
                'additionalData' => ['paymentMethod' => 'visa', 'cardSummary' => '4444', 'expiryDate' => '01/2032'],
                'recurringProcessingModel' => 'CardOnFile'
            ];
            return $map[$key] ?? null;
        });

        $existing = $this->createMock(\Magento\Vault\Api\Data\PaymentTokenInterface::class);
        $existing->expects($this->once())->method('setType');
        $existing->expects($this->once())->method('setExpiresAt');
        $existing->expects($this->once())->method('setTokenDetails');

        $this->paymentTokenManagement->method('getByGatewayToken')->willReturn($existing);
        $this->paymentTokenRepository->expects($this->once())->method('save')->with($existing);

        $this->vault->createVaultToken($payment, 'sameRef');
    }

    public function testHandlePaymentResponseRecurringDetailsSetsExtensionToken(): void
    {
        $adapter = $this->createConfiguredMock(Adapter::class, ['getCode' => PaymentMethods::ADYEN_CC, 'getStore' => 1]);
        $order = $this->createConfiguredMock(Order::class, [
            'getIncrementId' => '100000001', 'getCustomerId' => 1, 'getStoreId' => 1
        ]);

        $payment = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethodInstance','getOrder','getExtensionAttributes','setExtensionAttributes','getAdditionalInformation'])
            ->getMock();

        $payment->method('getMethodInstance')->willReturn($adapter);
        $payment->method('getOrder')->willReturn($order);

        $payment->method('getAdditionalInformation')->willReturnCallback(function ($key) {
            $map = [
                'additionalData' => [
                    'paymentMethod' => 'visa',
                    'cardSummary'   => '1111',
                    'expiryDate'    => '08/2030',
                ],
                'recurringProcessingModel' => 'CardOnFile',
            ];
            return $map[$key] ?? null;
        });

        $ext = $this->createMock(OrderPaymentExtensionInterface::class);
        $payment->method('getExtensionAttributes')->willReturn($ext);

        $this->config->method('getConfigData')->willReturn('{"adyen_cc":{"enabled":true}}');

        $token = $this->createMock(PaymentTokenInterface::class);
        $this->paymentTokenManagement->method('getByGatewayToken')->willReturn(null);
        $this->paymentTokenFactory->method('create')->willReturn($token);

        $ext->expects($this->once())->method('setVaultPaymentToken')->with($token);

        $this->paymentMethodsHelper->method('isWalletPaymentMethod')->willReturn(false);
        $this->paymentMethodsHelper->method('isAlternativePaymentMethod')->willReturn(false);

        $response = ['additionalData' => [
            Vault::RECURRING_DETAIL_REFERENCE => 'ref123',
            'paymentMethod' => 'visa',
            'cardSummary'   => '1111',
            'expiryDate'    => '08/2030',
            'cardHolderName'=> 'Jane Doe'
        ]];

        $this->vault->handlePaymentResponseRecurringDetails($payment, $response);
    }

    public function testHandlePaymentResponseRecurringDetailsLogsErrorOnException(): void
    {
        $adapter = $this->createConfiguredMock(Adapter::class, ['getCode' => PaymentMethods::ADYEN_CC, 'getStore' => 1]);
        $order = $this->createConfiguredMock(Order::class, ['getIncrementId' => '100000002', 'getCustomerId' => 1, 'getStoreId' => 1]);

        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getMethodInstance' => $adapter,
            'getOrder' => $order
        ]);

        $this->config->method('getConfigData')->willReturn('{"adyen_cc":{"enabled":true}}');

        $this->paymentTokenManagement->method('getByGatewayToken')->willReturn(null);
        $this->paymentTokenFactory->method('create')->willThrowException(new \RuntimeException('boom'));

        $this->adyenLogger->expects($this->once())->method('error')
            ->with($this->stringContains('Failure trying to save payment token in vault for order 100000002'));

        $this->vault->handlePaymentResponseRecurringDetails($payment, [
            'additionalData' => [
                Vault::RECURRING_DETAIL_REFERENCE => 'refX',
                'paymentMethod' => 'visa',
                'cardSummary' => '2222',
                'expiryDate' => '01/2031'
            ]
        ]);
    }

    public function testHandlePaymentResponseRecurringDetailsNoopWhenRecurringDisabled(): void
    {
        $adapter = $this->createConfiguredMock(Adapter::class, ['getCode' => PaymentMethods::ADYEN_CC, 'getStore' => 1]);
        $order = $this->createConfiguredMock(Order::class, ['getIncrementId' => '100000003']);

        $payment = $this->createConfiguredMock(Order\Payment::class, [
            'getMethodInstance' => $adapter,
            'getOrder' => $order
        ]);

        $this->config->method('getConfigData')->willReturn('{"adyen_cc":{"enabled":false}}');

        $this->vault->handlePaymentResponseRecurringDetails($payment, ['additionalData' => [/* no token */]]);
        $this->assertTrue(true);
    }

    public function testGetVaultTokenByStoredPaymentMethodIdReturnsToken(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->paymentTokenResourceModelMock->method('getConnection')->willReturn($connection);
        $this->paymentTokenResourceModelMock->method('getMainTable')->willReturn('vault_payment_token');

        $selectMock = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()->getMock();
        $connection->method('select')->willReturn($selectMock);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();

        $row = ['entity_id' => 1, 'gateway_token' => 'storedRef', 'details' => '{}'];
        $connection->method('fetchRow')->willReturn($row);

        $tokenMock = $this->createMock(PaymentTokenInterface::class);
        $this->paymentTokenFactory->method('create')->with(['data' => $row])->willReturn($tokenMock);

        $this->assertSame($tokenMock, $this->vault->getVaultTokenByStoredPaymentMethodId('storedRef'));
    }

    public function testGetVaultTokenByStoredPaymentMethodIdReturnsNullWhenMissing(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->paymentTokenResourceModelMock->method('getConnection')->willReturn($connection);
        $this->paymentTokenResourceModelMock->method('getMainTable')->willReturn('vault_payment_token');

        $selectMock = $this->getMockBuilder(\Magento\Framework\DB\Select::class)
            ->disableOriginalConstructor()->getMock();
        $connection->method('select')->willReturn($selectMock);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();

        $connection->method('fetchRow')->willReturn(false);

        $this->assertNull($this->vault->getVaultTokenByStoredPaymentMethodId('missing'));
    }

    public function testBuildPaymentMethodRecurringDataTakesRequestRpmOverConfig(): void
    {
        $storeId = 1;

        $paymentMock = $this->getMockBuilder(Order\Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethodInstance','getOrder','getAdditionalInformation'])
            ->getMock();

        $paymentMock->method('getMethodInstance')
            ->willReturn($this->createConfiguredMock(Adapter::class, ['getCode' => 'adyen_klarna']));

        $paymentMock->method('getOrder')
            ->willReturn($this->createConfiguredMock(Order::class, ['getQuoteId' => 7]));

        $paymentMock->method('getAdditionalInformation')->willReturnCallback(function ($key) {
            return $key === 'recurringProcessingModel' ? 'Subscription' : null;
        });

        $this->config->method('getConfigData')->willReturn(
            '{"adyen_klarna":{"enabled":true,"recurringProcessingModel":"CardOnFile"}}'
        );

        $this->stateData->method('getStateData')->willReturn([]);
        $this->stateData->method('getStoredPaymentMethodIdFromStateData')->willReturn(null);

        $req = $this->vault->buildPaymentMethodRecurringData($paymentMock, $storeId);
        $this->assertSame('Subscription', $req['recurringProcessingModel']);
        $this->assertTrue($req['storePaymentMethod']);
    }

    public function testBuildPaymentMethodRecurringDataReturnsEmptyWhenDisabled(): void
    {
        $storeId = 1;
        $paymentMock = $this->createConfiguredMock(Order\Payment::class, [
            'getMethodInstance' => $this->createConfiguredMock(Adapter::class, ['getCode' => 'adyen_klarna']),
            'getOrder' => $this->createConfiguredMock(Order::class, ['getQuoteId' => 1])
        ]);

        $this->config->method('getConfigData')->willReturn('{"adyen_klarna":{"enabled":false}}');

        $req = $this->vault->buildPaymentMethodRecurringData($paymentMock, $storeId);
        $this->assertSame([], $req);
    }

}
