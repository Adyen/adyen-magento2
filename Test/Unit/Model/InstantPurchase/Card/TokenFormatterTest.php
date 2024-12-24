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

namespace Adyen\Payment\Test\Helper\Unit\Model\InstantPurchase\Card;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\InstantPurchase\Card\TokenFormatter;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use InvalidArgumentException;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;

class TokenFormatterTest extends AbstractAdyenTestCase
{
    const VALID_TOKEN_DETAILS_VISA = '{"type":"visa","maskedCC":"1111","expirationDate":"3/2030","cardHolderName":"Veronica Costello","tokenType":"CardOnFile"}';
    const VALID_TOKEN_DETAILS_EXOTIC = '{"type":"exoticScheme","maskedCC":"4444","expirationDate":"3/2030","cardHolderName":"Veronica Costello","tokenType":"CardOnFile"}';

    const INVALID_TOKEN_DETAILS = '{"type":"visa","maskedCC":"1111"}';
    const CC_TYPES_MOCK = [
        'VI' => [
            'code_alt' => 'visa',
            'name' => 'Visa'
        ],
        'MC' => [
            'code_alt' => 'mc',
            'name' => 'MasterCard'
        ]
    ];

    protected TokenFormatter $tokenFormatter;
    protected Data|MockObject $adyenHelperMock;
    protected PaymentTokenInterface|MockObject $paymentTokenMock;

    public function setUp(): void
    {
        $this->paymentTokenMock = $this->createMock(PaymentTokenInterface::class);
        $this->adyenHelperMock = $this->createMock(Data::class);

        $this->adyenHelperMock->method('getAdyenCcTypes')->willReturn(self::CC_TYPES_MOCK);

        $this->tokenFormatter = new TokenFormatter($this->adyenHelperMock);
    }

    /**
     * @param $tokenDetails
     * @param $expected
     *
     * @dataProvider validTokenFormatterTestDataProvider
     *
     * @return void
     */
    public function testFormatPaymentTokenValid($tokenDetails, $expected)
    {
        $this->paymentTokenMock->expects($this->once())
            ->method('getTokenDetails')
            ->willReturn($tokenDetails);

        $this->assertEquals(
            $expected,
            $this->tokenFormatter->formatPaymentToken($this->paymentTokenMock)
        );
    }

    protected static function validTokenFormatterTestDataProvider(): array
    {
        return [
            [
                'tokenDetails' => self::VALID_TOKEN_DETAILS_VISA,
                'expected' => 'Card: Visa, ending: 1111 (expires: 3/2030)'
            ],
            [
                'tokenDetails' => self::VALID_TOKEN_DETAILS_EXOTIC,
                'expected' => 'Card: exoticScheme, ending: 4444 (expires: 3/2030)'
            ],
        ];
    }

    /**
     * @return void
     */
    public function testFormatPaymentTokenInvalid()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->paymentTokenMock->expects($this->once())
            ->method('getTokenDetails')
            ->willReturn(self::INVALID_TOKEN_DETAILS);

        $this->tokenFormatter->formatPaymentToken($this->paymentTokenMock);
    }
}
