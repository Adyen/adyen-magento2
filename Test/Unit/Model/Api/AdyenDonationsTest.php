<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\Api\AdyenDonations;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(AdyenDonations::class)]
class AdyenDonationsTest extends AbstractAdyenTestCase
{
    private AdyenDonations $adyenDonations;

    private CommandPoolInterface $commandPool;
    private Json $jsonSerializer;
    private Data $dataHelper;
    private OrderRepository $orderRepository;
    private PaymentDataObjectFactoryInterface $paymentDataObjectFactory;

    protected function setUp(): void
    {
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->jsonSerializer = $this->createMock(Json::class);
        $this->dataHelper = $this->createMock(Data::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->paymentDataObjectFactory = $this->createMock(PaymentDataObjectFactoryInterface::class);

        $this->adyenDonations = new AdyenDonations(
            $this->commandPool,
            $this->jsonSerializer,
            $this->dataHelper,
            $this->orderRepository,
            $this->paymentDataObjectFactory
        );
    }

    #[Test]
    public function makeDonationSuccess(): void
    {
        $payloadString = '{"amount":100}';
        $payloadArray = ['amount' => 100];

        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $paymentDO = $this->createMock(PaymentDataObject::class);
        $command = $this->createMock(CommandInterface::class);

        $order->method('getPayment')->willReturn($payment);
        $this->jsonSerializer->method('unserialize')->with($payloadString)->willReturn($payloadArray);

        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('donationPayload', $payloadArray);

        $this->paymentDataObjectFactory->method('create')->with($payment)->willReturn($paymentDO);
        $this->commandPool->method('get')->with('capture')->willReturn($command);
        $command->expects($this->once())->method('execute')->with(['payment' => $paymentDO]);

        $this->orderRepository->expects($this->once())->method('save')->with($order);

        $this->adyenDonations->makeDonation($payloadString, $order);
    }
}