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
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Method\Adapter;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Vault\Model\ResourceModel\PaymentToken as PaymentTokenResourceModel;

class VaultTest extends AbstractAdyenTestCase
{
    private $vault;
    private $adyenLogger;
    private $paymentTokenManagement;
    private $paymentTokenFactory;
    private $paymentTokenRepository;
    private $config;
    private $paymentMethodsHelper;
    private $stateData;
    private $paymentTokenResourceModelMock;
    private $orderPaymentExtensionInterfaceFactoryMock;

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

        $this->vault = new Vault(
            $this->adyenLogger,
            $this->paymentTokenManagement,
            $this->paymentTokenFactory,
            $this->paymentTokenRepository,
            $this->paymentTokenResourceModelMock,
            $this->orderPaymentExtensionInterfaceFactoryMock,
            $this->config,
            $this->paymentMethodsHelper,
            $this->stateData
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
}
