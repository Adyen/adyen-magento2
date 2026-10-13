<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Gateway\Request\PosCloudBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PointOfSale;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class PosCloudBuilderTest extends AbstractAdyenTestCase
{
    private PosCloudBuilder $posCloudBuilder;
    private MockObject $chargedCurrency;
    private MockObject $pointOfSale;

    private int $storeId = 3;

    protected function setUp(): void
    {
        $this->chargedCurrency = $this->createMock(ChargedCurrency::class);
        $this->pointOfSale = $this->createMock(PointOfSale::class);

        $amountCurrency = $this->createConfiguredMock(AdyenAmountCurrency::class, [
            'getCurrencyCode' => 'EUR',
            'getAmount' => 10.00
        ]);
        $this->chargedCurrency->method('getOrderAmountCurrency')->willReturn($amountCurrency);
        $this->pointOfSale->method('addSaleToAcquirerData')
            ->willReturnCallback(fn (array $request) => $request);

        $this->posCloudBuilder = new PosCloudBuilder($this->chargedCurrency, $this->pointOfSale);
    }

    private function buildSubject(?string $fundingSource = null, ?string $installments = null): array
    {
        $order = $this->createConfiguredMock(Order::class, [
            'getStoreId' => $this->storeId,
            'getIncrementId' => '000000123'
        ]);

        $payment = $this->createMock(Payment::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['terminal_id', 'TERMINAL-123'],
            ['funding_source', $fundingSource],
            ['number_of_installments', $installments],
        ]);

        return [
            'payment' => $this->createConfiguredMock(PaymentDataObjectInterface::class, [
                'getPayment' => $payment
            ])
        ];
    }

    public function testBuildIncludesClientConfigWithStoreId(): void
    {
        $result = $this->posCloudBuilder->build($this->buildSubject());

        $this->assertArrayHasKey('clientConfig', $result);
        $this->assertSame(['storeId' => $this->storeId], $result['clientConfig']);
    }

    public function testBuildIncludesPosRequestBody(): void
    {
        $result = $this->posCloudBuilder->build($this->buildSubject());

        $this->assertArrayHasKey('body', $result);
        $this->assertSame(
            'TERMINAL-123',
            $result['body']['SaleToPOIRequest']['MessageHeader']['POIID']
        );
        $this->assertSame(
            'EUR',
            $result['body']['SaleToPOIRequest']['PaymentRequest']['PaymentTransaction']['AmountsReq']['Currency']
        );
    }

    public function testBuildWithDebitFundingSource(): void
    {
        $result = $this->posCloudBuilder->build(
            $this->buildSubject(PaymentMethods::FUNDING_SOURCE_DEBIT)
        );

        $this->assertTrue(
            $result['body']['SaleToPOIRequest']['PaymentRequest']['PaymentTransaction']
            ['TransactionConditions']['DebitPreferredFlag']
        );
    }

    public function testBuildWithInstallments(): void
    {
        $result = $this->posCloudBuilder->build(
            $this->buildSubject(null, '3')
        );

        $paymentData = $result['body']['SaleToPOIRequest']['PaymentRequest']['PaymentData'];
        $this->assertSame('Instalment', $paymentData['PaymentType']);
        $this->assertSame(3, $paymentData['Instalment']['TotalNbOfPayments']);
    }
}
