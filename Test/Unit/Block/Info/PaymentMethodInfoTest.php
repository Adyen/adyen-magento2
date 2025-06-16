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

use Adyen\Payment\Block\Info\PaymentMethodInfo;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\ResourceModel\Order\Payment\Collection as OrderPaymentCollection;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\LayoutInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentMethodInfoTest extends AbstractAdyenTestCase
{
    protected ?PaymentMethodInfo $abstractInfo;
    protected Config|MockObject $configHelperMock;
    protected CollectionFactory|MockObject $adyenOrderPaymentCollectionFactoryMock;
    protected Context|MockObject $contextMock;
    protected ChargedCurrency|MockObject $chargedCurrencyMock;
    protected InfoInterface|MockObject $infoBlockMock;
    protected LayoutInterface|MockObject $layoutMock;
    protected array $data = [];

    /**
     * @return void
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->layoutMock = $this->createMock(LayoutInterface::class);
        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getLayout')->willReturn($this->layoutMock);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->adyenOrderPaymentCollectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->infoBlockMock = $this->createGeneratedMock(
            InfoInterface::class,
            [
                'encrypt',
                'decrypt',
                'setAdditionalInformation',
                'hasAdditionalInformation',
                'unsAdditionalInformation',
                'getAdditionalInformation',
                'getMethodInstance'
            ],
            ['getAdyenPspReference', 'getOrder', 'getId']
        );
        $this->data = [
            'info' => $this->infoBlockMock
        ];

        $this->abstractInfo = new PaymentMethodInfo(
            $this->adyenOrderPaymentCollectionFactoryMock,
            $this->configHelperMock,
            $this->contextMock,
            $this->chargedCurrencyMock,
            $this->data
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->abstractInfo = null;
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testGetAdyenPspReference()
    {
        $psprefereceMock = 'PSP_REFERENCE_MOCK';

        $this->infoBlockMock->expects($this->once())
            ->method('getAdyenPspReference')
            ->willReturn($psprefereceMock);

        $this->assertEquals($psprefereceMock, $this->abstractInfo->getAdyenPspReference());
    }

    /**
     * @return void
     * @throws Exception
     * @throws LocalizedException
     */
    public function testIsDemoMode()
    {
        $storeId = 1;
        $isDemoMode = true;

        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->expects($this->once())->method('getStoreId')->willReturn($storeId);

        $this->infoBlockMock->expects($this->once())->method('getOrder')->willReturn($orderMock);
        $this->configHelperMock->expects($this->once())
            ->method('getAdyenAbstractConfigDataFlag')
            ->willReturn('demo_mode', $storeId)
            ->willReturn($isDemoMode);

        $this->assertEquals($isDemoMode, $this->abstractInfo->isDemoMode());
    }

    /**
     * @return void
     * @throws Exception
     * @throws LocalizedException
     */
    public function testGetPartialPayments()
    {
        $paymentId = 1;

        $this->infoBlockMock->expects($this->once())->method('getId')->willReturn($paymentId);

        $orderPaymentCollection = $this->createMock(OrderPaymentCollection::class);
        $orderPaymentCollection->expects($this->once())
            ->method('addPaymentFilterAscending')
            ->with($paymentId)
            ->willReturnSelf();

        $this->adyenOrderPaymentCollectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($orderPaymentCollection);

        $this->assertInstanceOf(OrderPaymentCollection::class, $this->abstractInfo->getPartialPayments());
    }

    public function testRenderPartialPaymentsHtml()
    {
        $htmlMock = '<div>dummy</div>';

        $blockMock = $this->createGeneratedMock(
            AbstractBlock::class,
            ['toHtml', 'getLayout'],
            ['setInfoBlock']
        );
        $blockMock->method('setInfoBlock')->willReturnSelf();
        $blockMock->method('toHtml')->willReturn($htmlMock);

        $this->layoutMock->expects($this->once())
            ->method('createBlock', '', [])
            ->with("Adyen\Payment\Block\Info\PartialPayments")
            ->willReturn($blockMock);

        $this->assertEquals($htmlMock, $this->abstractInfo->renderPartialPaymentsHtml());
    }
}
