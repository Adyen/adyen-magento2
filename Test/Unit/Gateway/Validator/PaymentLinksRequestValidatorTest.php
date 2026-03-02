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
    public function testValidate($dateModification, $valid, $message = [])
    {
        $date = new \DateTime();
        $date->modify($dateModification);
        $adyenPblExpiresAt = $date->format(AdyenPayByLinkConfigProvider::DATE_TIME_FORMAT);
        $this->payment->method('getAdyenPblExpiresAt')->willReturn($adyenPblExpiresAt);
        $validationSubject['payment'] = $this->payment;

        $this->resultInterfaceFactoryMock->method('create')
            ->with(['isValid' => $valid, 'failsDescription' => $message, 'errorCodes' => []]);

        $validationResult = $this->payByLinkValidator->validate($validationSubject);
        $this->assertEquals($valid, $validationResult);
    }

    public static function adyenPblExpiresAtDataProvider()
    {
        return [
            [
                '+' . (AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS - 1) . ' days',
                true
            ],
            [
                '+' . (AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS + 1) . ' days',
                true
            ],
            [
                '+' . AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS . ' days',
                false,
                ['Invalid expiry date selected for Adyen Pay By Link']
            ],
            [
                '+' . AdyenPayByLinkConfigProvider::MAX_EXPIRY_DAYS . ' days -1 minute',
                true
            ],
            [
                ' + ' . AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS . ' days',
                true
            ],
            [
                ' + ' . AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS . ' days -5 minute',
                false,
                ['Invalid expiry date selected for Adyen Pay By Link']
            ],
            [
                ' + ' . AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS . ' days -' . AdyenPayByLinkConfigProvider::EXPIRY_BUFFER_IN_SECONDS . ' seconds',
                true
            ]
        ];
    }
}
