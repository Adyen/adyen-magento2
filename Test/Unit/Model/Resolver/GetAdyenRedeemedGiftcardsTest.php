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

use Adyen\Payment\Helper\GiftcardPayment;
use Adyen\Payment\Model\Resolver\GetAdyenRedeemedGiftcards;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Adyen\Payment\Test\Model\Resolver\Order;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class GetAdyenRedeemedGiftcardsTest extends AbstractAdyenTestCase
{
    private $giftcardPaymentMock;
    private $jsonSerializerMock;
    private $quoteIdMaskFactoryMock;
    private $quoteIdMaskMock;
    private $getAdyenRedeemedGiftcards;

    protected function setUp(): void
    {
        $this->giftcardPaymentMock = $this->createMock(GiftcardPayment::class);
        $this->jsonSerializerMock = $this->createMock(Json::class);
        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class);
        $this->quoteIdMaskMock = $this->createMock(QuoteIdMask::class);

        $this->quoteIdMaskFactoryMock->method('create')->willReturn($this->quoteIdMaskMock);

        $this->getAdyenRedeemedGiftcards = new GetAdyenRedeemedGiftcards(
            $this->giftcardPaymentMock,
            $this->jsonSerializerMock,
            $this->quoteIdMaskFactoryMock
        );
    }

    public function testSuccessfulRetrievalOfRedeemedGiftCardDetailsWithValidCartId()
    {

        $fieldMock = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $contextMock = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $resolveInfoMock = $this->createMock(\Magento\Framework\GraphQl\Schema\Type\ResolveInfo::class);


        $cartId = 'test_cart_id';
        $quoteId = 0;
        $args = ['cartId' => $cartId];
        $redeemedGiftcardsJson = '{"redeemedGiftcards": [], "remainingAmount": 100, "totalDiscount": 10}';
        $redeemedGiftcardsData = json_decode($redeemedGiftcardsJson, true);

        $this->quoteIdMaskMock->expects($this->once())
            ->method('load')
            ->with($cartId, 'masked_id')
            ->willReturn($this->quoteIdMaskMock);

        $this->quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $this->quoteIdMaskMock->method('load')->willReturn($this->quoteIdMaskMock);
        $this->quoteIdMaskMock->method('getQuoteId')->willReturn(1);

        $this->giftcardPaymentMock->expects($this->once())
            ->method('fetchRedeemedGiftcards')
            ->with($quoteId)
            ->willReturn($redeemedGiftcardsJson);

        $this->jsonSerializerMock->expects($this->once())
            ->method('unserialize')
            ->with($redeemedGiftcardsJson)
            ->willReturn($redeemedGiftcardsData);


        $result = $this->getAdyenRedeemedGiftcards->resolve($fieldMock, $contextMock, $resolveInfoMock, [], $args);

        $this->assertEquals(['redeemedGiftcards' => $redeemedGiftcardsData['redeemedGiftcards'], 'remainingAmount' => $redeemedGiftcardsData['remainingAmount'], 'totalDiscount' => $redeemedGiftcardsData['totalDiscount']], $result);
    }
}





