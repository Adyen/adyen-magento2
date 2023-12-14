<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\AdyenException;
use Adyen\Payment\Gateway\Request\MotoMerchantAccountDataBuilder;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment;

class MotoMerchantAccountDataBuilderTest extends AbstractAdyenTestCase
{
    public static function merchantAccountProvider(): array
    {
        return [
            [
                '$merchantAccount' => 'DUMMY_MERCHANT_ACCOUNT'
            ],
            [
                '$merchantAccount' => null
            ]
        ];
    }

    /**
     * @dataProvider merchantAccountProvider
     */
    public function testRequestBuilder($merchantAccount)
    {
        if (is_null($merchantAccount)) {
            $this->expectException(AdyenException::class);
        }

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('motoMerchantAccount')
            ->willReturn($merchantAccount);

        $paymentDataObjectMock = $this->createConfiguredMock(PaymentDataObject::class, [
            'getPayment' => $paymentMock
        ]);

        $buildSubject = [
            'payment' => $paymentDataObjectMock
        ];

        $builder = new MotoMerchantAccountDataBuilder();
        $request = $builder->build($buildSubject);

        $this->assertSame([Requests::MERCHANT_ACCOUNT => $merchantAccount], $request['body']);
    }
}
