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

namespace Adyen\Payment\Test\Unit\Model\Resolver\StoreConfig;

use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Model\Resolver\StoreConfig\StoreLocale;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\Context;
use Magento\GraphQl\Model\Query\ContextExtensionInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Adyen\Payment\Model\Resolver\StoreConfig\StoreLocale
 */
class StoreLocaleTest extends AbstractAdyenTestCase
{
    private MockObject|Context $contextMock;
    private MockObject|Field $fieldMock;
    private MockObject|ResolveInfo $infoMock;
    private MockObject|Locale $dataHelperMock;
    private MockObject|ContextExtensionInterface $contextExtensionMock;
    private StoreLocale $storeLocale;

    protected function setUp(): void
    {
        $this->contextExtensionMock = $this->createMock(ContextExtensionInterface::class);

        $this->contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExtensionAttributes'])
            ->getMock();

        $this->fieldMock = $this->createMock(Field::class);
        $this->infoMock = $this->createMock(ResolveInfo::class);

        $this->dataHelperMock = $this->getMockBuilder(Locale::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreLocale'])
            ->getMock();

        $this->storeLocale = new StoreLocale($this->dataHelperMock);
    }

    /**
     * @return void
     * @throws \Exception
     * @covers ::resolve
     */
    public function testGetStoreLocale(): void
    {
        $storeMock = $this->createConfiguredMock(Store::class, [
            'getId' => 1,
        ]);

        $this->contextExtensionMock
            ->expects($this->once())
            ->method('getStore')
            ->willReturn($storeMock);

        $this->contextMock
            ->expects($this->once())
            ->method('getExtensionAttributes')
            ->willReturn($this->contextExtensionMock);

        $this->dataHelperMock
            ->expects($this->once())
            ->method('getStoreLocale')
            ->willReturn('fr_FR');

        $this->assertEquals(
            'fr_FR',
            $this->storeLocale->resolve($this->fieldMock, $this->contextMock, $this->infoMock, [], [])
        );
    }
}
