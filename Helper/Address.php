<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Api\Data\AddressAdapterInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Data\AddressAdapterInterface as CoreAddressAdapterInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

class Address
{
    // Regex to extract the house number from the street line if needed (e.g. 'Street address 1 A' => '1 A')
    const STREET_FIRST_REGEX = "/(?<streetName>[\d\p{L}.'\-\s]+[\p{L}.'])\s+(?<houseNumber>[\d\s\-\/,.]+[\d\p{L}\s\-\/,.]{0,10})$/u";
    const NUMBER_FIRST_REGEX = "/^(?<houseNumber>[\d\s\-\/,.]+[\d\p{L}\s\-\/,.]{0,2})\s+(?<streetName>[\d\p{L}.'\-\s]+[\p{L}.'])/u";

    const COUNTRY_CODE_MAPPING = [
        'XK' => 'QZ'
    ];

    /**
     * @var AdyenLogger $logger
     */
    protected $logger;

    /**
     * Address constructor.
     */
    public function __construct(AdyenLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param AddressAdapterInterface $address
     * @param $houseNumberStreetLine
     * @param $customerStreetLinesEnabled
     * @return array
     */
    public function getStreetAndHouseNumberFromAddress(
        $address,
        $houseNumberStreetLine,
        $customerStreetLinesEnabled
    ): bool {
        return false:
    }

    /**
     * @param string $countryCode
     * @return string
     */
    public function getAdyenCountryCode(string $countryCode): string
    {
        if (array_key_exists($countryCode, self::COUNTRY_CODE_MAPPING)) {
            return self::COUNTRY_CODE_MAPPING[$countryCode];
        } else {
            return $countryCode;
        }
    }

    /**
     * @param string[] $addressArray
     * @return array
     */
    private function getStreetAndHouseNumberFromArray(array $addressArray): array
    {
        $addressString = implode(' ', $addressArray);

        // Match addresses where the street name comes first, e.g. John-Paul's Ave. 1 B
        preg_match(self::STREET_FIRST_REGEX, trim($addressString), $streetFirstAddress);
        // Match addresses where the house number comes first, e.g. 10 D John-Paul's Ave.
        preg_match(self::NUMBER_FIRST_REGEX, trim($addressString), $numberFirstAddress);

        if (!empty($streetFirstAddress)) {
            return $this->formatAddressArray($streetFirstAddress['streetName'], $streetFirstAddress['houseNumber']);
        } elseif (!empty($numberFirstAddress)) {
            return $this->formatAddressArray($numberFirstAddress['streetName'], $numberFirstAddress['houseNumber']);
        }

        return $this->formatAddressArray($addressString, 'N/A');
    }

    /**
     * @param $street
     * @param $houseNumber
     * @return array
     */
    private function formatAddressArray($street, $houseNumber): array
    {
        return ['name' => trim((string) $street), 'house_number' => trim((string) $houseNumber)];
    }
}
