<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Observer\AdyenPayByLinkDataAssignObserver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenPayByLinkDataAssignObserverTest extends AbstractAdyenTestCase
{
    private MockObject|Payment $paymentInfo;
    private AdyenPayByLinkDataAssignObserver $observer;

    protected function setUp(): void
    {
        $this->paymentInfo = $this->createGeneratedMock(
            Payment::class,
            ['setAdditionalInformation'],
            ['setCcType']
        );
        $this->observer = new AdyenPayByLinkDataAssignObserver();
    }

    public function testExecuteWithNoAdditionalData()
    {
        $dataObject = new DataObject();
        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->expects($this->once())
            ->method('setCcType')
            ->with(null);

        $this->paymentInfo->expects($this->never())
            ->method('setAdditionalInformation');

        $this->observer->execute($observer);
    }

    public function testExecuteWithExpiryDate()
    {
        $additionalData = [
            'adyen_pbl_expires_at' => '2026-04-01',
        ];

        $dataObject = new DataObject([
            PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
        ]);

        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->expects($this->once())
            ->method('setCcType')
            ->with(null);

        $this->paymentInfo->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('adyen_pbl_expires_at', '2026-04-01');

        $this->observer->execute($observer);
    }

    public function testExecuteIgnoresUnapprovedKeys()
    {
        $additionalData = [
            'adyen_pbl_expires_at' => '2026-04-01',
            'unknown_key' => 'should_be_ignored',
        ];

        $dataObject = new DataObject([
            PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
        ]);

        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->expects($this->once())
            ->method('setCcType')
            ->with(null);

        $this->paymentInfo->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('adyen_pbl_expires_at', '2026-04-01');

        $this->observer->execute($observer);
    }

    public function testExecuteWithOnlyUnapprovedKeys()
    {
        $additionalData = [
            'unknown_key' => 'should_be_ignored',
        ];

        $dataObject = new DataObject([
            PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
        ]);

        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->expects($this->once())
            ->method('setCcType')
            ->with(null);

        $this->paymentInfo->expects($this->never())
            ->method('setAdditionalInformation');

        $this->observer->execute($observer);
    }

    /**
     * @param array $returnMap
     * @return MockObject|Observer
     */
    private function getPreparedObserverWithMap(array $returnMap): Observer|MockObject
    {
        $observer = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->getMock();

        $observer->expects(static::atLeastOnce())
            ->method('getEvent')
            ->willReturn($event);
        $event->expects(static::atLeastOnce())
            ->method('getDataByKey')
            ->willReturnMap(
                $returnMap
            );

        return $observer;
    }
}
