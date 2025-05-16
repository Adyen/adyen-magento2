<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Gateway\Request\DonationDataBuilder;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(DonationDataBuilder::class)]
class DonationDataBuilderTest extends AbstractAdyenTestCase
{
    private Requests $adyenRequestsHelper;
    private StoreManagerInterface $storeManager;
    private DonationDataBuilder $donationDataBuilder;

    protected function setUp(): void
    {
        $this->adyenRequestsHelper = $this->createMock(Requests::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->donationDataBuilder = new DonationDataBuilder(
            $this->adyenRequestsHelper,
            $this->storeManager
        );
    }

    #[Test]
    public function buildReturnsBodyWithDonationData(): void
    {
        $storeId = 1;
        $donationData = ['donationToken' => 'abc123'];

        $store = $this->createConfiguredMock(Store::class, [
            'getId' => $storeId
        ]);
        $this->storeManager->method('getStore')->willReturn($store);

        $payment = $this->createMock(InfoInterface::class);
        $paymentDataObject = $this->createConfiguredMock(PaymentDataObjectInterface::class, [
            'getPayment' => $payment
        ]);

        $this->adyenRequestsHelper
            ->expects($this->once())
            ->method('buildDonationData')
            ->with($payment, $storeId)
            ->willReturn($donationData);

        $result = $this->donationDataBuilder->build(['payment' => $paymentDataObject]);

        $this->assertArrayHasKey('body', $result);
        $this->assertSame($donationData, $result['body']);
    }

    #[Test]
    public function buildThrowsExceptionIfHelperFails(): void
    {
        $this->expectException(LocalizedException::class);

        $storeId = 1;
        $store = $this->createConfiguredMock(Store::class, [
            'getId' => $storeId
        ]);
        $this->storeManager->method('getStore')->willReturn($store);

        $payment = $this->createMock(InfoInterface::class);
        $paymentDataObject = $this->createConfiguredMock(PaymentDataObjectInterface::class, [
            'getPayment' => $payment
        ]);

        $this->adyenRequestsHelper
            ->method('buildDonationData')
            ->with($payment, $storeId)
            ->willThrowException(new LocalizedException(__('Donation failed')));

        $this->donationDataBuilder->build(['payment' => $paymentDataObject]);
    }
}
