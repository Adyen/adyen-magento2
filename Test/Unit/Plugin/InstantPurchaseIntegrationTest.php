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

namespace Adyen\Payment\Test\Plugin;

use Adyen\Payment\Api\InstantPurchase\PaymentMethodIntegration\AdyenAvailabilityCheckerInterface;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Method\PaymentMethodVault;
use Adyen\Payment\Plugin\InstantPurchaseIntegration;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\InstantPurchase\PaymentMethodIntegration\Integration;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\MockObject\MockObject;

class InstantPurchaseIntegrationTest extends AbstractAdyenTestCase
{
    protected ?InstantPurchaseIntegration $instantPurchaseIntegrationPlugin;
    protected Integration|MockObject $subjectMock;
    protected AdyenAvailabilityCheckerInterface|MockObject $adyenAvailabilityCheckerMock;
    protected PaymentMethods|MockObject $paymentMethodsMock;
    protected Data $magentoDataHelperMock;

    public function setUp(): void
    {
        $this->adyenAvailabilityCheckerMock = $this->createMock(
            AdyenAvailabilityCheckerInterface::class
        );
        $this->paymentMethodsMock = $this->createMock(PaymentMethods::class);
        $this->magentoDataHelperMock = $this->createMock(Data::class);
        $this->subjectMock = $this->createMock(Integration::class);

        $this->instantPurchaseIntegrationPlugin = new InstantPurchaseIntegration(
            $this->adyenAvailabilityCheckerMock,
            $this->paymentMethodsMock,
            $this->magentoDataHelperMock
        );
    }

    public function tearDown(): void
    {
        $this->instantPurchaseIntegrationPlugin = null;
    }

    /**
     * @param $providerCode
     * @param $isAdyenAlternativePaymentMethod
     * @param $isWallet
     * @param $shouldIntercept
     *
     * @return void
     * @throws LocalizedException
     *
     * @dataProvider dataProviderForNotApplicableCases
     *
     */
    public function testAroundIsAvailable(
        $providerCode,
        $isAdyenAlternativePaymentMethod,
        $isWallet,
        $shouldIntercept
    ) {
        $vaultMethodInstanceMock = $this->createMock(PaymentMethodVault::class);
        $vaultMethodInstanceMock->method('getProviderCode')
            ->willReturn($providerCode);

        $this->subjectMock->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn($vaultMethodInstanceMock);

        $providerMethodInstanceMock = $this->createMock(MethodInterface::class);

        $this->magentoDataHelperMock->expects($this->once())
            ->method('getMethodInstance')
            ->with($providerCode)
            ->willReturn($providerMethodInstanceMock);

        $this->paymentMethodsMock->expects($this->once())
            ->method('isAlternativePaymentMethod')
            ->with($providerMethodInstanceMock)
            ->willReturn($isAdyenAlternativePaymentMethod);

        $this->paymentMethodsMock->expects($this->once())
            ->method('isWalletPaymentMethod')
            ->with($providerMethodInstanceMock)
            ->willReturn($isWallet);

        $this->subjectMock->method('isAvailable')->willReturn(true);

        if ($shouldIntercept) {
            $this->adyenAvailabilityCheckerMock
                ->expects($this->once())
                ->method('isAvailableAdyenMethod')
                ->with($providerCode)
                ->willReturn(true);
        } else {
            $this->adyenAvailabilityCheckerMock->expects($this->never())->method('isAvailableAdyenMethod');
        }

        $result = $this->instantPurchaseIntegrationPlugin->aroundIsAvailable(
            $this->subjectMock,
            [$this, 'callableMockFunction']
        );

        $this->assertTrue($result);
    }

    /**
     * Mock the callable argument of `aroundIsAvailable` method.
     *
     * @return bool
     */
    public function callableMockFunction(): bool
    {
        return true;
    }

    /**
     * @return array[]
     */
    public static function dataProviderForNotApplicableCases(): array
    {
        return [
            [
                'providerCode' => 'adyen_cc',
                'isAdyenAlternativePaymentMethod' => false,
                'isWallet' => false,
                'shouldIntercept' => false,
            ],
            [
                'providerCode' => 'adyen_googlepay',
                'isAdyenAlternativePaymentMethod' => true,
                'isWallet' => true,
                'shouldIntercept' => false,
            ],
            [
                'providerCode' => 'adyen_sepa_direct_debit',
                'isAdyenAlternativePaymentMethod' => true,
                'isWallet' => false,
                'shouldIntercept' => true,
            ]
        ];
    }
}
