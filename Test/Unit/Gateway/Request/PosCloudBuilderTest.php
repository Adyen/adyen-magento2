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

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\PosCloudBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\ConnectedTerminals;
use Adyen\Payment\Helper\PointOfSale;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;

#[AllowMockObjectsWithoutExpectations]
class PosCloudBuilderTest extends AbstractAdyenTestCase
{
    private const STORE_ID = 1;
    private const CONNECTED_TERMINALS = [
        'uniqueTerminalIds' => ['P400Plus-123456789', 'S1F2-000158234567890']
    ];

    /**
     * @return void
     */
    public function testBuildAddsClientConfigAndBody()
    {
        $terminalId = 'P400Plus-123456789';

        $orderMock = $this->createOrderMock();

        $expectedBody = ['SaleToPOIRequest' => ['foo' => 'bar']];
        $pointOfSaleMock = $this->createMock(PointOfSale::class);
        $pointOfSaleMock->expects($this->once())
            ->method('addSaleToAcquirerData')
            ->with(
                $this->callback(function (array $request) use ($terminalId) {
                    return $request['SaleToPOIRequest']['MessageHeader']['POIID'] === $terminalId;
                }),
                $orderMock
            )
            ->willReturn($expectedBody);

        $connectedTerminalsMock = $this->createMock(ConnectedTerminals::class);
        $connectedTerminalsMock->expects($this->once())
            ->method('getConnectedTerminals')
            ->with(self::STORE_ID)
            ->willReturn(self::CONNECTED_TERMINALS);

        $builder = new PosCloudBuilder($this->createChargedCurrencyMock(), $pointOfSaleMock, $connectedTerminalsMock);
        $result = $builder->build($this->createBuildSubject($orderMock, $terminalId));

        $this->assertEquals(['storeId' => self::STORE_ID], $result['clientConfig']);
        $this->assertEquals($expectedBody, $result['body']);
    }

    /**
     * @return array
     */
    public static function invalidTerminalDataProvider(): array
    {
        return [
            'terminal not connected to the store' => [
                'terminalId' => 'INJECTED-POIID',
                'connectedTerminals' => self::CONNECTED_TERMINALS
            ],
            'terminal id differs only in case' => [
                'terminalId' => 'p400plus-123456789',
                'connectedTerminals' => self::CONNECTED_TERMINALS
            ],
            'terminal id missing' => [
                'terminalId' => null,
                'connectedTerminals' => self::CONNECTED_TERMINALS
            ],
            'no connected terminals returned' => [
                'terminalId' => 'P400Plus-123456789',
                'connectedTerminals' => []
            ],
            'empty connected terminals list' => [
                'terminalId' => 'P400Plus-123456789',
                'connectedTerminals' => ['uniqueTerminalIds' => []]
            ]
        ];
    }

    /**
     * @param string|null $terminalId
     * @param array $connectedTerminals
     * @return void
     */
    #[DataProvider('invalidTerminalDataProvider')]
    public function testBuildThrowsExceptionForInvalidTerminal(?string $terminalId, array $connectedTerminals)
    {
        $orderMock = $this->createOrderMock();

        $pointOfSaleMock = $this->createMock(PointOfSale::class);
        $pointOfSaleMock->expects($this->never())->method('addSaleToAcquirerData');

        $connectedTerminalsMock = $this->createMock(ConnectedTerminals::class);
        $connectedTerminalsMock->expects($this->once())
            ->method('getConnectedTerminals')
            ->with(self::STORE_ID)
            ->willReturn($connectedTerminals);

        $this->expectException(LocalizedException::class);

        $builder = new PosCloudBuilder($this->createChargedCurrencyMock(), $pointOfSaleMock, $connectedTerminalsMock);
        $builder->build($this->createBuildSubject($orderMock, $terminalId));
    }

    private function createOrderMock(): Order
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getStoreId')->willReturn(self::STORE_ID);
        $orderMock->method('getIncrementId')->willReturn('100000001');

        return $orderMock;
    }

    private function createChargedCurrencyMock(): ChargedCurrency
    {
        $amountCurrencyMock = $this->createConfiguredMock(AdyenAmountCurrency::class, [
            'getCurrencyCode' => 'EUR',
            'getAmount' => 1000
        ]);

        $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $chargedCurrencyMock->method('getOrderAmountCurrency')->willReturn($amountCurrencyMock);

        return $chargedCurrencyMock;
    }

    private function createBuildSubject(Order $orderMock, ?string $terminalId): array
    {
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getAdditionalInformation')->willReturnMap([
            ['terminal_id', $terminalId],
            ['funding_source', null],
            ['number_of_installments', null],
        ]);

        return [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];
    }
}
