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
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

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

        $objectManager = new ObjectManager($this);
        $paymentMethodsStatus = $objectManager->getObject(PaymentMethodsStatus::class, [
            'paymentMethodsHelper' => $paymentMethodsHelperMock
        ]);

        $result = $paymentMethodsStatus->afterSave();

        $this->assertInstanceOf(PaymentMethodsStatus::class, $result);
    }
}
