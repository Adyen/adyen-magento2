<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Gateway\Validator;

use Adyen\Payment\Gateway\Validator\PaymentLinksRequestValidator;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\TestCase;

class PaymentLinksRequestValidatorTest extends AbstractAdyenTestCase
{
    /**
     * @var PaymentLinksRequestValidator
     */
    private $payByLinkValidator;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $payment;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $resultInterfaceFactoryMock;

    protected function setUp(): void
    {
        $this->resultInterfaceFactoryMock = $this->createMock(ResultInterfaceFactory::class);

        $this->resultInterfaceFactoryMock->method('create')->will(
            $this->returnValueMap(
                [
                    [
                        [
                            "isValid" => false,
                            "failsDescription" => ["Invalid expiry date selected for Adyen Pay By Link"],
                            "errorCodes" => []
                        ],
                        false
                    ],
                    [
                        [
                            "isValid" => true,
                            "failsDescription" => [],
                            "errorCodes" => []
                        ],
                        true
                    ]
                ]));

        $this->payByLinkValidator = new PaymentLinksRequestValidator($this->resultInterfaceFactoryMock);

        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()->addMethods(['getAdyenPblExpiresAt'])
            ->getMock();
    }

    /**
     * @dataProvider adyenPblExpiresAtDataProvider
     */
    public function testValidate($adyenPblExpiresAt, $valid, $message = [])
    {
        $this->payment->method('getAdyenPblExpiresAt')->willReturn($adyenPblExpiresAt);
        $validationSubject['payment'] = $this->payment;

        $this->resultInterfaceFactoryMock->method('create')
            ->with(['isValid' => $valid, 'failsDescription' => $message, 'errorCodes' => []]);

        $validationResult = $this->payByLinkValidator->validate($validationSubject);
        $this->assertEquals($valid, $validationResult);
    }

    public static function adyenPblExpiresAtDataProvider()
    {
        $today = date(AdyenPayByLinkConfigProvider::DATE_FORMAT);
        return [
            [
                date(
                    AdyenPayByLinkConfigProvider::DATE_FORMAT,
                    strtotime($today . ' + ' . (AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS - 1) . ' days')
                ),
                true
            ],
            [
                date(
                    AdyenPayByLinkConfigProvider::DATE_FORMAT,
                    strtotime($today . ' + ' . (AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS + 1) . ' days')
                ),
                true
            ],
            [
                date(
                    AdyenPayByLinkConfigProvider::DATE_FORMAT,
                    strtotime($today . ' + ' . (AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS) . ' days')
                ),
                false,
                ['Invalid expiry date selected for Adyen Pay By Link']
            ],
            [
                date(
                    AdyenPayByLinkConfigProvider::DATE_FORMAT,
                    strtotime($today . ' + ' . (AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS) . ' days')
                ),
                true
            ],
        ];
    }
}
