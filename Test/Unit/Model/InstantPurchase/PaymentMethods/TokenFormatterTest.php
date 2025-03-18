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

namespace Adyen\Payment\Test\Helper\Unit\Model\InstantPurchase\PaymentMethods;

use Adyen\Payment\Model\InstantPurchase\PaymentMethods\TokenFormatter;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Vault\Api\Data\PaymentTokenInterface;

class TokenFormatterTest extends AbstractAdyenTestCase
{
    public function testFormatPaymentToken()
    {
        $tokenFormatter = new TokenFormatter();
        $paymentTokenMock = $this->createMock(PaymentTokenInterface::class);

        $tokenDetails = '{"type":"sepadirectdebit","tokenLabel":"SEPA Direct Debit token created on 2024-12-11"}';

        $paymentTokenMock->expects($this->once())
            ->method('getTokenDetails')
            ->willReturn($tokenDetails);

        $this->assertEquals(
            'SEPA Direct Debit token created on 2024-12-11',
            $tokenFormatter->formatPaymentToken($paymentTokenMock)
        );
    }
}
