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
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Resolver;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Resolver\DataProvider\GetAdyenPaymentStatus;
use Adyen\Payment\Model\Resolver\GetAdyenPaymentDetails;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\GraphQl\Helper\Error\AggregateExceptionMessageFormatter;
use Magento\GraphQl\Model\Query\ContextExtensionInterface;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;

class GetAdyenPaymentDetailsTest extends AbstractAdyenTestCase
{
    private MockObject $getCartForUserMock;
    private MockObject $getAdyenPaymentStatusDataProviderMock;
    private MockObject $orderMock;
    private MockObject $jsonSerializerMock;
    private MockObject $adyenLoggerMock;
    private MockObject $adyenGraphQlExceptionMessageFormatterMock;
    private MockObject $contextMock;
    private MockObject $fieldMock;
    private MockObject $infoMock;
    private GetAdyenPaymentDetails $getAdyenPaymentDetails;

    protected function setUp(): void
    {
        $this->getCartForUserMock = $this->createMock(GetCartForUser::class);
        $this->getAdyenPaymentStatusDataProviderMock = $this->createMock(GetAdyenPaymentStatus::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->jsonSerializerMock = $this->createMock(Json::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenGraphQlExceptionMessageFormatterMock = $this->createMock(AggregateExceptionMessageFormatter::class);
        $this->contextMock = $this->createGeneratedMock(ContextInterface::class, [], ['getUserId', 'getExtensionAttributes']);
        $this->fieldMock = $this->createMock(Field::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $this->getAdyenPaymentDetails = new GetAdyenPaymentDetails(
            $this->getCartForUserMock,
            $this->getAdyenPaymentStatusDataProviderMock,
            $this->orderMock,
            $this->jsonSerializerMock,
            $this->adyenLoggerMock,
            $this->adyenGraphQlExceptionMessageFormatterMock
        );
    }

    public function testResolveWithMissingPayloadArgument(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Required parameter "payload" is missing');

        $args = ['cart_id' => 'test_cart_id'];

        $this->adyenGraphQlExceptionMessageFormatterMock
            ->method('getFormatted')
            ->willReturnCallback(function ($e) {
                return $e;
            });

        $this->getAdyenPaymentDetails->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveWithMissingCartIdArgument(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Required parameter "cart_id" is missing');

        $args = ['payload' => '{"orderId": "000000001"}'];

        $this->adyenGraphQlExceptionMessageFormatterMock
            ->method('getFormatted')
            ->willReturnCallback(function ($e) {
                return $e;
            });

        $this->getAdyenPaymentDetails->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveWithMissingOrderIdInPayload(): void
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Missing "orderId" from payload');

        $args = [
            'cart_id' => 'test_cart_id',
            'payload' => '{"someKey": "someValue"}'
        ];

        $this->jsonSerializerMock
            ->method('unserialize')
            ->with($args['payload'])
            ->willReturn(['someKey' => 'someValue']);

        $this->adyenGraphQlExceptionMessageFormatterMock
            ->method('getFormatted')
            ->willReturnCallback(function ($e) {
                return $e;
            });

        $this->getAdyenPaymentDetails->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveWithOrderQuoteIdNotMatchingCartEntityId(): void
    {
        $this->expectException(GraphQlNoSuchEntityException::class);
        $this->expectExceptionMessage('Order does not exist');

        $args = [
            'cart_id' => 'test_cart_id',
            'payload' => '{"orderId": "000000001"}'
        ];

        $this->jsonSerializerMock
            ->method('unserialize')
            ->with($args['payload'])
            ->willReturn(['orderId' => '000000001']);

        $this->orderMock
            ->method('loadByIncrementId')
            ->with('000000001')
            ->willReturnSelf();

        $this->orderMock
            ->method('getEntityId')
            ->willReturn('1');

        $this->orderMock
            ->method('getQuoteId')
            ->willReturn('100');

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);

        $extensionAttributesMock = $this->createMock(ContextExtensionInterface::class);
        $extensionAttributesMock->method('getStore')->willReturn($storeMock);

        $this->contextMock->method('getUserId')->willReturn(1);
        $this->contextMock->method('getExtensionAttributes')->willReturn($extensionAttributesMock);

        $cartMock = $this->createMock(Quote::class);
        $cartMock->method('getEntityId')->willReturn('200');

        $this->getCartForUserMock
            ->method('execute')
            ->with('test_cart_id', 1, 1)
            ->willReturn($cartMock);

        $this->adyenGraphQlExceptionMessageFormatterMock
            ->method('getFormatted')
            ->willReturnCallback(function ($e) {
                return $e;
            });

        $this->getAdyenPaymentDetails->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveWithPaymentRefused(): void
    {
        $args = [
            'cart_id' => 'test_cart_id',
            'payload' => '{"orderId": "000000001"}'
        ];

        $this->jsonSerializerMock
            ->method('unserialize')
            ->with($args['payload'])
            ->willReturn(['orderId' => '000000001']);

        $this->jsonSerializerMock
            ->method('serialize')
            ->willReturnCallback(function ($data) {
                return json_encode($data);
            });

        $this->orderMock
            ->method('loadByIncrementId')
            ->with('000000001')
            ->willReturnSelf();

        $this->orderMock
            ->method('getEntityId')
            ->willReturn('1');

        $this->orderMock
            ->method('getId')
            ->willReturn('1');

        $this->orderMock
            ->method('getQuoteId')
            ->willReturn('100');

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);

        $extensionAttributesMock = $this->createMock(ContextExtensionInterface::class);
        $extensionAttributesMock->method('getStore')->willReturn($storeMock);

        $this->contextMock->method('getUserId')->willReturn(1);
        $this->contextMock->method('getExtensionAttributes')->willReturn($extensionAttributesMock);

        $cartMock = $this->createMock(Quote::class);
        $cartMock->method('getEntityId')->willReturn('100');

        $this->getCartForUserMock
            ->method('execute')
            ->with('test_cart_id', 1, 1)
            ->willReturn($cartMock);

        $validatorException = new ValidatorException(__('The payment is REFUSED.'));

        $this->getAdyenPaymentStatusDataProviderMock
            ->method('getGetAdyenPaymentDetails')
            ->willThrowException($validatorException);

        $formattedException = new GraphQlInputException(__('The payment is REFUSED.'));

        $this->adyenGraphQlExceptionMessageFormatterMock
            ->method('getFormatted')
            ->willReturn($formattedException);

        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('The payment is REFUSED.');

        $this->getAdyenPaymentDetails->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }
}
