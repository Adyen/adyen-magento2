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

namespace Adyen\Payment\Test\Helper\Unit\Model\InstantPurchase\PaymentMethods;

use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\InstantPurchase\PaymentMethods\AvailabilityChecker;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Polyfill\Intl\Icu\Exception\MethodNotImplementedException;

class AvailabilityCheckerTest extends AbstractAdyenTestCase
{
    const STORE_ID = PHP_INT_MAX;

    protected ?AvailabilityChecker $availabilityChecker;
    protected Vault|MockObject $vaultHelperMock;
    protected StoreManagerInterface|MockObject $storeManagerMock;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->vaultHelperMock = $this->createMock(Vault::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(self::STORE_ID);
        $this->storeManagerMock->method('getStore')->willReturn($store);

        $this->availabilityChecker = new AvailabilityChecker(
            $this->vaultHelperMock,
            $this->storeManagerMock
        );
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        $this->availabilityChecker = null;
    }

    /**
     * @param $paymentMethod
     * @param $isMethodRecurringEnabled
     * @param $recurringProcessingModel
     * @param $expectedResult
     *
     * @return void
     * @dataProvider availabilityTestDataProvider
     */
    public function testIsAvailableAdyenMethod(
        $paymentMethod,
        $isMethodRecurringEnabled,
        $recurringProcessingModel,
        $expectedResult
    ) {
        $this->vaultHelperMock->expects($this->once())
            ->method('getPaymentMethodRecurringActive')
            ->with($paymentMethod, self::STORE_ID)
            ->willReturn($isMethodRecurringEnabled);

        $this->vaultHelperMock->expects($this->once())
            ->method('getPaymentMethodRecurringProcessingModel')
            ->with($paymentMethod, self::STORE_ID)
            ->willReturn($recurringProcessingModel);

        $this->assertEquals($expectedResult, $this->availabilityChecker->isAvailableAdyenMethod($paymentMethod));
    }

    /**
     * @return array[]
     */
    protected static function availabilityTestDataProvider(): array
    {
        return [
            [
                'paymentMethod' => 'sepa_direct_debit',
                'isMethodRecurringEnabled' => true,
                'recurringProcessingModel' => Vault::CARD_ON_FILE,
                'expectedResult' => true
            ],
            [
                'paymentMethod' => 'sepa_direct_debit',
                'isMethodRecurringEnabled' => false,
                'recurringProcessingModel' => Vault::CARD_ON_FILE,
                'expectedResult' => false
            ],
            [
                'paymentMethod' => 'sepa_direct_debit',
                'isMethodRecurringEnabled' => true,
                'recurringProcessingModel' => Vault::SUBSCRIPTION,
                'expectedResult' => false
            ]
        ];
    }

    /**
     * Ensure `isAvailable` is not implemented.
     *
     * @return void
     */
    public function testIsAvailable()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->availabilityChecker->isAvailable();
    }
}
