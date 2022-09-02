<?php declare(strict_types=1);

namespace Adyen\Payment\Tests\Unit\Helper;

use Adyen\Payment\Helper\Address;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Recurring;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Tests\Unit\AbstractAdyenTestCase;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class RequestsTest extends AbstractAdyenTestCase
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
        $this->setMockObjects(['storePaymentMethod' => true], true, Recurring::SUBSCRIPTION);
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

    private function setMockObjects(array $stateDataArray, bool $vaultEnabled, string $tokenType): void
    {
        $stateDataMock = $this->createConfiguredMock(StateData::class, [
            'getStateData' => $stateDataArray
        ]);

        $vaultHelperMock = $this->createConfiguredMock(Vault::class, [
            'isCardVaultEnabled' => $vaultEnabled
        ]);


        $configHelperMock = $this->createConfiguredMock(Config::class, [
            'getCardRecurringType' => $tokenType
        ]);

        $this->sut = new Requests(
            $this->createMock(Data::class),
            $configHelperMock,
            $this->createMock(Address::class),
            $stateDataMock,
            $this->createMock(PaymentMethods::class),
            $vaultHelperMock
        );

        $orderMock = $this->createConfiguredMock(Order::class, [
            'getQuoteId' => 1
        ]);
        $this->paymentMock = $this->createConfiguredMock(Payment::class, [
            'getOrder' => $orderMock
        ]);
    }
}
