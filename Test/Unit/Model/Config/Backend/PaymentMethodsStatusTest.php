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

    public function testAfterSave()
    {
        $paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsHelperMock->method('togglePaymentMethodsActivation')
            ->willReturn(self::ENABLED_METHODS);


        $paymentMethodsStatusMock = $this->getMockBuilder(PaymentMethodsStatus::class)
            ->setMethods([
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
                $paymentMethodsHelperMock,
                $this->createMock(AbstractResource::class),
                $this->createMock(AbstractDb::class),
                []
            ])
            ->getMock();
        $paymentMethodsStatusMock->method('getScope')->willReturn('default');
        $paymentMethodsStatusMock->method('getScopeId')->willReturn(0);

        $result = $paymentMethodsStatusMock->afterSave();

        $this->assertInstanceOf(PaymentMethodsStatus::class, $result);
    }
}
