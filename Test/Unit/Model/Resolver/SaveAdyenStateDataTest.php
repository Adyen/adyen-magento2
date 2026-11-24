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

use Adyen\Payment\Helper\Quote as QuoteHelper;
use Adyen\Payment\Exception\GraphQlAdyenException;
use Magento\Framework\Exception\NoSuchEntityException;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\AdyenStateData;
use Adyen\Payment\Model\Resolver\SaveAdyenStateData;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Catalog\Model\Layer\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class SaveAdyenStateDataTest extends AbstractAdyenTestCase
{
    private SaveAdyenStateData $saveAdyenStateDataResolver;
    private AdyenStateData|MockObject $adyenStateDataHelperMock;
    private Field|MockObject $fieldMock;
    private ContextInterface|MockObject $contextMock;
    private ResolveInfo|MockObject $infoMock;
    private QuoteHelper|MockObject $quoteHelperMock;
    private AdyenLogger|MockObject $adyenLoggerMock;

    public function setUp(): void
    {
        $this->adyenStateDataHelperMock = $this->createMock(AdyenStateData::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createGraphQlContextMock();
        $this->infoMock = $this->createMock(ResolveInfo::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->quoteHelperMock = $this->createMock(QuoteHelper::class);
        $this->quoteHelperMock->method('getCartForUser')->willReturn($this->createQuoteMockWithId(1));

        $this->saveAdyenStateDataResolver = new SaveAdyenStateData(
            $this->adyenStateDataHelperMock,
            $this->quoteHelperMock,
            $this->adyenLoggerMock,
        );
    }

    public function testResolve()
    {
        $stateData = "{\"paymentMethod\":{\"type\":\"giftcard\",\"brand\":\"svs\",\"encryptedCardNumber\":\"abd...\",\"encryptedSecurityCode\":\"xyz...\"},\"giftcard\":{\"balance\":{\"currency\":\"EUR\",\"value\":5000},\"title\":\"SVS\"}}";
        $stateDataId = 1;

        $args = [
            'stateData' => $stateData,
            'cartId' => '1'
        ];

        $this->adyenStateDataHelperMock->expects($this->once())->method('save')->willReturn($stateDataId);

        $result = $this->saveAdyenStateDataResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );

        $this->assertArrayHasKey('stateDataId', $result);
        $this->assertEquals($stateDataId, $result['stateDataId']);
    }

    public function testResolveFailedWithException()
    {
        $this->expectException(Exception::class);

        $args = [
            'stateData' => "{}",
            'cartId' => '1'
        ];

        $this->adyenStateDataHelperMock->expects($this->once())
            ->method('save')
            ->willThrowException(new Exception());

        $result = $this->saveAdyenStateDataResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    #[DataProvider('inputFailureDataProvider')]
    public function testResolveFailureWithWrongInput($stateData, $cartId)
    {
        $this->expectException(GraphQlInputException::class);

        $args = [
            'stateData' => $stateData,
            'cartId' => $cartId
        ];

        $this->saveAdyenStateDataResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    /**
     * Data provider for testResolveFailureWithWrongInput test method
     *
     * @return array
     */
    public static function inputFailureDataProvider(): array
    {
        return [
            [
                'stateData' => '',
                'cartId' => '1'
            ],
            [
                'stateData' => "{}",
                'cartId' => ''
            ],
            [
                'stateData' => '',
                'cartId' => ''
            ],
            [
                'stateData' => null,
                'cartId' => '1'
            ]
        ];
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
        $this->adyenStateDataHelperMock->expects($this->never())->method('save');

        $this->expectException(GraphQlAdyenException::class);

        $this->saveAdyenStateDataResolver->resolve(
            $this->fieldMock,
            $contextMock,
            $this->infoMock,
            null,
            ['stateData' => '{}', 'cartId' => 'other_customer_cart']
        );
    }
}
