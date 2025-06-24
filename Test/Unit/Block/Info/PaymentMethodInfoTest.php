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

namespace Adyen\Payment\Test\Block\Info;

use Adyen\Payment\Block\Info\PaymentMethodInfo;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentMethodInfoTest extends AbstractAdyenTestCase
{
    protected ?PaymentMethodInfo $paymentMethodInfo;
    protected MockObject|CollectionFactory $collectionFactoryMock;
    protected MockObject|Config $configHelperMock;
    protected MockObject|Context $contextMock;
    protected MockObject|ChargedCurrency $chargedCurrencyMock;
    protected array $data = [];

    /**
     * @return void
     */
    public function generateSut(): void
    {
        $this->paymentMethodInfo = new PaymentMethodInfo(
            $this->collectionFactoryMock,
            $this->configHelperMock,
            $this->contextMock,
            $this->chargedCurrencyMock,
            $this->data
        );
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->collectionFactoryMock = $this->createGeneratedMock(CollectionFactory::class, ['create']);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->contextMock = $this->createMock(Context::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->paymentMethodInfo = null;
    }

    /**
     * @return void
     */
    public function testGetBankTransferData()
    {
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getAdditionalInformation')
            ->willReturnMap([
                ['bankTransfer.owner', 'John Doe'],
                [null, ['bankTransfer.owner' => 'John Doe']]
            ]);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $paymentMock->method('getOrder')->willReturn($orderMock);

        $this->data = [
            'info' => $paymentMock
        ];

        $this->generateSut();

        $result = $this->paymentMethodInfo->getBankTransferData();
        $this->assertArrayHasKey('bankTransfer.owner', $result);
    }

    /**
     * @return array[]
     */
    private static function getMultibancoDataDataProvider(): array
    {
        return [
            [
                'actionMock' => [
                    'entity' => '123456',
                    'reference' => '111 222 333',
                    'expiresAt' => '2025-10-29T10:45:00'
                ]
            ],
            [
                'actionMock' => [
                    'entity' => '123456',
                    'reference' => '111 222 333',
                    'expiresAt' => '29.10.2025'
                ]
            ],
        ];
    }

    /**
     * @dataProvider getMultibancoDataDataProvider
     *
     * @param $actionMock
     * @return void
     */
    public function testsGetMultibancoData($actionMock)
    {
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getAdditionalInformation')
            ->with('action')
            ->willReturn($actionMock);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $paymentMock->method('getOrder')->willReturn($orderMock);

        $this->data = [
            'info' => $paymentMock
        ];

        $this->generateSut();

        $result = $this->paymentMethodInfo->getMultibancoData();

        $this->assertArrayHasKey('entity', $result);
        $this->assertArrayHasKey('reference', $result);
        $this->assertArrayHasKey('expiresAt', $result);
    }

    /**
     * @return void
     */
    public function testGetOrder()
    {
        $orderMock = $this->createMock(Order::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $this->data = [
            'info' => $paymentMock
        ];

        $this->generateSut();

        $result = $this->paymentMethodInfo->getOrder();
        $this->assertInstanceOf(Order::class, $result);
    }

    /**
     * @return void
     */
    public function testGetOrderAmountCurrency()
    {
        $orderMock = $this->createMock(Order::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $this->data = [
            'info' => $paymentMock
        ];

        $adyenOrderAmountCurrency = $this->createMock(AdyenAmountCurrency::class);

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->with($orderMock)
            ->willReturn($adyenOrderAmountCurrency);

        $this->generateSut();

        $result = $this->paymentMethodInfo->getOrderAmountCurrency();
        $this->assertInstanceOf(AdyenAmountCurrency::class, $result);
    }
}
