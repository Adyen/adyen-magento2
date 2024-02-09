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

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\GiftcardDataBuilder;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class GiftcardDataBuilderTest extends AbstractAdyenTestCase
{
    private $giftcardDataBuilder;
    private $adyenStateDataCollectionMock;

    protected function setUp(): void
    {
        $this->adyenStateDataCollectionMock = $this->createMock(Collection::class);

        $objectManager = new ObjectManager($this);
        $this->giftcardDataBuilder = $objectManager->getObject(GiftcardDataBuilder::class, [
            'adyenStateData' => $this->adyenStateDataCollectionMock
        ]);
    }

    public function testBuild()
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getQuoteId')->willReturn(1);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $stateDataMock = [
            ['entity_id' => 1, 'state_data' => '{"paymentMethod":{"type":"giftcard","brand":"genericgiftcard"}}']
        ];

        $this->adyenStateDataCollectionMock->method('getStateDataRowsWithQuoteId')->willReturnSelf();
        $this->adyenStateDataCollectionMock->method('getData')->willReturn($stateDataMock);

        $request = $this->giftcardDataBuilder->build($buildSubject);
        $this->assertArrayHasKey('giftcardRequestParameters', $request['body']);
    }
}
