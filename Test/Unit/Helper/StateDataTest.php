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

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\StateData as StateDataResourceModel;
use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Model\StateDataFactory;

class StateDataTest extends AbstractAdyenTestCase
{
    public function testRemoveStateData($stateDataId = 1, $quoteId = 1)
    {

    }

    protected function buildStateDataHelper(
        $stateDataCollectionMock = null,
        $stateDataFactoryMock = null,
        $stateDataResourceModelMock = null,
        $checkoutStateDataValidatorMock = null,
        $adyenLoggerMock = null
    ) {
        if (is_null($stateDataCollectionMock)) {
            $stateDataCollectionMock = $this->createMock(StateDataCollection::class);
        }

        if (is_null($stateDataFactoryMock)) {
            $stateDataFactoryMock = $this->createGeneratedMock(StateDataFactory::class);
        }

        if (is_null($stateDataResourceModelMock)) {
            $stateDataResourceModelMock = $this->createMock(StateDataResourceModel::class);
        }

        if (is_null($checkoutStateDataValidatorMock)) {
            $checkoutStateDataValidatorMock = $this->createMock(CheckoutStateDataValidator::class);
        }

        if (is_null($adyenLoggerMock)) {
            $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        }

        return new StateData(
            $stateDataCollectionMock,
            $stateDataFactoryMock,
            $stateDataResourceModelMock,
            $checkoutStateDataValidatorMock,
            $adyenLoggerMock
        );
    }
}
