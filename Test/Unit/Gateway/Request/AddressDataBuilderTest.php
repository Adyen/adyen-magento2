<?php

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\AddressDataBuilder;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\Requests;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AddressDataBuilderTest extends AbstractAdyenTestCase
{
    /**
     * @var AddressDataBuilder
     */
    private $addressDataBuilder;

    /**
     * @var Requests|MockObject
     */
    private $adyenRequestsHelperMock;

    /**
     * @var PaymentDataObjectInterface|MockObject
     */
    private $paymentDataObjectMock;

    /**
     * @var OrderAdapterInterface|MockObject
     */
    private $orderAdapterMock;

    /**
     * @var AddressAdapterInterface|MockObject
     */
    private $addressAdapterMock;

    protected function setUp(): void
    {
        $this->adyenRequestsHelperMock = $this->createMock(Requests::class);
        $this->paymentDataObjectMock = $this->createMock(PaymentDataObjectInterface::class);
        $this->orderAdapterMock = $this->createMock(OrderAdapterInterface::class);
        $this->addressAdapterMock = $this->createMock(AddressAdapterInterface::class);

        $this->addressDataBuilder = new AddressDataBuilder(
            $this->adyenRequestsHelperMock
        );
    }

    public function testBuildWithValidAddress()
    {
        $expectedResult = [
            'body' => [
                'billingAddress' => [
                    'street' => '123 Main Street',
                    'postalCode' => '12345',
                    'city' => 'Anytown',
                    'country' => 'US',
                    'stateOrProvince' => 'NY',
                    'houseNumberOrName' => '123',
                ]
            ]
        ];

        $this->paymentDataObjectMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($this->orderAdapterMock);

        $this->orderAdapterMock->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($this->addressAdapterMock);

        $this->orderAdapterMock->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn($this->addressAdapterMock);

        $this->orderAdapterMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn('store_id');

        $this->adyenRequestsHelperMock->expects($this->once())
            ->method('buildAddressData')
            ->with(
                $this->addressAdapterMock,
                $this->addressAdapterMock,
                'store_id',
                []
            )
            ->willReturn($expectedResult['body']);

        $result = $this->addressDataBuilder->build(['payment' => $this->paymentDataObjectMock]);

        $this->assertEquals($expectedResult, $result);
    }

    public function testBuildWithMissingAddress()
    {
        $expectedResult = [
            'body' => []
        ];

        $this->paymentDataObjectMock->expects($this->once())
            ->method('getOrder')
            ->willReturn($this->orderAdapterMock);

        $this->orderAdapterMock->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn(null);

        $this->orderAdapterMock->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn(null);

        $this->orderAdapterMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn('store_id');

        $this->adyenRequestsHelperMock->expects($this->once())
            ->method('buildAddressData')
            ->with(
                null,
                null,
                'store_id',
                []
            )
            ->willReturn($expectedResult['body']);

        $result = $this->addressDataBuilder->build(['payment' => $this->paymentDataObjectMock]);

        $this->assertEquals($expectedResult, $result);
    }
}
