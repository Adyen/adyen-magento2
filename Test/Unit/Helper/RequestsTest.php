<?php declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Address;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Framework\App\RequestInterface;

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

    public function testBuildCardRecurringStorePaymentMethodTrueVault(): void
    {
        $this->setMockObjects(['storePaymentMethod' => true], true, Vault::SUBSCRIPTION);
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertTrue($request['storePaymentMethod']);
        $this->assertEquals(Vault::SUBSCRIPTION, $request['recurringProcessingModel']);
    }

    public function testBuildCardRecurringStorePaymentMethodTrueAdyenCardOnFile(): void
    {
        $this->setMockObjects(['storePaymentMethod' => true], true, Vault::CARD_ON_FILE);
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertTrue($request['storePaymentMethod']);
        $this->assertEquals(Vault::CARD_ON_FILE, $request['recurringProcessingModel']);
    }

    public function testBuildCardRecurringStorePaymentMethodTrueAdyenSubscription(): void
    {
        $this->setMockObjects(['storePaymentMethod' => true], true, Vault::SUBSCRIPTION);
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertTrue($request['storePaymentMethod']);
        $this->assertEquals(Vault::SUBSCRIPTION, $request['recurringProcessingModel']);
    }

    public function testBuildCardRecurringStorePaymentMethodFalse(): void
    {
        $this->setMockObjects(['storePaymentMethod' => false], false, Vault::SUBSCRIPTION);
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertArrayNotHasKey('storePaymentMethod', $request);
        $this->assertArrayNotHasKey('recurringProcessingModel', $request);
    }

    public function testBuildCardRecurringNoRecurringProcessingModel(): void
    {
        $this->setMockObjects(['storePaymentMethod' => true], true, '');
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertTrue($request['storePaymentMethod']);
        $this->assertArrayNotHasKey('recurringProcessingModel', $request);
    }

    public function testBuildCardRecurringWithEmptyStateData(): void
    {
        $this->setMockObjects([], true, Vault::SUBSCRIPTION);
        $request = $this->sut->buildCardRecurringData(1, $this->paymentMock);

        $this->assertArrayNotHasKey('storePaymentMethod', $request);
        $this->assertArrayNotHasKey('recurringProcessingModel', $request);
    }

    private function setMockObjects(array $stateDataArray, bool $vaultEnabled, string $tokenType): void
    {
        $stateDataMock = $this->createConfiguredMock(StateData::class, [
            'getStateData' => $stateDataArray
        ]);

        $vaultHelperMock = $this->createConfiguredMock(Vault::class, [
            'getPaymentMethodRecurringActive' => $vaultEnabled,
            'getPaymentMethodRecurringProcessingModel' => $tokenType
        ]);


        $configHelperMock = $this->createConfiguredMock(Config::class, [
            //'getPaymentMethodRecurringProcessingModel' => $tokenType
            //'getCardRecurringActive' => true
        ]);

        $this->sut = new Requests(
            $this->createMock(Data::class),
            $configHelperMock,
            $this->createMock(Address::class),
            $stateDataMock,
            $vaultHelperMock,
            $this->createMock(RequestInterface::class)
        );

        $orderMock = $this->createConfiguredMock(Order::class, [
            'getQuoteId' => 1
        ]);
        $this->paymentMock = $this->createConfiguredMock(Payment::class, [
            'getOrder' => $orderMock,
            'getMethod' => 'adyen_cc'
        ]);
    }
}
