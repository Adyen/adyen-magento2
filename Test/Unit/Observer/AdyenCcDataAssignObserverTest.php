<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Util\CheckoutStateDataValidator;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenCcDataAssignObserverTest extends AbstractAdyenTestCase
{
    private MockObject|CheckoutStateDataValidator $checkoutStateDataValidator;
    private MockObject|Collection $stateDataCollection;
    private MockObject|StateData $stateData;
    private MockObject|Vault $vaultHelper;
    private MockObject|Payment $paymentInfo;
    private AdyenCcDataAssignObserver $observer;

    protected function setUp(): void
    {
        $this->checkoutStateDataValidator = $this->createMock(CheckoutStateDataValidator::class);
        $this->stateDataCollection = $this->createMock(Collection::class);
        $this->stateData = $this->createMock(StateData::class);
        $this->vaultHelper = $this->createMock(Vault::class);
        $this->paymentInfo = $this->createGeneratedMock(
            Payment::class,
            ['unsAdditionalInformation', 'setAdditionalInformation', 'getData'],
            ['setCcType']
        );
        $this->observer = new AdyenCcDataAssignObserver(
            $this->checkoutStateDataValidator,
            $this->stateDataCollection,
            $this->stateData,
            $this->vaultHelper
        );
    }

    public function testExecuteWithNoAdditionalData()
    {
        $dataObject = new DataObject();
        $observer = $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $this->paymentInfo],
        ]);

        $this->paymentInfo->expects($this->never())->method('unsAdditionalInformation');
        $this->paymentInfo->expects($this->never())->method('setCcType');
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

        $this->paymentInfo->expects($this->exactly(2))->method('unsAdditionalInformation');

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

        // combo_card_type + number_of_installments (stateData and cc_type are unset before this loop)
        $this->paymentInfo->expects($this->exactly(2))->method('setAdditionalInformation');

        $this->observer->execute($observer);
    }

    public function testExecuteWithStateDataFromDatabase()
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

        $this->paymentInfo->method('getData')
            ->with('quote_id')
            ->willReturn(456);

        $dbStateData = ['paymentMethod' => ['type' => 'scheme']];
        $this->stateDataCollection->expects($this->once())
            ->method('getStateDataArrayWithQuoteId')
            ->with(456)
            ->willReturn($dbStateData);

        $validatedStateData = ['paymentMethod' => ['type' => 'scheme']];
        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
            ->willReturn($validatedStateData);

        $this->stateData->expects($this->once())
            ->method('setStateData')
            ->with($validatedStateData, 456);

        $this->paymentInfo->expects($this->once())
            ->method('setCcType')
            ->with(null);

        $this->observer->execute($observer);
    }

    public function testExecuteWithGiftcardStateDataSkipsValidation()
    {
        $stateDataJson = json_encode(['paymentMethod' => ['type' => 'giftcard']]);
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

        $this->checkoutStateDataValidator->expects($this->never())
            ->method('getValidatedAdditionalData');

        $this->stateData->expects($this->never())
            ->method('setStateData');

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

    public function testExecuteWithValidRecurringProcessingModel()
    {
        $additionalData = [
            'stateData' => json_encode(['paymentMethod' => ['type' => 'scheme']]),
            'recurringProcessingModel' => 'Subscription',
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

        $this->vaultHelper->expects($this->once())
            ->method('validateRecurringProcessingModel')
            ->with('Subscription')
            ->willReturn(true);

        // No CC-specific keys or invalid recurringProcessingModel → unsAdditionalInformation must not be called
        $this->paymentInfo->expects($this->never())
            ->method('unsAdditionalInformation');

        // recurringProcessingModel should be set as additional information
        $this->paymentInfo->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('recurringProcessingModel', 'Subscription');

        $this->observer->execute($observer);
    }

    public function testExecuteWithInvalidRecurringProcessingModel()
    {
        $additionalData = [
            'stateData' => json_encode(['paymentMethod' => ['type' => 'scheme']]),
            'recurringProcessingModel' => 'invalid',
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

        $this->vaultHelper->expects($this->once())
            ->method('validateRecurringProcessingModel')
            ->with('invalid')
            ->willReturn(false);

        // Only recurringProcessingModel is unset (no installments/combo_card_type in request)
        $this->paymentInfo->expects($this->once())
            ->method('unsAdditionalInformation')
            ->with(AdyenCcDataAssignObserver::RECURRING_PROCESSING_MODEL);

        // recurringProcessingModel should NOT be set as additional information
        $this->paymentInfo->expects($this->never())
            ->method('setAdditionalInformation');

        $this->observer->execute($observer);
    }

    public function testExecuteCcTypeSetToNullWhenNotProvided()
    {
        $additionalData = [
            'guestEmail' => 'test@example.com',
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

        $this->stateDataCollection->method('getStateDataArrayWithQuoteId')
            ->willReturn([]);

        $this->paymentInfo->expects($this->once())
            ->method('setCcType')
            ->with(null);

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
