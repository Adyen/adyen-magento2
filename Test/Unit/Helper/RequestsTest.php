<?php declare(strict_types=1);

namespace Adyen\Payment\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class RequestsTest extends TestCase
{
    /** @var Requests $sut */
    private $sut;

    /** @var Payment $paymentMock */
    private $paymentMock;

    public function testBuildCardRecurringGuestNoStorePaymentMethod()
    {
        $this->setMockObjects([], false, '');
        $this->assertEmpty($this->sut->buildCardRecurringData(1, $this->paymentMock));
    }

    public function testBuildCardRecurringStorePaymentMethodFalse()
    {
        $this->setMockObjects(['storePaymentMethod' => false], false, '');
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertFalse($request['storePaymentMethod']);
    }

    public function testBuildCardRecurringStorePaymentMethodTrueVault()
    {
        $this->setMockObjects(['storePaymentMethod' => true], true, '');
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertTrue($request['storePaymentMethod']);
        $this->assertEquals(Recurring::SUBSCRIPTION, $request['recurringProcessingModel']);
    }

    public function testBuildCardRecurringStorePaymentMethodTrueAdyenCardOnFile()
    {
        $this->setMockObjects(['storePaymentMethod' => true], false, Recurring::CARD_ON_FILE);
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertTrue($request['storePaymentMethod']);
        $this->assertEquals(Recurring::CARD_ON_FILE, $request['recurringProcessingModel']);
    }

    public function testBuildCardRecurringStorePaymentMethodTrueAdyenSubscription()
    {
        $this->setMockObjects(['storePaymentMethod' => true], false, Recurring::SUBSCRIPTION);
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertTrue($request['storePaymentMethod']);
        $this->assertEquals(Recurring::SUBSCRIPTION, $request['recurringProcessingModel']);
    }

    private function setMockObjects(array $stateDataArray, bool $vaultEnabled, string $adyenTokenType): void
    {
        // Model\Order\Payment\Interceptor
        $stateDataMock = $this->createMock(StateData::class);
        $stateDataMock->method('getStateData')->willReturn($stateDataArray);

        $vaultHelperMock = $this->createMock(Vault::class);
        $vaultHelperMock->method('isCardVaultEnabled')->willReturn($vaultEnabled);

        $configHelperMock = $this->createMock(Config::class);
        $configHelperMock->method('getCardRecurringType')->willReturn($adyenTokenType);

        $this->sut = new Requests(
            $this->createMock(Data::class),
            $configHelperMock,
            $this->createMock(Address::class),
            $stateDataMock,
            $this->createMock(PaymentMethods::class),
            $vaultHelperMock
        );

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getQuoteId')->willReturn(1);
        $this->paymentMock = $this->createMock(Payment::class);
        $this->paymentMock->method('getOrder')->willReturn($orderMock);
    }
}
