<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Block\Info;

use Adyen\Payment\Block\Info\AbstractInfo;
use Adyen\Payment\Block\Info\PartialPayments;
use Adyen\Payment\Model\ResourceModel\Order\Payment\Collection as OrderPaymentCollection;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Framework\View\Element\Template\Context;

class PartialPaymentsTest extends AbstractAdyenTestCase
{
    protected PartialPayments $partialPayments;
    protected Context|MockObject $contextMock;
    protected array $dataMock = [];

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);

        $this->partialPayments = $this->getMockBuilder(PartialPayments::class)
            ->disableOriginalConstructor()
            ->setConstructorArgs([$this->contextMock, $this->dataMock])
            ->addMethods(['getInfoBlock'])
            ->getMock();
    }

    /**
     * @return void
     * @throws Exception
     * @throws LocalizedException
     */
    public function testGetPartialPayments()
    {
        $orderPaymentCollection = $this->createMock(OrderPaymentCollection::class);

        $infoBlockMock = $this->createMock(AbstractInfo::class);
        $infoBlockMock->expects($this->once())
            ->method('getPartialPayments')
            ->willReturn($orderPaymentCollection);

        $this->partialPayments->method('getInfoBlock')->willReturn($infoBlockMock);
        $this->assertInstanceOf(OrderPaymentCollection::class, $this->partialPayments->getPartialPayments());
    }

    /**
     * @return void
     * @throws Exception
     * @throws LocalizedException
     */
    public function testGetPartialPaymentsShouldReturnNull()
    {
        $infoBlockMock = ['some_random_data'];

        $this->partialPayments->method('getInfoBlock')->willReturn($infoBlockMock);
        $this->assertNull($this->partialPayments->getPartialPayments());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testIsDemoMode()
    {
        $isDemoMode = true;

        $infoBlockMock = $this->createMock(AbstractInfo::class);
        $infoBlockMock->expects($this->once())
            ->method('isDemoMode')
            ->willReturn($isDemoMode);

        $this->partialPayments->method('getInfoBlock')->willReturn($infoBlockMock);
        $this->assertTrue($this->partialPayments->isDemoMode());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testIsDemoModeShouldReturnTrueDefault()
    {
        $infoBlockMock = ['some_random_data'];

        $this->partialPayments->method('getInfoBlock')->willReturn($infoBlockMock);
        $this->assertTrue($this->partialPayments->isDemoMode());
    }
}
