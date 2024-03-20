<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\Api\GuestAdyenStateData;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\InputException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestAdyenStateDataTest extends AbstractAdyenTestCase
{
    private $objectManager;
    private $stateDataHelperMock;
    private $quoteIdMaskFactoryMock;
    private $quoteIdMaskMock;
    private $guestAdyenStateDataModel;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->quoteIdMaskFactoryMock = $this->createGeneratedMock(QuoteIdMaskFactory::class, [
            'create'
        ]);
        $this->stateDataHelperMock = $this->createMock(StateData::class);

        $this->quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $this->quoteIdMaskMock->method('load')->willReturn($this->quoteIdMaskMock);
        $this->quoteIdMaskMock->method('getQuoteId')->willReturn(1);

        $this->guestAdyenStateDataModel = $this->objectManager->getObject(GuestAdyenStateData::class, [
            'quoteIdMaskFactory' => $this->quoteIdMaskFactoryMock,
            'stateDataHelper' => $this->stateDataHelperMock
        ]);
    }

    public function testSaveSuccessful()
    {
        $stateData = '{"stateData":"dummyData"}';
        $cartId = 'ABC123456789';

        $stateDataMock = $this->createMock(\Adyen\Payment\Model\StateData::class);
        $stateDataMock->method('getEntityId')->willReturn(1);

        $this->quoteIdMaskFactoryMock->method('create')->willReturn($this->quoteIdMaskMock);
        $this->stateDataHelperMock->expects($this->once())->method('saveStateData')->willReturn($stateDataMock);

        $this->guestAdyenStateDataModel->save($stateData, $cartId);
    }

    public function testRemoveSuccessful()
    {
        $stateDataId = 1;
        $cartId = 'ABC123456789';

        $this->quoteIdMaskFactoryMock->method('create')->willReturn($this->quoteIdMaskMock);
        $this->stateDataHelperMock->expects($this->once())->method('removeStateData');
        $this->guestAdyenStateDataModel->remove($stateDataId, $cartId);
    }

    public function testSaveException()
    {
        $this->expectException(InputException::class);

        $stateData = '{"stateData":"dummyData"}';
        $cartId = '';

        $quoteIdMaskMock = $this->createGeneratedMock(QuoteIdMask::class, ['load', 'getQuoteId']);
        $quoteIdMaskMock->method('load')->willReturn($quoteIdMaskMock);
        $quoteIdMaskMock->method('getQuoteId')->willReturn(null);

        $this->quoteIdMaskFactoryMock->method('create')->willReturn($quoteIdMaskMock);

        $this->guestAdyenStateDataModel->save($stateData, $cartId);
    }
}
