<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Adyen\Payment\Observer\AdyenMotoDataAssignObserver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenMotoDataAssignObserverTest extends AbstractAdyenTestCase
{
    private MockObject|CheckoutStateDataValidator $checkoutStateDataValidator;
    private MockObject|Collection $stateDataCollection;
    private MockObject|StateData $stateData;
    private MockObject|Payment $paymentInfo;
    private AdyenMotoDataAssignObserver $observer;

    protected function setUp(): void
    {
        $this->checkoutStateDataValidator = $this->createMock(CheckoutStateDataValidator::class);
        $this->stateDataCollection = $this->createMock(Collection::class);
        $this->stateData = $this->createMock(StateData::class);
        $this->paymentInfo = $this->createGeneratedMock(
            Payment::class,
            ['unsAdditionalInformation', 'setAdditionalInformation', 'getData'],
            ['setCcType']
        );
        $this->observer = new AdyenMotoDataAssignObserver(
            $this->checkoutStateDataValidator,
            $this->stateDataCollection,
            $this->stateData
        );
    }

    public function testExecuteWithNoAdditionalData()
    {
        $dataObject = new DataObject();
        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->expects($this->once())->method('unsAdditionalInformation')
            ->with('cc_type');
        $this->stateData->expects($this->never())->method('setStateData');
        $this->paymentInfo->expects($this->never())->method('setAdditionalInformation');

        $this->observer->execute($observer);
    }

    public function testExecuteWithStateDataFromFrontend()
    {
        $stateDataJson = json_encode(['paymentMethod' => ['type' => 'scheme']]);
        $additionalData = [
            'stateData' => $stateDataJson,
            'combo_card_type' => 'credit',
            'number_of_installments' => '3',
            'cc_type' => 'visa',
        ];

        $dataObject = new DataObject([
            PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
        ]);

        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->method('getData')
            ->with('quote_id')
            ->willReturn(123);

        $validatedStateData = ['paymentMethod' => ['type' => 'scheme']];
        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
            ->willReturn($validatedStateData);

        $this->stateData->expects($this->once())
            ->method('setStateData')
            ->with($validatedStateData, 123);

        $this->paymentInfo->expects($this->once())
            ->method('setCcType')
            ->with('visa');

        // combo_card_type + number_of_installments (stateData and cc_type are unset before the loop)
        $this->paymentInfo->expects($this->exactly(2))->method('setAdditionalInformation');

        $this->observer->execute($observer);
    }

    public function testExecuteWithEmptyStateData()
    {
        $additionalData = [
            'number_of_installments' => '3',
        ];

        $dataObject = new DataObject([
            PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
        ]);

        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->checkoutStateDataValidator->expects($this->never())
            ->method('getValidatedAdditionalData');

        $this->stateData->expects($this->never())
            ->method('setStateData');

        $this->paymentInfo->expects($this->once())
            ->method('setCcType')
            ->with(null);

        // number_of_installments
        $this->paymentInfo->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('number_of_installments', '3');

        $this->observer->execute($observer);
    }

    public function testExecuteCcTypeSetToNullWhenNotProvided()
    {
        $additionalData = [
            'stateData' => json_encode(['paymentMethod' => ['type' => 'scheme']]),
        ];

        $dataObject = new DataObject([
            PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
        ]);

        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->method('getData')
            ->with('quote_id')
            ->willReturn(1);

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')
            ->willReturn(['paymentMethod' => ['type' => 'scheme']]);

        $this->paymentInfo->expects($this->once())
            ->method('setCcType')
            ->with(null);

        $this->observer->execute($observer);
    }

    public function testExecuteWithStorePaymentMethod()
    {
        $stateDataJson = json_encode(['paymentMethod' => ['type' => 'scheme']]);
        $additionalData = [
            'stateData' => $stateDataJson,
        ];

        $dataObject = new DataObject([
            PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
        ]);

        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->method('getData')
            ->with('quote_id')
            ->willReturn(1);

        $validatedStateData = [
            'paymentMethod' => ['type' => 'scheme'],
            'storePaymentMethod' => true,
        ];
        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
            ->willReturn($validatedStateData);

        $this->stateData->expects($this->once())->method('setStateData');

        $this->paymentInfo->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('store_cc', true);

        $this->observer->execute($observer);
    }

    public function testExecuteWithMotoMerchantAccount()
    {
        $stateDataJson = json_encode(['paymentMethod' => ['type' => 'scheme']]);
        $additionalData = [
            'stateData' => $stateDataJson,
        ];

        $dataObject = new DataObject([
            PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
        ]);

        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->method('getData')
            ->with('quote_id')
            ->willReturn(1);

        $validatedStateData = [
            'paymentMethod' => ['type' => 'scheme'],
            'motoMerchantAccount' => 'TestMerchantMOTO',
        ];
        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
            ->willReturn($validatedStateData);

        $this->stateData->expects($this->once())->method('setStateData');

        $this->paymentInfo->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('motoMerchantAccount', 'TestMerchantMOTO');

        $this->observer->execute($observer);
    }

    public function testExecuteWithStorePaymentMethodAndMotoMerchantAccount()
    {
        $stateDataJson = json_encode(['paymentMethod' => ['type' => 'scheme']]);
        $additionalData = [
            'stateData' => $stateDataJson,
        ];

        $dataObject = new DataObject([
            PaymentInterface::KEY_ADDITIONAL_DATA => $additionalData,
        ]);

        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->method('getData')
            ->with('quote_id')
            ->willReturn(1);

        $validatedStateData = [
            'paymentMethod' => ['type' => 'scheme'],
            'storePaymentMethod' => true,
            'motoMerchantAccount' => 'TestMerchantMOTO',
        ];
        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
            ->willReturn($validatedStateData);

        $this->stateData->expects($this->once())->method('setStateData');

        // store_cc + motoMerchantAccount
        $this->paymentInfo->expects($this->exactly(2))
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
