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

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenCcDataAssignObserverTest extends AbstractAdyenTestCase
{
    protected ?AdyenCcDataAssignObserver $observer;
    protected CheckoutStateDataValidator|MockObject $checkoutStateDataValidatorMock;
    protected Collection|MockObject $stateDataCollectionMock;
    protected StateData|MockObject $stateDataMock;
    protected Vault|MockObject $vaultHelperMock;

    protected function setUp(): void
    {
        $this->checkoutStateDataValidatorMock = $this->createMock(CheckoutStateDataValidator::class);
        $this->stateDataCollectionMock = $this->createMock(Collection::class);
        $this->stateDataMock = $this->createMock(StateData::class);
        $this->vaultHelperMock = $this->createMock(Vault::class);

        $this->observer = new AdyenCcDataAssignObserver(
            $this->checkoutStateDataValidatorMock,
            $this->stateDataCollectionMock,
            $this->stateDataMock,
            $this->vaultHelperMock
        );
    }

    protected function tearDown(): void
    {
        $this->observer = null;
    }

    public function testExecuteRemovePreviousData()
    {
        $paymentMock = $this->createMock(Payment::class);

        $eventMock = $this->createMock(Event::class);
        $eventMock->method('getDataByKey')->willReturn($paymentMock);

        $observerMock = $this->createMock(Observer::class);
        $observerMock->method('getEvent')->willReturn($eventMock);

        $paymentMock->expects($this->atLeast(4))->method('unsAdditionalInformation');

        $this->observer->execute($observerMock);
    }
}
