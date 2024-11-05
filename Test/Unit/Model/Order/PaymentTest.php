<?php

namespace Adyen\Payment\Test\Unit\Model\Order;

use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use DateTime;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order\Payment\Repository as MagentoPaymentRepository;
use Magento\Sales\Api\Data\OrderPaymentInterface as MagentoPaymentInterface;
use Magento\Framework\Pricing\Helper\Data as PricingData;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    private $payment;
    private $pricingDataMock;
    private $magentoPaymentRepositoryMock;
    private $magentoPaymentMock;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $contextMock = $this->createMock(Context::class);
        $registryMock = $this->createMock(Registry::class);
        $this->pricingDataMock = $this->createMock(PricingData::class);
        $this->magentoPaymentRepositoryMock = $this->createMock(MagentoPaymentRepository::class);
        $this->magentoPaymentMock = $this->createMock(MagentoPaymentInterface::class);

        $this->payment = $objectManager->getObject(
            Payment::class,
            [
                'context' => $contextMock,
                'registry' => $registryMock,
                'pricingData' => $this->pricingDataMock,
                'magentoPaymentRepository' => $this->magentoPaymentRepositoryMock
            ]
        );
    }

    public function testGetAndSetPspReference()
    {
        $pspReference = 'test_psp_reference';
        $this->payment->setPspreference($pspReference);
        $this->assertEquals($pspReference, $this->payment->getPspreference());
    }

    public function testGetAndSetMerchantReference()
    {
        $merchantReference = 'test_merchant_reference';
        $this->payment->setMerchantReference($merchantReference);
        $this->assertEquals($merchantReference, $this->payment->getMerchantReference());
    }

    public function testGetAndSetPaymentId()
    {
        $paymentId = 123;
        $this->payment->setPaymentId($paymentId);
        $this->assertEquals($paymentId, $this->payment->getPaymentId());
    }

    public function testGetAndSetPaymentMethod()
    {
        $paymentMethod = 'test_payment_method';
        $this->payment->setPaymentMethod($paymentMethod);
        $this->assertEquals($paymentMethod, $this->payment->getPaymentMethod());
    }

    public function testGetAndSetAmount()
    {
        $amount = 100.0;
        $this->payment->setAmount($amount);
        $this->assertEquals($amount, $this->payment->getAmount());
    }

    public function testGetAndSetTotalRefunded()
    {
        $totalRefunded = 50.0;
        $this->payment->setTotalRefunded($totalRefunded);
        $this->assertEquals($totalRefunded, $this->payment->getTotalRefunded());
    }

    public function testGetAndSetCreatedAt()
    {
        $createdAt = new DateTime();
        $this->payment->setCreatedAt($createdAt);
        $this->assertEquals($createdAt, $this->payment->getCreatedAt());
    }

    public function testGetAndSetUpdatedAt()
    {
        $updatedAt = new DateTime();
        $this->payment->setUpdatedAt($updatedAt);
        $this->assertEquals($updatedAt, $this->payment->getUpdatedAt());
    }

    public function testGetAndSetCaptureStatus()
    {
        $captureStatus = 'Captured';
        $this->payment->setCaptureStatus($captureStatus);
        $this->assertEquals($captureStatus, $this->payment->getCaptureStatus());
    }

    public function testGetAndSetTotalCaptured()
    {
        $totalCaptured = 75.0;
        $this->payment->setTotalCaptured($totalCaptured);
        $this->assertEquals($totalCaptured, $this->payment->getTotalCaptured());
    }

    public function testSetAndGetSortOrder()
    {
        $sortOrder = 1;
        $this->payment->setSortOrder($sortOrder);
        $this->assertEquals($sortOrder, $this->payment->getSortOrder());
    }
}
