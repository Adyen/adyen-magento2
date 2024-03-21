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
use Magento\Catalog\Model\Layer\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\TestCase;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Adyen\Payment\Exception\GraphQlAdyenException;
use Magento\Framework\GraphQl\Query;

class GetAdyenPaymentMethodsBalanceTest extends TestCase
{
    private $balanceMock;
    private $contextMock;
    private $fieldMock;
    private $infoMock;
    private $getAdyenPaymentMethodsBalance;

    protected function setUp(): void
    {
        $this->balanceMock = $this->createMock(AdyenPaymentMethodsBalance::class);
        $this->contextMock = $this->createMock(ContextInterface::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $this->getAdyenPaymentMethodsBalance = new GetAdyenPaymentMethodsBalance(
            $this->balanceMock
        );
    }

    public function testWithMissingPayloadArgument()
    {
        $this->expectException(GraphQlInputException::class);
        $this->expectExceptionMessage('Required parameter "payload" is missing');

        $this->getAdyenPaymentMethodsBalance->resolve($this->fieldMock, $this->contextMock, $this->infoMock, [], []);
    }

    public function testWithValidPayloadArgument()
    {
        $payload = '{\"paymentMethod\":{\"type\":\"giftcard\",\"brand\":\"svs\",\"encryptedCardNumber\":\"abc…\",\"encryptedSecurityCode\":\"xyz…\"},\"amount\":{\"currency\":\"EUR\",\"value\":1000}}';
        $args = ['payload' => $payload];
        $expectedBalanceResponse = '10';

        $this->balanceMock->expects($this->once())
            ->method('getBalance')
            ->with($payload)
            ->willReturn($expectedBalanceResponse);

        $result = $this->getAdyenPaymentMethodsBalance->resolve($this->fieldMock, $this->contextMock, $this->infoMock, [], $args);

        $this->assertEquals(['balanceResponse' => $expectedBalanceResponse], $result);
    }

    public function testWithFailingApiCall()
    {
        $this->expectException(GraphQlAdyenException::class);

        $args = [
            'payload' => "{}"
        ];

        $this->balanceMock->method('getBalance')->willThrowException(new \Exception());

        $this->getAdyenPaymentMethodsBalance->resolve($this->fieldMock, $this->contextMock, $this->infoMock, [], $args);
    }
}





