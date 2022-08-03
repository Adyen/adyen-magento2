<?php declare(strict_types=1);

/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Tests\Unit;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Order\Payment\Collection;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection as OrderStatusCollection;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as OrderStatusCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

abstract class AbstractAdyenTestCase extends TestCase
{
    /**
     * Mock a class dynamically generated by Magento
     */
    protected function createGeneratedMock(string $originalClassName, array $additionalMethods = []): MockObject
    {
        return $this->getMockBuilder($originalClassName)
            ->setMethods($additionalMethods)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
    }

    protected function createOrder(?string $status = null)
    {
        $orderPaymentMock = $this->createConfiguredMock(MagentoOrder\Payment::class, ['getMethod' => 'adyen_cc']);

        return $this->createConfiguredMock(MagentoOrder::class, [
            'getStatus' => $status,
            'getPayment' => $orderPaymentMock,
        ]);
    }

    protected function createWebhook(?string $originalReference = null)
    {
        return $this->createConfiguredMock(Notification::class, [
            'getAmountValue' => 1000,
            'getEventCode' => 'AUTHORISATION',
            'getAmountCurrency' => 'EUR',
            'getOriginalReference' => $originalReference
        ]);
    }

    protected function createOrderStatusCollection($state): MockObject
    {
        $orderStatus = $this->createGeneratedMock(MagentoOrder\Status::class, ['getState']);
        $orderStatus->method('getState')->willReturn($state);

        $orderStatusCollection = $this->createConfiguredMock(OrderStatusCollection::class, []);
        $orderStatusCollection->method('addFieldToFilter')->willReturn($orderStatusCollection);
        $orderStatusCollection->method('joinStates')->willReturn($orderStatusCollection);
        $orderStatusCollection->method('addStateFilter')->willReturn($orderStatusCollection);
        $orderStatusCollection->method('getFirstItem')->willReturn($orderStatus);

        $orderStatusCollectionFactory = $this->createGeneratedMock(OrderStatusCollectionFactory::class, ['create']);
        $orderStatusCollectionFactory->method('create')->willReturn($orderStatusCollection);

        return $orderStatusCollectionFactory;
    }

    protected function createAdyenOrderPaymentCollection(?int $entityId = null): MockObject
    {
        $adyenOrderPayment = $this->createConfiguredMock(OrderPaymentInterface::class, ['getEntityId' => $entityId]);

        $adyenOrderPaymentCollection = $this->createConfiguredMock(Collection::class, [
            'getFirstItem' => $adyenOrderPayment
        ]);
        $adyenOrderPaymentCollection->method('addFieldToFilter')->willReturn($adyenOrderPaymentCollection);

        $adyenOrderPaymentCollectionFactory = $this->createGeneratedMock(OrderPaymentCollectionFactory::class, ['create']);
        $adyenOrderPaymentCollectionFactory->method('create')->willReturn($adyenOrderPaymentCollection);

        return $adyenOrderPaymentCollectionFactory;
    }
}
