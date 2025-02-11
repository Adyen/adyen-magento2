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
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class SuccessTest extends AbstractAdyenTestCase
{
    private $successBlock;
    private $contextMock;
    private $sessionMock;
    private $orderRepositoryMock;
    private $searchCriteriaBuilderMock;
    private $orderSearchResultMock;

    protected function setUp(): void
    {
        $this->sessionMock = $this->createGeneratedMock(SessionManager::class, [
            'getOrderIds'
        ]);
        $this->sessionMock->method('getOrderIds')->willReturn(['1' => 1, '2' => 2]);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getSession')->willReturn($this->sessionMock);

        $this->orderSearchResultMock = $this->createMock(OrderSearchResultInterface::class);
        $this->orderSearchResultMock->method('getItems')->willReturn([]);

        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->orderRepositoryMock->method('getList')->willReturn($this->orderSearchResultMock);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->searchCriteriaBuilderMock->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('create')->willReturn(
            $this->createMock(SearchCriteriaInterface::class)
        );

        $payments = [
            ['result_code' => 'Authorised'],
            ['result_code' => 'IdentifyShopper', 'action' => 'DUMMY_ACTION_OBJECT']
        ];

        $objectManager = new ObjectManager($this);
        $this->successBlock = $objectManager->getObject(Success::class, [
            'paymentResponseEntities' => $payments,
            'context' => $this->contextMock,
            'orderRepository' => $this->orderRepositoryMock,
            'searchCriteriaBuilder' => $this->searchCriteriaBuilderMock
        ]);
    }

    public function testRenderAction()
    {
        $result = $this->successBlock->renderAction();
        $this->assertTrue($result);
    }

    public function testBillingCountryCodeSetterGetter()
    {
        $countryCode = "TR";
        $this->successBlock->setBillingCountryCode($countryCode);

        $this->assertEquals($countryCode, $this->successBlock->getBillingCountryCode());
    }
}
