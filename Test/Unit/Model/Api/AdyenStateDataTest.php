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

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\Api\AdyenStateData;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class AdyenStateDataTest extends AbstractAdyenTestCase
{
    private $objectManager;
    private $stateDataHelperMock;
    private $adyenStateDataModel;

    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);

        $this->stateDataHelperMock = $this->createMock(StateData::class);

        $this->adyenStateDataModel = $this->objectManager->getObject(AdyenStateData::class, [
            'stateDataHelper' => $this->stateDataHelperMock
        ]);
    }

    public function testSaveSuccessful()
    {
        $stateData = '{"stateData":"dummyData"}';
        $cartId = 100;

        $stateDataMock = $this->createMock(\Adyen\Payment\Model\StateData::class);
        $stateDataMock->method('getEntityId')->willReturn(1);

        $this->stateDataHelperMock->expects($this->once())
            ->method('saveStateData')
            ->willReturn($stateDataMock);

        $this->adyenStateDataModel->save($stateData, $cartId);
    }

    public function testRemoveSuccessful()
    {
        $stateDataId = 1;
        $cartId = 100;

        $this->stateDataHelperMock->expects($this->once())->method('removeStateData');
        $this->adyenStateDataModel->remove($stateDataId, $cartId);
    }
}
