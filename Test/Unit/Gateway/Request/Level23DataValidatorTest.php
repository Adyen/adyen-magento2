<?php

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Gateway\Request\Level23DataValidator;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Api\Data\CountryInformationInterface;
use PHPUnit\Framework\MockObject\MockObject;

class Level23DataValidatorTest extends AbstractAdyenTestCase
{
    protected Level23DataValidator $validator;
    protected MockObject|CountryInformationAcquirerInterface $countryInfoAcquirerMock;
    protected MockObject|AdyenLogger $loggerMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->countryInfoAcquirerMock = $this->createMock(CountryInformationAcquirerInterface::class);
        $this->loggerMock = $this->createMock(AdyenLogger::class);

        $this->validator = new Level23DataValidator(
            $this->countryInfoAcquirerMock,
            $this->loggerMock
        );
    }

    public static function customerReferenceProvider(): array
    {
        return [
            'valid short reference' => ['REF123', 'REF123'],
            'valid at max length' => [str_repeat('A', 25), str_repeat('A', 25)],
            'truncated over max length' => [str_repeat('A', 30), str_repeat('A', 25)],
            'leading spaces trimmed' => ['  REF123', 'REF123'],
            'blank string' => ['', null],
            'all spaces' => ['   ', null],
            'all zeros' => ['0000', null],
        ];
    }

    /**
     * @dataProvider customerReferenceProvider
     */
    public function testSanitizeCustomerReference(string $input, ?string $expected): void
    {
        $result = $this->validator->sanitizeCustomerReference($input);
        $this->assertSame($expected, $result);
    }

    public static function descriptionProvider(): array
    {
        return [
            'valid description' => ['Test Product Name', 'Test Product Name'],
            'truncated to 26 chars' => ['This is a very long product description that exceeds limit', 'This is a very long produc'],
            'leading spaces trimmed' => ['  Product', 'Product'],
            'blank string' => ['', null],
            'all spaces' => ['   ', null],
            'single character' => ['A', null],
            'all special characters' => ['!@#$%', null],
            'all zeros' => ['0000', null],
            'two characters valid' => ['AB', 'AB'],
            'mixed special and alpha' => ['A!B', 'A!B'],
        ];
    }

    /**
     * @dataProvider descriptionProvider
     */
    public function testSanitizeDescription(string $input, ?string $expected): void
    {
        $result = $this->validator->sanitizeDescription($input);
        $this->assertSame($expected, $result);
    }

    public static function productCodeProvider(): array
    {
        return [
            'valid short code' => ['SKU123', 'SKU123'],
            'valid at max length' => [str_repeat('A', 12), str_repeat('A', 12)],
            'truncated over max' => ['PRODUCT-CODE-LONG', 'PRODUCT-CODE'],
            'leading spaces trimmed' => ['  SKU123', 'SKU123'],
            'blank string' => ['', null],
            'all spaces' => ['   ', null],
            'all zeros' => ['000', null],
        ];
    }

    /**
     * @dataProvider productCodeProvider
     */
    public function testSanitizeProductCode(string $input, ?string $expected): void
    {
        $result = $this->validator->sanitizeProductCode($input);
        $this->assertSame($expected, $result);
    }

    public static function commodityCodeProvider(): array
    {
        return [
            'valid code' => ['COMM123', 'COMM123'],
            'truncated over max' => ['COMMODITYCODE123', 'COMMODITYCOD'],
            'blank string' => ['', null],
            'all zeros' => ['000', null],
        ];
    }

    /**
     * @dataProvider commodityCodeProvider
     */
    public function testSanitizeCommodityCode(string $input, ?string $expected): void
    {
        $result = $this->validator->sanitizeCommodityCode($input);
        $this->assertSame($expected, $result);
    }

    public static function postalCodeProvider(): array
    {
        return [
            'valid US zip' => ['10001', '10001'],
            'valid US zip+4' => ['10001-0000', '10001-0000'],
            'truncated over max' => ['12345678901', '1234567890'],
            'leading spaces trimmed' => [' 10001', '10001'],
            'blank string' => ['', null],
        ];
    }

    /**
     * @dataProvider postalCodeProvider
     */
    public function testSanitizePostalCode(string $input, ?string $expected): void
    {
        $result = $this->validator->sanitizePostalCode($input);
        $this->assertSame($expected, $result);
    }

    public static function stateProvinceCodeProvider(): array
    {
        return [
            'valid 2-char' => ['MI', 'MI'],
            'valid 3-char' => ['NYC', 'NYC'],
            'truncated over max' => ['MICHIGAN', 'MIC'],
            'leading spaces trimmed' => [' NY', 'NY'],
            'blank string' => ['', null],
        ];
    }

    /**
     * @dataProvider stateProvinceCodeProvider
     */
    public function testSanitizeStateProvinceCode(string $input, ?string $expected): void
    {
        $result = $this->validator->sanitizeStateProvinceCode($input);
        $this->assertSame($expected, $result);
    }

    public function testConvertCountryCodeToAlpha3Success(): void
    {
        $countryInfoMock = $this->createMock(CountryInformationInterface::class);
        $countryInfoMock->method('getThreeLetterAbbreviation')->willReturn('USA');

        $this->countryInfoAcquirerMock->method('getCountryInfo')
            ->with('US')
            ->willReturn($countryInfoMock);

        $result = $this->validator->convertCountryCodeToAlpha3('US');
        $this->assertSame('USA', $result);
    }

    public function testConvertCountryCodeToAlpha3Failure(): void
    {
        $this->countryInfoAcquirerMock->method('getCountryInfo')
            ->willThrowException(new \Exception('Country not found'));

        $result = $this->validator->convertCountryCodeToAlpha3('XX');
        $this->assertNull($result);
    }

    public static function lineItemTotalAmountProvider(): array
    {
        return [
            'simple calculation' => [2, 1000, 500, 1500],
            'no discount' => [3, 1000, 0, 3000],
            'full discount' => [1, 1000, 1000, 0],
            'over discount' => [1, 1000, 1500, -500],
        ];
    }

    /**
     * @dataProvider lineItemTotalAmountProvider
     */
    public function testCalculateLineItemTotalAmount(
        int $quantity,
        int $unitPrice,
        int $discountAmount,
        int $expected
    ): void {
        $result = $this->validator->calculateLineItemTotalAmount($quantity, $unitPrice, $discountAmount);
        $this->assertSame($expected, $result);
    }

    public static function amountNotAllZerosProvider(): array
    {
        return [
            'non-zero amount' => ['1000', true],
            'all zeros' => ['000', false],
            'single zero' => ['0', false],
            'mixed with non-zero' => ['0100', true],
        ];
    }

    /**
     * @dataProvider amountNotAllZerosProvider
     */
    public function testIsAmountNotAllZeros(string $input, bool $expected): void
    {
        $result = $this->validator->isAmountNotAllZeros($input);
        $this->assertSame($expected, $result);
    }

    public static function validateLineItemInputProvider(): array
    {
        return [
            'valid item' => [10.00, 2, true],
            'zero price' => [0, 2, false],
            'null price' => [null, 2, false],
            'zero qty' => [10.00, 0, false],
            'fractional qty below 1' => [10.00, 0.5, false],
            'null qty' => [10.00, null, false],
            'valid fractional qty above 1' => [10.00, 1.5, true],
        ];
    }

    /**
     * @dataProvider validateLineItemInputProvider
     */
    public function testValidateLineItemInput($unitPrice, $qtyOrdered, bool $expected): void
    {
        $result = $this->validator->validateLineItemInput($unitPrice, $qtyOrdered);
        $this->assertSame($expected, $result);
    }
}
