<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Observer\AdyenPosCloudDataAssignObserver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenPosCloudDataAssignObserverTest extends AbstractAdyenTestCase
{
    private MockObject|Payment $paymentInfo;
    private AdyenPosCloudDataAssignObserver $observer;

    protected function setUp(): void
    {
        $this->paymentInfo = $this->createGeneratedMock(
            Payment::class,
            ['setAdditionalInformation'],
            ['setCcType']
        );
        $this->observer = new AdyenPosCloudDataAssignObserver();
    }

    public function testExecuteWithNoAdditionalData()
    {
        $dataObject = new DataObject();
        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->expects($this->never())->method('setCcType');
        $this->paymentInfo->expects($this->never())->method('setAdditionalInformation');

        $this->observer->execute($observer);
    }

    public function testExecuteWithAllAdditionalData()
    {
        $additionalData = [
            'terminal_id' => 'P400Plus-123456789',
            'number_of_installments' => '3',
            'funding_source' => 'credit',
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

        $this->paymentInfo->expects($this->exactly(3))
            ->method('setAdditionalInformation');

        $this->observer->execute($observer);
    }

    public function testExecuteWithPartialAdditionalData()
    {
        $additionalData = [
            'terminal_id' => 'P400Plus-123456789',
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
            ->with('terminal_id', 'P400Plus-123456789');

        $this->observer->execute($observer);
    }

    public function testExecuteIgnoresUnapprovedKeys()
    {
        $additionalData = [
            'terminal_id' => 'P400Plus-123456789',
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

        // Only terminal_id should be set, unknown_key should be ignored
        $this->paymentInfo->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('terminal_id', 'P400Plus-123456789');

        $this->observer->execute($observer);
    }

    public function testExecuteWithEmptyApprovedKeys()
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
