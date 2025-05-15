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

namespace Adyen\Payment\Test\Unit\Model\Config\Backend;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Config\Backend\PaymentMethodsStatus;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class PaymentMethodsStatusTest extends AbstractAdyenTestCase
{
    const ENABLED_METHODS = [
        'adyen_cc',
        'adyen_ideal'
    ];

    private $paymentMethodsStatusMock;
    private $paymentMethodsHelperMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->paymentMethodsStatusMock = $this->getMockBuilder(PaymentMethodsStatus::class)
            ->addMethods([
                'getScope',
                'getScopeId'
            ])
            ->setConstructorArgs([
                $this->createConfiguredMock(Context::class, [
                    'getEventDispatcher' => $this->createMock(ManagerInterface::class)
                ]),
                $this->createMock(Registry::class),
                $this->createMock(ScopeConfigInterface::class),
                $this->createMock(TypeListInterface::class),
                $this->paymentMethodsHelperMock,
                $this->createMock(AbstractResource::class),
                $this->createMock(AbstractDb::class),
                []
            ])
            ->getMock();

        $this->paymentMethodsStatusMock->method('getScope')->willReturn('default');
        $this->paymentMethodsStatusMock->method('getScopeId')->willReturn(0);
    }

    public function testAfterSave()
    {
        $this->paymentMethodsHelperMock->method('togglePaymentMethodsActivation')
            ->willReturn(self::ENABLED_METHODS);

        $result = $this->paymentMethodsStatusMock->afterSave();

        $this->assertInstanceOf(PaymentMethodsStatus::class, $result);
    }

    public function testAfterDelete()
    {
        $result = $this->paymentMethodsStatusMock->afterDelete();

        $this->assertInstanceOf(PaymentMethodsStatus::class, $result);
    }
}
