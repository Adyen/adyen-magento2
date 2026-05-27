<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2026 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Directory\Api\CountryInformationAcquirerInterface;

class Level23DataValidator
{
    const MAX_LENGTH_CUSTOMER_REFERENCE = 25;
    const MAX_LENGTH_DESCRIPTION = 26;
    const MAX_LENGTH_PRODUCT_CODE = 12;
    const MAX_LENGTH_COMMODITY_CODE = 12;
    const MAX_LENGTH_POSTAL_CODE = 10;
    const MAX_LENGTH_STATE_PROVINCE_CODE = 3;
    const MAX_LENGTH_AMOUNT = 12;

    /**
     * @param CountryInformationAcquirerInterface $countryInformationAcquirer
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly CountryInformationAcquirerInterface $countryInformationAcquirer,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * Sanitize customerReference: trim, truncate to 25, reject if blank or all zeros.
     *
     * @param string $value
     * @return string|null
     */
    public function sanitizeCustomerReference(string $value): ?string
    {
        $value = $this->trimAndTruncate($value, self::MAX_LENGTH_CUSTOMER_REFERENCE);

        if ($this->isBlank($value) || $this->isAllZeros($value)) {
            $this->adyenLogger->addAdyenInfoLog('L2/L3: customerReference is blank or all zeros, skipping field.');
            return null;
        }

        return $value;
    }

    /**
     * Sanitize description: trim, truncate to 26, reject if blank, single char, all special chars, or all zeros.
     *
     * @param string $value
     * @return string|null
     */
    public function sanitizeDescription(string $value): ?string
    {
        $value = $this->trimAndTruncate($value, self::MAX_LENGTH_DESCRIPTION);

        if (
            $this->isBlank($value) ||
            mb_strlen($value) < 2 ||
            $this->isAllSpecialCharacters($value) ||
            $this->isAllZeros($value)
        ) {
            return null;
        }

        return $value;
    }

    /**
     * Sanitize productCode: trim, truncate to 12, reject if blank or all zeros.
     *
     * @param string $value
     * @return string|null
     */
    public function sanitizeProductCode(string $value): ?string
    {
        $value = $this->trimAndTruncate($value, self::MAX_LENGTH_PRODUCT_CODE);

        if ($this->isBlank($value) || $this->isAllZeros($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Sanitize commodityCode: trim, truncate to 12, reject if blank or all zeros.
     *
     * @param string $value
     * @return string|null
     */
    public function sanitizeCommodityCode(string $value): ?string
    {
        $value = $this->trimAndTruncate($value, self::MAX_LENGTH_COMMODITY_CODE);

        if ($this->isBlank($value) || $this->isAllZeros($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Sanitize destinationPostalCode: trim leading spaces, truncate to 10.
     *
     * @param string $value
     * @return string|null
     */
    public function sanitizePostalCode(string $value): ?string
    {
        $value = $this->trimAndTruncate($value, self::MAX_LENGTH_POSTAL_CODE);

        if ($this->isBlank($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Sanitize destinationStateProvinceCode: trim leading spaces, truncate to 3.
     *
     * @param string $value
     * @return string|null
     */
    public function sanitizeStateProvinceCode(string $value): ?string
    {
        $value = $this->trimAndTruncate($value, self::MAX_LENGTH_STATE_PROVINCE_CODE);

        if ($this->isBlank($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Convert ISO 3166-1 alpha-2 country code to alpha-3.
     *
     * @param string $alpha2Code
     * @return string|null
     */
    public function convertCountryCodeToAlpha3(string $alpha2Code): ?string
    {
        try {
            $countryInfo = $this->countryInformationAcquirer->getCountryInfo($alpha2Code);
            $alpha3 = $countryInfo->getThreeLetterAbbreviation();

            if (!empty($alpha3) && strlen($alpha3) === 3) {
                return $alpha3;
            }
        } catch (\Exception $e) {
            $this->adyenLogger->error(
                sprintf('L2/L3: Failed to convert country code "%s" to alpha-3: %s', $alpha2Code, $e->getMessage())
            );
        }

        return null;
    }

    /**
     * Calculate line item totalAmount per Visa rules: (quantity * unitPrice) - discountAmount.
     * All values are in minor units (integers).
     *
     * @param int $quantity
     * @param int $unitPrice
     * @param int $discountAmount
     * @return int
     */
    public function calculateLineItemTotalAmount(int $quantity, int $unitPrice, int $discountAmount): int
    {
        return ($quantity * $unitPrice) - $discountAmount;
    }

    /**
     * Validate a formatted amount string is not all zeros.
     *
     * @param string $formattedAmount
     * @return bool
     */
    public function isAmountNotAllZeros(string $formattedAmount): bool
    {
        return !$this->isAllZeros($formattedAmount);
    }

    /**
     * Validate line item input values before formatting.
     *
     * @param float|int|null $unitPrice
     * @param float|int|null $qtyOrdered
     * @return bool
     */
    public function validateLineItemInput($unitPrice, $qtyOrdered): bool
    {
        if (empty($unitPrice) || floatval($unitPrice) == 0) {
            return false;
        }

        if (empty($qtyOrdered) || floatval($qtyOrdered) < 1) {
            return false;
        }

        return true;
    }

    /**
     * Trim leading spaces and truncate to max length.
     *
     * @param string $value
     * @param int $maxLength
     * @return string
     */
    private function trimAndTruncate(string $value, int $maxLength): string
    {
        $value = ltrim($value);
        return mb_substr($value, 0, $maxLength);
    }

    /**
     * Check if a value is blank or all spaces.
     *
     * @param string $value
     * @return bool
     */
    private function isBlank(string $value): bool
    {
        return trim($value) === '';
    }

    /**
     * Check if a value consists only of zero characters.
     *
     * @param string $value
     * @return bool
     */
    private function isAllZeros(string $value): bool
    {
        return $value !== '' && preg_match('/^0+$/', $value) === 1;
    }

    /**
     * Check if a value consists only of non-alphanumeric characters.
     *
     * @param string $value
     * @return bool
     */
    private function isAllSpecialCharacters(string $value): bool
    {
        return $value !== '' && preg_match('/^[^a-zA-Z0-9]+$/', $value) === 1;
    }
}
