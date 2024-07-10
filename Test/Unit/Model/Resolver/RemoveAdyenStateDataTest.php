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
use Adyen\Payment\Model\Api\AdyenStateData;
use Adyen\Payment\Model\Resolver\RemoveAdyenStateData;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Catalog\Model\Layer\ContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class RemoveAdyenStateDataTest extends AbstractAdyenTestCase
{
    private $removeAdyenStateDataResolver;
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

        $this->removeAdyenStateDataResolver = $this->objectManager->getObject(RemoveAdyenStateData::class, [
            'adyenStateData' => $this->adyenStateDataHelperMock,
            'quoteIdMaskFactory' => $this->quoteIdMaskFactoryMock
        ]);
    }

    public function testResolve()
    {
        $stateDataId = 1;

        $args = [
            'stateDataId' => $stateDataId,
            'cartId' => 1
        ];

        $this->adyenStateDataHelperMock->expects($this->once())->method('remove')->willReturn(true);

        $result = $this->removeAdyenStateDataResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );

        $this->assertArrayHasKey('stateDataId', $result);
        $this->assertEquals($stateDataId, $result['stateDataId']);
    }

    public function testResolveWithLocalizedException()
    {
        $this->expectException(LocalizedException::class);

        $stateDataId = 1;

        $args = [
            'stateDataId' => $stateDataId,
            'cartId' => 1
        ];

        $this->adyenStateDataHelperMock->expects($this->once())->method('remove')->willReturn(false);

        $result = $this->removeAdyenStateDataResolver->resolve(
            $this->fieldMock,
            $this->contextMock,
            $this->infoMock,
            null,
            $args
        );
    }

    public function testResolveWithGraphQLAdyenException()
    {
        $this->expectException(GraphQlAdyenException::class);

        $args = [
            'stateDataId' => 1,
            'cartId' => 1
        ];

        $this->adyenStateDataHelperMock->expects($this->once())
            ->method('remove')
            ->willThrowException(new Exception());

        $this->removeAdyenStateDataResolver->resolve(
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
    public function testResolveFailureWithWrongInput($stateDataId, $cartId)
    {
        $this->expectException(GraphQlInputException::class);

        $args = [
            'stateDataId' => $stateDataId,
            'cartId' => $cartId
        ];

        $this->removeAdyenStateDataResolver->resolve(
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
                'stateDataId' => '',
                'cartId' => 1
            ],
            [
                'stateDataId' => 1,
                'cartId' => ''
            ],
            [
                'stateDataId' => '',
                'cartId' => ''
            ],
            [
                'stateDataId' => null,
                'cartId' => 1
            ]
        ];
    }
}
