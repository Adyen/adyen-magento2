<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Gateway\Validator;

use Adyen\Payment\Gateway\Validator\PayByLinkValidator;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\TestCase;

class PayByLinkValidatorTest extends TestCase
{
    /**
     * @var PayByLinkValidator
     */
    private $payByLinkValidator;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $payment;

    protected function setUp(): void
    {
        $resultInterfaceFactory = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();

        $resultInterfaceFactory->method('create')->will(
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

        $this->payByLinkValidator = new PayByLinkValidator($resultInterfaceFactory);

        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()->setMethods(['getAdyenPblExpiresAt'])
            ->getMock();
    }

    /**
     * @dataProvider adyenPblExpiresAtDataProvider
     */
    public function testValidate($adyenPblExpiresAt, $valid)
    {
        $this->payment->method('getAdyenPblExpiresAt')->willReturn($adyenPblExpiresAt);
        $validationSubject['payment'] = $this->payment;
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
                false
            ],
            [
                date(
                    AdyenPayByLinkConfigProvider::DATE_FORMAT,
                    strtotime($today . ' + ' . (AdyenPayByLinkConfigProvider::MIN_EXPIRY_DAYS) . ' days')
                ),
                false
            ],
        ];
    }
}
