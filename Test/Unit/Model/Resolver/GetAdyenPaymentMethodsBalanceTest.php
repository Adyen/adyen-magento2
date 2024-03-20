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

use Adyen\Payment\Model\Api\AdyenPaymentMethodsBalance;
use Adyen\Payment\Model\Resolver\GetAdyenPaymentMethodsBalance;
//use GraphQL\Type\Definition\ResolveInfo;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\TestCase;
use Magento\Framework\GraphQl\Query\Fields;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Adyen\Payment\Api\AdyenPaymentMethodsBalanceInterface;
use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\GraphQl\Query;
use PHPUnit\Framework\MockObject\MockObject;
use Exception;

class GetAdyenPaymentMethodsBalanceTest extends TestCase
{
    private $balanceMock;
    private $getAdyenPaymentMethodsBalance;



    protected function setUp(): void
    {
        $this->balanceMock = $this->createMock(AdyenPaymentMethodsBalance::class);

        $this->getAdyenPaymentMethodsBalance = new GetAdyenPaymentMethodsBalance(
            $this->balanceMock
        );
    }

    public function testWithMissingPayloadArgument()
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Required parameter "payload" is missing');

        $fieldMock = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $contextMock = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $resolveInfoMock = $this->createMock(\Magento\Framework\GraphQl\Schema\Type\ResolveInfo::class);

        $this->getAdyenPaymentMethodsBalance->resolve($fieldMock, $contextMock, $resolveInfoMock, [], []);
    }

    public function testWithValidPayloadArgument()
    {
        $payload = '{\"paymentMethod\":{\"type\":\"giftcard\",\"brand\":\"svs\",\"encryptedCardNumber\":\"abc…\",\"encryptedSecurityCode\":\"xyz…\"},\"amount\":{\"currency\":\"EUR\",\"value\":1000}}';
        $args = ['payload' => $payload];
        $expectedBalanceResponse = '10';

        $fieldMock = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $contextMock = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
        $resolveInfoMock = $this->createMock(ResolveInfo::class);

        $this->balanceMock->expects($this->once())
            ->method('getBalance')
            ->with($payload)
            ->willReturn($expectedBalanceResponse);

        $result = $this->getAdyenPaymentMethodsBalance->resolve($fieldMock, $contextMock, $resolveInfoMock, [], $args);

        $this->assertEquals(['balanceResponse' => $expectedBalanceResponse], $result);
    }

//    public function testWithAdyensAPIReturningAnError()
//    {
//        $payload = '{"some":"data"}';
//        $args = ['payload' => $payload];
//
//        $this->balanceMock->expects($this->once())
//            ->method('getBalance')
//            ->with($payload)
//            ->willThrowException(new Exception('API Error'));
//
//        $this->expectException(GraphQlAdyenException::class);
//        $this->expectExceptionMessage('An error occurred while fetching the payment method balance.');
//
//        $fieldMock = $this->createMock(\Magento\Framework\GraphQl\Query\Fields::class);
//        $contextMock = $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class);
//        $resolveInfoMock = $this->createMock(ResolveInfo::class);
//
//        $result = $this->getAdyenPaymentMethodsBalance->resolve($fieldMock, $contextMock, $resolveInfoMock, [], $args);
//    }


}





