<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Observer;

use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilderInterface;
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
use Magento\Vault\Api\Data\PaymentTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AdyenCcDataAssignObserverTest extends AbstractAdyenTestCase
{
    const QUOTE_ID = 1;

    private MockObject|CheckoutStateDataValidator $checkoutStateDataValidator;
    private MockObject|Collection $stateDataCollection;
    private MockObject|StateData $stateData;
    private MockObject|Vault $vaultHelper;
    private MockObject|Payment $paymentInfo;
    private AdyenCcDataAssignObserver $observer;

    private array $setAdditionalInformationCalls = [];
    private array $unsAdditionalInformationCalls = [];

    protected function setUp(): void
    {
        $this->checkoutStateDataValidator = $this->createMock(CheckoutStateDataValidator::class);
        $this->stateDataCollection = $this->createMock(Collection::class);
        $this->stateData = $this->createMock(StateData::class);
        $this->vaultHelper = $this->createMock(Vault::class);

        $this->paymentInfo = $this->createMockWithMethods(
            Payment::class,
            ['setAdditionalInformation', 'unsAdditionalInformation', 'getData'],
            ['setCcType']
        );
        $this->paymentInfo->method('getData')->with('quote_id')->willReturn(self::QUOTE_ID);

        $this->setAdditionalInformationCalls = [];
        $this->unsAdditionalInformationCalls = [];

        $this->paymentInfo->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value = null) {
                $this->setAdditionalInformationCalls[$key] = $value;
                return $this->paymentInfo;
            });

        $this->paymentInfo->method('unsAdditionalInformation')
            ->willReturnCallback(function ($key = null) {
                $this->unsAdditionalInformationCalls[] = $key;
                return $this->paymentInfo;
            });

        $this->observer = new AdyenCcDataAssignObserver(
            $this->checkoutStateDataValidator,
            $this->stateDataCollection,
            $this->stateData,
            $this->vaultHelper
        );
    }

    public function testExecuteDoesNothingWhenAdditionalDataIsNotAnArray()
    {
        $paymentInfo = $this->createMock(Payment::class);
        $paymentInfo->expects($this->never())->method('setAdditionalInformation');
        $paymentInfo->expects($this->never())->method('unsAdditionalInformation');
        $this->stateDataCollection->expects($this->never())->method('getStateDataArrayWithQuoteId');

        $observer = $this->getPreparedObserver(
            new DataObject([PaymentInterface::KEY_ADDITIONAL_DATA => 'not-an-array']),
            $paymentInfo
        );

        $this->observer->execute($observer);
    }

    public function testExecuteUnsetsCcSpecificKeysWhenTheyArePresentInTheRequest()
    {
        $this->stateDataCollection->method('getStateDataArrayWithQuoteId')->willReturn([]);

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::CC_TYPE => 'VI',
                    AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS => 3,
                    AdyenCcDataAssignObserver::COMBO_CARD_TYPE => 'debit'
                ]
            ]),
            $this->paymentInfo
        );

        $this->observer->execute($observer);

        $this->assertEquals(
            [
                AdyenCcDataAssignObserver::CC_TYPE,
                AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS,
                AdyenCcDataAssignObserver::COMBO_CARD_TYPE
            ],
            $this->unsAdditionalInformationCalls
        );
    }

    public function testExecuteKeepsPreviousCcDataWhenNoReplacementIsProvided()
    {
        $this->stateDataCollection->method('getStateDataArrayWithQuoteId')->willReturn([]);

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::GUEST_EMAIL => 'shopper@adyen.com'
                ]
            ]),
            $this->paymentInfo
        );

        $this->observer->execute($observer);

        $this->assertSame([], $this->unsAdditionalInformationCalls);
        $this->assertSame(
            [AdyenCcDataAssignObserver::GUEST_EMAIL => 'shopper@adyen.com'],
            $this->setAdditionalInformationCalls
        );
    }

    public function testExecuteAssignsOnlyApprovedAdditionalDataKeys()
    {
        $this->stateDataCollection->method('getStateDataArrayWithQuoteId')->willReturn([]);

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::CC_TYPE => 'VI',
                    AdyenCcDataAssignObserver::GUEST_EMAIL => 'shopper@adyen.com',
                    AdyenCcDataAssignObserver::RETURN_URL => 'https://adyen.com/return',
                    PaymentTokenInterface::PUBLIC_HASH => 'publicHash',
                    HeaderDataBuilderInterface::ADDITIONAL_DATA_FRONTEND_TYPE_KEY => 'headless',
                    AdyenCcDataAssignObserver::DEVICE_FINGERPRINT => 'spoofedFingerprint',
                    'notApprovedKey' => 'notApprovedValue'
                ]
            ]),
            $this->paymentInfo
        );

        $this->paymentInfo->expects($this->once())->method('setCcType')->with('VI');

        $this->observer->execute($observer);

        $this->assertSame(
            [
                AdyenCcDataAssignObserver::GUEST_EMAIL,
                AdyenCcDataAssignObserver::CC_TYPE,
                AdyenCcDataAssignObserver::RETURN_URL,
                PaymentTokenInterface::PUBLIC_HASH,
                HeaderDataBuilderInterface::ADDITIONAL_DATA_FRONTEND_TYPE_KEY
            ],
            array_keys($this->setAdditionalInformationCalls)
        );
    }

    public function testExecuteStoresStateDataFromTheRequestAndRemovesItFromAdditionalData()
    {
        $stateData = ['paymentMethod' => ['type' => 'scheme']];

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::STATE_DATA => json_encode($stateData)
                ]
            ]),
            $this->paymentInfo
        );

        $this->stateDataCollection->expects($this->never())->method('getStateDataArrayWithQuoteId');
        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
            ->with($stateData)
            ->willReturn($stateData);
        $this->stateData->expects($this->once())
            ->method('setStateData')
            ->with($stateData, self::QUOTE_ID);

        $this->observer->execute($observer);

        $this->assertArrayNotHasKey(
            AdyenCcDataAssignObserver::STATE_DATA,
            $this->setAdditionalInformationCalls
        );
    }

    public function testExecuteFetchesStateDataFromDatabaseWhenNotProvidedInTheRequest()
    {
        $stateData = ['paymentMethod' => ['type' => 'scheme']];

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::CC_TYPE => 'VI'
                ]
            ]),
            $this->paymentInfo
        );

        $this->stateDataCollection->expects($this->once())
            ->method('getStateDataArrayWithQuoteId')
            ->with(self::QUOTE_ID)
            ->willReturn($stateData);
        $this->checkoutStateDataValidator->expects($this->once())
            ->method('getValidatedAdditionalData')
            ->with($stateData)
            ->willReturn($stateData);
        $this->stateData->expects($this->once())
            ->method('setStateData')
            ->with($stateData, self::QUOTE_ID);

        $this->observer->execute($observer);
    }

    public function testExecuteSetsStoreCcWhenStorePaymentMethodIsEnabled()
    {
        $stateData = [
            'paymentMethod' => ['type' => 'scheme'],
            AdyenCcDataAssignObserver::STORE_PAYMENT_METHOD => true
        ];

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::STATE_DATA => json_encode($stateData)
                ]
            ]),
            $this->paymentInfo
        );

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn($stateData);

        $this->observer->execute($observer);

        $this->assertSame(
            [AdyenCcDataAssignObserver::STORE_CC => true],
            $this->setAdditionalInformationCalls
        );
    }

    public function testExecuteDoesNotProcessGiftcardStateData()
    {
        $stateData = ['paymentMethod' => ['type' => 'giftcard']];

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::STATE_DATA => json_encode($stateData)
                ]
            ]),
            $this->paymentInfo
        );

        $this->checkoutStateDataValidator->expects($this->never())->method('getValidatedAdditionalData');
        $this->stateData->expects($this->never())->method('setStateData');

        $this->observer->execute($observer);
    }

    public function testExecuteStoresDeviceFingerprintFromRiskDataClientData()
    {
        $stateData = [
            'paymentMethod' => ['type' => 'scheme'],
            'riskData' => [
                'clientData' => base64_encode(json_encode(['deviceFingerprint' => 'testDeviceFingerprint']))
            ]
        ];

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::STATE_DATA => json_encode($stateData)
                ]
            ]),
            $this->paymentInfo
        );

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn($stateData);

        $this->observer->execute($observer);

        $this->assertSame(
            'testDeviceFingerprint',
            $this->setAdditionalInformationCalls[AdyenCcDataAssignObserver::DEVICE_FINGERPRINT]
        );
    }

    public function testExecuteDoesNotStoreDeviceFingerprintWhenClientDataIsNotValidJson()
    {
        $stateData = [
            'paymentMethod' => ['type' => 'scheme'],
            'riskData' => ['clientData' => base64_encode('not-a-json-string')]
        ];

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::STATE_DATA => json_encode($stateData)
                ]
            ]),
            $this->paymentInfo
        );

        $this->checkoutStateDataValidator->method('getValidatedAdditionalData')->willReturn($stateData);

        $this->observer->execute($observer);

        $this->assertArrayNotHasKey(
            AdyenCcDataAssignObserver::DEVICE_FINGERPRINT,
            $this->setAdditionalInformationCalls
        );
    }

    public function testExecuteRemovesInvalidRecurringProcessingModel()
    {
        $this->stateDataCollection->method('getStateDataArrayWithQuoteId')->willReturn([]);

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::CC_TYPE => 'VI',
                    AdyenCcDataAssignObserver::RECURRING_PROCESSING_MODEL => 'InvalidModel'
                ]
            ]),
            $this->paymentInfo
        );

        $this->vaultHelper->expects($this->once())
            ->method('validateRecurringProcessingModel')
            ->with('InvalidModel')
            ->willReturn(false);

        $this->observer->execute($observer);

        $this->assertContains(
            AdyenCcDataAssignObserver::RECURRING_PROCESSING_MODEL,
            $this->unsAdditionalInformationCalls
        );
        $this->assertArrayNotHasKey(
            AdyenCcDataAssignObserver::RECURRING_PROCESSING_MODEL,
            $this->setAdditionalInformationCalls
        );
    }

    public function testExecuteKeepsValidRecurringProcessingModel()
    {
        $this->stateDataCollection->method('getStateDataArrayWithQuoteId')->willReturn([]);

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::RECURRING_PROCESSING_MODEL => 'Subscription'
                ]
            ]),
            $this->paymentInfo
        );

        $this->vaultHelper->expects($this->once())
            ->method('validateRecurringProcessingModel')
            ->with('Subscription')
            ->willReturn(true);

        $this->observer->execute($observer);

        $this->assertSame(
            [AdyenCcDataAssignObserver::RECURRING_PROCESSING_MODEL => 'Subscription'],
            $this->setAdditionalInformationCalls
        );
    }

    public function testExecuteDoesNotSetCcTypeWhenItIsNotProvided()
    {
        $this->stateDataCollection->method('getStateDataArrayWithQuoteId')->willReturn([]);

        $observer = $this->getPreparedObserver(
            new DataObject([
                PaymentInterface::KEY_ADDITIONAL_DATA => [
                    AdyenCcDataAssignObserver::GUEST_EMAIL => 'shopper@adyen.com'
                ]
            ]),
            $this->paymentInfo
        );

        $this->paymentInfo->expects($this->never())->method('setCcType');

        $this->observer->execute($observer);
    }

    private function getPreparedObserver(DataObject $dataObject, Payment|MockObject $paymentInfo): Observer|MockObject
    {
        return $this->getPreparedObserverWithMap([
            [AbstractDataAssignObserver::DATA_CODE, $dataObject],
            [AbstractDataAssignObserver::MODEL_CODE, $paymentInfo],
        ]);
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
            ->willReturnMap($returnMap);

        return $observer;
    }
}
