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

use Adyen\Payment\Model\Api\AdyenStateData;
use Adyen\Payment\Model\Resolver\SaveAdyenStateData;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Catalog\Model\Layer\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class SaveAdyenStateDataTest extends AbstractAdyenTestCase
{
    private $saveAdyenStateDataResolver;
    private $adyenStateDataHelperMock;
    private $quoteIdMaskFactoryMock;
    private $quoteIdMaskMock;
    private $fieldMock;
    private $contextMock;
    private $infoMock;

    public function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->adyenStateDataHelperMock = $this->createMock(AdyenStateData::class);
        $this->fieldMock = $this->createMock(Field::class);
        $this->contextMock = $this->createMock(ContextInterface::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $this->quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $this->quoteIdMaskMock->method('load')->willReturn($this->quoteIdMaskMock);
        $this->quoteIdMaskMock->method('getQuoteId')->willReturn(1);

        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, [
            'create'
        ]);
        $this->quoteIdMaskFactoryMock->method('create')->willReturn($this->quoteIdMaskMock);

        $this->saveAdyenStateDataResolver = $this->objectManager->getObject(SaveAdyenStateData::class, [
            'adyenStateData' => $this->adyenStateDataHelperMock,
            'quoteIdMaskFactory' => $this->quoteIdMaskFactoryMock
        ]);
    }

    public function testResolve()
    {
        $stateData = "{\"paymentMethod\":{\"type\":\"giftcard\",\"brand\":\"svs\",\"encryptedCardNumber\":\"abd...\",\"encryptedSecurityCode\":\"xyz...\"},\"giftcard\":{\"balance\":{\"currency\":\"EUR\",\"value\":5000},\"title\":\"SVS\"}}";
        $stateDataId = 1;

        $args = [
            'stateData' => $stateData,
            'cartId' => 1
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
            'cartId' => 1
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

    /**
     * @dataProvider inputFailureDataProvider
     */
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
    private static function inputFailureDataProvider(): array
    {
        return [
            [
                'stateData' => '',
                'cartId' => 1
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
                'cartId' => 1
            ]
        ];
    }
}
