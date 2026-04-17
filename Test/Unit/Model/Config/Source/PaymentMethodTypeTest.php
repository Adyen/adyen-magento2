<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Config\Source;

use Adyen\Payment\Model\Config\Source\PaymentMethodType;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Helper\Data as MagentoPaymentDataHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(PaymentMethodType::class)]
class PaymentMethodTypeTest extends AbstractAdyenTestCase
{
    private MockObject $paymentHelperMock;
    private MockObject $scopeConfigMock;
    private PaymentMethodType $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentHelperMock = $this->createMock(MagentoPaymentDataHelper::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $this->source = new PaymentMethodType(
            $this->paymentHelperMock,
            $this->scopeConfigMock
        );
    }

    public function testToOptionArrayFiltersNonAdyenMethods(): void
    {
        $this->paymentHelperMock->method('getPaymentMethodList')
            ->willReturn([
                'checkmo' => 'Check / Money Order',
                'banktransfer' => 'Bank Transfer',
                'adyen_ideal' => 'iDEAL',
            ]);

        $this->scopeConfigMock->method('getValue')
            ->willReturn('iDEAL');

        $result = $this->source->toOptionArray();

        $this->assertCount(1, $result);
        $this->assertSame('ideal', $result[0]['value']);
    }

    public function testToOptionArrayExcludesExcludedCodes(): void
    {
        $this->paymentHelperMock->method('getPaymentMethodList')
            ->willReturn([
                'adyen_hpp' => 'HPP',
                'adyen_oneclick' => 'One Click',
                'adyen_pos_cloud' => 'POS Cloud',
                'adyen_pay_by_link' => 'Pay by Link',
                'adyen_moto' => 'MOTO',
                'adyen_abstract' => 'Abstract',
                'adyen_hpp_vault' => 'HPP Vault',
                'adyen_ideal' => 'iDEAL',
            ]);

        $this->scopeConfigMock->method('getValue')
            ->willReturn('iDEAL');

        $result = $this->source->toOptionArray();

        $this->assertCount(1, $result);
        $this->assertSame('ideal', $result[0]['value']);
    }

    public function testToOptionArrayExcludesVaultMethods(): void
    {
        $this->paymentHelperMock->method('getPaymentMethodList')
            ->willReturn([
                'adyen_klarna' => 'Klarna',
                'adyen_klarna_vault' => 'Stored Klarna',
                'adyen_cc_vault' => 'Stored Cards',
            ]);

        $this->scopeConfigMock->method('getValue')
            ->willReturn('Klarna');

        $result = $this->source->toOptionArray();

        $this->assertCount(1, $result);
        $this->assertSame('klarna', $result[0]['value']);
    }

    public function testToOptionArrayMapsCcToScheme(): void
    {
        $this->paymentHelperMock->method('getPaymentMethodList')
            ->willReturn(['adyen_cc' => 'Cards']);

        $this->scopeConfigMock->method('getValue')
            ->willReturn('Cards');

        $result = $this->source->toOptionArray();

        $this->assertCount(1, $result);
        $this->assertSame('scheme', $result[0]['value']);
        $this->assertSame('Cards (scheme)', (string) $result[0]['label']);
    }

    public function testToOptionArrayMapsBoletoToBoletobancario(): void
    {
        $this->paymentHelperMock->method('getPaymentMethodList')
            ->willReturn(['adyen_boleto' => 'Boleto']);

        $this->scopeConfigMock->method('getValue')
            ->willReturn('Boleto');

        $result = $this->source->toOptionArray();

        $this->assertCount(1, $result);
        $this->assertSame('boletobancario', $result[0]['value']);
        $this->assertSame('Boleto (boletobancario)', (string) $result[0]['label']);
    }

    public function testToOptionArrayUsesCodeAsFallbackWhenTitleEmpty(): void
    {
        $this->paymentHelperMock->method('getPaymentMethodList')
            ->willReturn(['adyen_ideal' => 'iDEAL']);

        $this->scopeConfigMock->method('getValue')
            ->willReturn('');

        $result = $this->source->toOptionArray();

        $this->assertCount(1, $result);
        $this->assertSame('ideal', (string) $result[0]['label']);
    }

    public function testToOptionArraySortsAlphabetically(): void
    {
        $this->paymentHelperMock->method('getPaymentMethodList')
            ->willReturn([
                'adyen_sepadirectdebit' => 'SEPA',
                'adyen_ideal' => 'iDEAL',
                'adyen_cc' => 'Cards',
            ]);

        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['payment/adyen_sepadirectdebit/title', 'store', null, 'SEPA Direct Debit'],
                ['payment/adyen_ideal/title', 'store', null, 'iDEAL'],
                ['payment/adyen_cc/title', 'store', null, 'Cards'],
            ]);

        $result = $this->source->toOptionArray();

        // strcmp sorts uppercase before lowercase: Cards < SEPA < iDEAL
        $this->assertSame('scheme', $result[0]['value']);
        $this->assertSame('sepadirectdebit', $result[1]['value']);
        $this->assertSame('ideal', $result[2]['value']);
    }

    public function testToOptionArrayReturnsEmptyWhenNoAdyenMethods(): void
    {
        $this->paymentHelperMock->method('getPaymentMethodList')
            ->willReturn(['checkmo' => 'Check / Money Order']);

        $result = $this->source->toOptionArray();

        $this->assertSame([], $result);
    }
}
