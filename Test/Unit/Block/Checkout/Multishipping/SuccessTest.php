<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Block\Checkout\Multishipping;

use Adyen\Payment\Block\Checkout\Multishipping\Success;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class SuccessTest extends AbstractAdyenTestCase
{
    private $successBlock;
    private $contextMock;
    private $sessionMock;
    private $orderRepositoryMock;
    private $searchCriteriaBuilderMock;
    private $orderSearchResultMock;
    private $orderMock;
    private $paymentMock;
    private $paymentMethodHelperMock;

    const ORDER_BILLING_ADDRESS_COUNTRY = 'TR';

    protected function setUp(): void
    {
        $this->sessionMock = $this->createGeneratedMock(SessionManager::class, [
            'getOrderIds'
        ]);
        $this->sessionMock->method('getOrderIds')->willReturn(['1' => 1, '2' => 2]);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getSession')->willReturn($this->sessionMock);

        $this->paymentMock = $this->createMock(Order\Payment::class);
        $this->paymentMock->method('getMethod')->willReturn('adyen_cc');
        $this->paymentMock->method('getAdditionalInformation')->willReturn([
            'resultCode' => 'Authorised'
        ]);

        $billingAddressMock = $this->createMock(OrderAddressInterface::class);
        $billingAddressMock->method('getCountryId')->willReturn(self::ORDER_BILLING_ADDRESS_COUNTRY);

        $this->orderMock = $this->createMock(Order::class);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getEntityId')->willReturn(1);
        $this->orderMock->method('getBillingAddress')->willReturn($billingAddressMock);

        $this->orderSearchResultMock = $this->createMock(OrderSearchResultInterface::class);
        $this->orderSearchResultMock->method('getItems')->willReturn([$this->orderMock]);

        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->orderRepositoryMock->method('getList')->willReturn($this->orderSearchResultMock);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->searchCriteriaBuilderMock->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('create')->willReturn(
            $this->createMock(SearchCriteriaInterface::class)
        );

        $this->paymentMethodHelperMock = $this->createMock(PaymentMethods::class);
        $this->paymentMethodHelperMock->method('isAdyenPayment')->willReturn(true);

        $payments = [
            ['result_code' => 'Authorised'],
            ['result_code' => 'IdentifyShopper', 'action' => 'DUMMY_ACTION_OBJECT']
        ];

        $objectManager = new ObjectManager($this);
        $this->successBlock = $objectManager->getObject(Success::class, [
            'paymentResponseEntities' => $payments,
            'context' => $this->contextMock,
            'orderRepository' => $this->orderRepositoryMock,
            'searchCriteriaBuilder' => $this->searchCriteriaBuilderMock,
            'paymentMethodsHelper' => $this->paymentMethodHelperMock
        ]);
    }

    public function testRenderAction()
    {
        $result = $this->successBlock->renderAction();
        $this->assertTrue($result);
    }

    public function testBillingCountryCodeSetterGetter()
    {
        $this->assertEquals(
            self::ORDER_BILLING_ADDRESS_COUNTRY,
            $this->successBlock->getBillingCountryCode()
        );
    }
}
