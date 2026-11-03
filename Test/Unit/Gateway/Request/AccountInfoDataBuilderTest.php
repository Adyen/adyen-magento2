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

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Gateway\Request\AccountInfoDataBuilder;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class AccountInfoDataBuilderTest extends AbstractAdyenTestCase
{
    // 2026-10-27 12:00:00 UTC
    const NOW_TIMESTAMP = 1793102400;
    const QUOTE_CREATED_AT = '2026-10-27 11:00:00';

    private ?AccountInfoDataBuilder $accountInfoDataBuilder;
    private CustomerRepositoryInterface|MockObject $customerRepositoryMock;
    private CartRepositoryInterface|MockObject $cartRepositoryMock;
    private DateTime|MockObject $dateTimeMock;
    private AdyenLogger|MockObject $adyenLoggerMock;
    private Order|MockObject $orderMock;
    private Quote|MockObject $quoteMock;
    private int $quoteId = 1;
    private int $customerId = 10;
    private array $buildSubject;

    protected function setUp(): void
    {
        $this->customerRepositoryMock = $this->createMock(CustomerRepositoryInterface::class);
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->dateTimeMock = $this->createMock(DateTime::class);
        $this->dateTimeMock->method('gmtTimestamp')->willReturn(self::NOW_TIMESTAMP);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->quoteMock = $this->createMock(Quote::class);
        $this->cartRepositoryMock->method('get')->with($this->quoteId)->willReturn($this->quoteMock);

        $this->orderMock = $this->createMock(Order::class);
        $this->orderMock->method('getQuoteId')->willReturn($this->quoteId);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($this->orderMock);

        $paymentDataObjectMock = $this->createMock(PaymentDataObject::class);
        $paymentDataObjectMock->method('getPayment')->willReturn($paymentMock);

        $this->buildSubject = ['payment' => $paymentDataObjectMock];

        $this->accountInfoDataBuilder = new AccountInfoDataBuilder(
            $this->customerRepositoryMock,
            $this->cartRepositoryMock,
            $this->dateTimeMock,
            $this->adyenLoggerMock
        );
    }

    protected function tearDown(): void
    {
        $this->accountInfoDataBuilder = null;
    }

    public function testBuildForGuestShopper(): void
    {
        $this->orderMock->method('getCustomerId')->willReturn(null);
        $this->customerRepositoryMock->expects($this->never())->method('getById');

        $result = $this->accountInfoDataBuilder->build($this->buildSubject);

        $this->assertSame(
            ['body' => ['accountInfo' => ['accountAgeIndicator' => 'notApplicable']]],
            $result
        );
    }

    public static function loggedInShopperDataProvider(): array
    {
        return [
            'created during this checkout' => [
                'accountCreatedAt' => '2026-10-27 11:30:00',
                'quoteCreatedAt' => self::QUOTE_CREATED_AT,
                'expectedIndicator' => 'thisTransaction'
            ],
            'created at the same time as the quote' => [
                'accountCreatedAt' => self::QUOTE_CREATED_AT,
                'quoteCreatedAt' => self::QUOTE_CREATED_AT,
                'expectedIndicator' => 'thisTransaction'
            ],
            'created before the quote on the same day' => [
                'accountCreatedAt' => '2026-10-27 08:00:00',
                'quoteCreatedAt' => self::QUOTE_CREATED_AT,
                'expectedIndicator' => 'lessThan30Days'
            ],
            'created 29 days ago' => [
                'accountCreatedAt' => '2026-09-28 12:00:00',
                'quoteCreatedAt' => self::QUOTE_CREATED_AT,
                'expectedIndicator' => 'lessThan30Days'
            ],
            'created exactly 30 days ago' => [
                'accountCreatedAt' => '2026-09-27 12:00:00',
                'quoteCreatedAt' => self::QUOTE_CREATED_AT,
                'expectedIndicator' => 'from30To60Days'
            ],
            'created exactly 60 days ago' => [
                'accountCreatedAt' => '2026-08-28 12:00:00',
                'quoteCreatedAt' => self::QUOTE_CREATED_AT,
                'expectedIndicator' => 'from30To60Days'
            ],
            'created 61 days ago' => [
                'accountCreatedAt' => '2026-08-27 12:00:00',
                'quoteCreatedAt' => self::QUOTE_CREATED_AT,
                'expectedIndicator' => 'moreThan60Days'
            ],
            'quote without creation date' => [
                'accountCreatedAt' => '2025-01-01 00:00:00',
                'quoteCreatedAt' => null,
                'expectedIndicator' => 'moreThan60Days'
            ]
        ];
    }

    #[DataProvider('loggedInShopperDataProvider')]
    public function testBuildForLoggedInShopper(
        string $accountCreatedAt,
        ?string $quoteCreatedAt,
        string $expectedIndicator
    ): void {
        $customerMock = $this->createMock(CustomerInterface::class);
        $customerMock->method('getCreatedAt')->willReturn($accountCreatedAt);

        $this->orderMock->method('getCustomerId')->willReturn($this->customerId);
        $this->customerRepositoryMock->expects($this->once())
            ->method('getById')
            ->with($this->customerId)
            ->willReturn($customerMock);
        $this->quoteMock->method('getCreatedAt')->willReturn($quoteCreatedAt);

        $result = $this->accountInfoDataBuilder->build($this->buildSubject);

        $this->assertSame(
            ['body' => ['accountInfo' => ['accountAgeIndicator' => $expectedIndicator]]],
            $result
        );
    }

    public function testBuildReturnsEmptyArrayWhenCustomerCannotBeLoaded(): void
    {
        $this->orderMock->method('getCustomerId')->willReturn($this->customerId);
        $this->customerRepositoryMock->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Customer not found')));
        $this->adyenLoggerMock->expects($this->once())->method('error');

        $result = $this->accountInfoDataBuilder->build($this->buildSubject);

        $this->assertSame([], $result);
    }

    public function testBuildReturnsEmptyArrayWhenAccountCreationDateIsMissing(): void
    {
        $customerMock = $this->createMock(CustomerInterface::class);
        $customerMock->method('getCreatedAt')->willReturn(null);

        $this->orderMock->method('getCustomerId')->willReturn($this->customerId);
        $this->customerRepositoryMock->method('getById')->willReturn($customerMock);
        $this->adyenLoggerMock->expects($this->once())->method('error');

        $result = $this->accountInfoDataBuilder->build($this->buildSubject);

        $this->assertSame([], $result);
    }
}
