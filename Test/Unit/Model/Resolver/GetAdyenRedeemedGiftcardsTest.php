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
namespace Adyen\Payment\Test\Model\Resolver;

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Helper\GiftcardPayment;
use Adyen\Payment\Helper\Quote as QuoteHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Resolver\GetAdyenRedeemedGiftcards;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class GetAdyenRedeemedGiftcardsTest extends AbstractAdyenTestCase
{
    private GetAdyenRedeemedGiftcards $getAdyenRedeemedGiftcards;
    private GiftcardPayment|MockObject $giftcardPaymentMock;
    private Json|MockObject $jsonSerializerMock;
    private Field|MockObject $fieldMock;
    private ContextInterface|MockObject $contextMock;
    private ResolveInfo|MockObject $resolveInfoMock;
    private QuoteHelper|MockObject $quoteHelperMock;
    private AdyenLogger|MockObject $adyenLoggerMock;

    protected function setUp(): void
    {
        $this->giftcardPaymentMock = $this->createMock(GiftcardPayment::class);
        $this->jsonSerializerMock = $this->createMock(Json::class);
        $this->quoteHelperMock = $this->createMock(QuoteHelper::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createGraphQlContextMock();
        $this->resolveInfoMock = $this->createMock(ResolveInfo::class);

        $this->getAdyenRedeemedGiftcards = new GetAdyenRedeemedGiftcards(
            $this->giftcardPaymentMock,
            $this->jsonSerializerMock,
            $this->quoteHelperMock,
            $this->adyenLoggerMock
        );
    }

    public function testSuccessfulRetrievalOfRedeemedGiftCardDetailsWithValidCartId()
    {
        $cartId = 'test_cart_id';
        $quoteId = 0;
        $args = ['cartId' => $cartId];
        $redeemedGiftcardsJson = '{"redeemedGiftcards": [], "remainingAmount": 100, "totalDiscount": 10}';
        $redeemedGiftcardsData = json_decode($redeemedGiftcardsJson, true);

        $this->quoteHelperMock->expects($this->once())->method('getCartForUser')->willReturn($this->createQuoteMockWithId($quoteId));

        $this->giftcardPaymentMock->expects($this->once())
            ->method('fetchRedeemedGiftcards')
            ->with($quoteId)
            ->willReturn($redeemedGiftcardsJson);

        $this->jsonSerializerMock->expects($this->once())
            ->method('unserialize')
            ->with($redeemedGiftcardsJson)
            ->willReturn($redeemedGiftcardsData);

        $result = $this->getAdyenRedeemedGiftcards->resolve($this->fieldMock, $this->contextMock, $this->resolveInfoMock, [], $args);

        $this->assertEquals(
            [
                'redeemedGiftcards' => $redeemedGiftcardsData['redeemedGiftcards'],
                'remainingAmount' => $redeemedGiftcardsData['remainingAmount'],
                'totalDiscount' => $redeemedGiftcardsData['totalDiscount']
            ],
            $result
        );
    }

    public function testFailedRetrievalOfRedeemedGiftCards()
    {
        $this->expectException(GraphQlAdyenException::class);

        $cartId = 'test_cart_id';
        $args = ['cartId' => $cartId];
        $quoteId = 1;

        $this->quoteHelperMock->expects($this->once())->method('getCartForUser')->willReturn($this->createQuoteMockWithId($quoteId));

        $this->giftcardPaymentMock->method('fetchRedeemedGiftcards')
            ->willThrowException(new Exception());

        $this->getAdyenRedeemedGiftcards->resolve($this->fieldMock, $this->contextMock, $this->resolveInfoMock, [], $args);
    }

    public function testFailedRetrievalOfRedeemedGiftCardsWithNullCartId()
    {
        $this->expectException(GraphQlInputException::class);

        $args = ['cartId' => ""];

        $this->getAdyenRedeemedGiftcards->resolve($this->fieldMock, $this->contextMock, $this->resolveInfoMock, [], $args);
    }

    public function testResolveRejectsCartNotOwnedByCurrentUser(): void
    {
        $contextMock = $this->createGraphQlContextMock(42, 3);

        // The user ID and store ID from the GraphQL context must be used to resolve the cart
        $this->quoteHelperMock->expects($this->once())
            ->method('getCartForUser')
            ->with('other_customer_cart', 42, 3)
            ->willThrowException(new NoSuchEntityException(
                __('The current user cannot perform operations on cart "%masked_cart_id"', ['masked_cart_id' => 'other_customer_cart'])
            ));
        $this->giftcardPaymentMock->expects($this->never())->method('fetchRedeemedGiftcards');

        $this->expectException(GraphQlAdyenException::class);

        $this->getAdyenRedeemedGiftcards->resolve(
            $this->fieldMock,
            $contextMock,
            $this->resolveInfoMock,
            [],
            ['cartId' => 'other_customer_cart']
        );
    }
}
