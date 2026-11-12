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

namespace Adyen\Payment\Test\Unit\Model\Ui;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Ui\AdyenPmConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AdyenPmConfigProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class AdyenPmConfigProviderTest extends AbstractAdyenTestCase
{
    public function testGetConfig(): void
    {
        $storeId = 1;
        $ratePayId = 'RATEPAY_ID';
        $bnplDisclaimer = 'Read the [terms](https://www.example.com/terms).';
        $openInvoicePaymentMethods = ['adyen_klarna', 'adyen_riverty'];

        $storeManagerMock = $this->createConfiguredMock(StoreManagerInterface::class, [
            'getStore' => $this->createConfiguredMock(StoreInterface::class, ['getId' => $storeId]),
        ]);
        $sessionMock = $this->createConfiguredMock(Session::class, ['getQuoteId' => 123]);

        $configHelperMock = $this->createMock(Config::class);
        $configHelperMock->expects($this->once())
            ->method('getRatePayId')
            ->with($storeId)
            ->willReturn($ratePayId);
        $configHelperMock->expects($this->once())
            ->method('getBnplDisclaimer')
            ->with($storeId)
            ->willReturn($bnplDisclaimer);

        $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsHelperMock->expects($this->once())
            ->method('getOpenInvoicePaymentMethods')
            ->willReturn($openInvoicePaymentMethods);

        $provider = new AdyenPmConfigProvider(
            $this->createMock(RequestInterface::class),
            $sessionMock,
            $storeManagerMock,
            $configHelperMock,
            $paymentMethodsHelperMock
        );

        $config = $provider->getConfig()['payment']['adyenPm'];

        $this->assertSame($ratePayId, $config['ratePayId']);
        $this->assertSame($bnplDisclaimer, $config['bnplDisclaimer']);
        $this->assertSame($openInvoicePaymentMethods, $config['openInvoicePaymentMethods']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $config['deviceIdentToken']);
    }
}
