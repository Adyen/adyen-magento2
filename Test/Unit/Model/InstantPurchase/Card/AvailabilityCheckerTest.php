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

namespace Adyen\Payment\Test\Helper\Unit\Model\InstantPurchase\Card;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\InstantPurchase\Card\AvailabilityChecker;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AvailabilityCheckerTest extends AbstractAdyenTestCase
{
    const STORE_ID = PHP_INT_MAX;

    protected ?AvailabilityChecker $availabilityChecker;
    protected Config|MockObject $configHelperMock;
    protected Vault|MockObject $vaultHelperMock;
    protected StoreManagerInterface|MockObject $storeManagerMock;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->configHelperMock = $this->createMock(Config::class);
        $this->vaultHelperMock = $this->createMock(Vault::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(self::STORE_ID);
        $this->storeManagerMock->method('getStore')->willReturn($store);

        $this->availabilityChecker = new AvailabilityChecker(
            $this->configHelperMock,
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
     * @param $isCardRecurringEnabled
     * @param $recurringProcessingModel
     * @param $isCvcRequiredForCardRecurringPayments
     * @param $expectedResult
     *
     * @dataProvider availabilityTestDataProvider
     *
     * @return void
     */
    public function testIsAvailable(
        $isCardRecurringEnabled,
        $recurringProcessingModel,
        $isCvcRequiredForCardRecurringPayments,
        $expectedResult
    ) {
        $this->vaultHelperMock->expects($this->once())
            ->method('getPaymentMethodRecurringActive')
            ->with(AdyenCcConfigProvider::CODE, self::STORE_ID)
            ->willReturn($isCardRecurringEnabled);

        $this->vaultHelperMock->expects($this->once())
            ->method('getPaymentMethodRecurringProcessingModel')
            ->with(AdyenCcConfigProvider::CODE, self::STORE_ID)
            ->willReturn($recurringProcessingModel);

        $this->configHelperMock->expects($this->once())
            ->method('getIsCvcRequiredForRecurringCardPayments')
            ->with(self::STORE_ID)
            ->willReturn($isCvcRequiredForCardRecurringPayments);

        $this->assertEquals($expectedResult, $this->availabilityChecker->isAvailable());
    }

    /**
     * @return array[]
     */
    protected static function availabilityTestDataProvider(): array
    {
        return [
            [
                'isCardRecurringEnabled' => true,
                'recurringProcessingModel' => Vault::CARD_ON_FILE,
                'isCvcRequiredForCardRecurringPayments' => false,
                'expectedResult' => true
            ],
            [
                'isCardRecurringEnabled' => true,
                'recurringProcessingModel' => Vault::SUBSCRIPTION,
                'isCvcRequiredForCardRecurringPayments' => false,
                'expectedResult' => false
            ],
            [
                'isCardRecurringEnabled' => false,
                'recurringProcessingModel' => Vault::CARD_ON_FILE,
                'isCvcRequiredForCardRecurringPayments' => false,
                'expectedResult' => false
            ],
            [
                'isCardRecurringEnabled' => true,
                'recurringProcessingModel' => Vault::CARD_ON_FILE,
                'isCvcRequiredForCardRecurringPayments' => true,
                'expectedResult' => false
            ]
        ];
    }
}
