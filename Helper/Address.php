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
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Magento\Payment\Gateway\Data\AddressAdapterInterface;

class Address
{
    // Regex to extract the house number from the street line if needed (e.g. 'Street address 1 A' => '1 A')
    const STREET_FIRST_REGEX = "/(?<streetName>[a-zA-Z.'\- ]+)\s+(?<houseNumber>\d{1,10}((\s)?\w{1,3})?)$/";
    CONST NUMBER_FIRST_REGEX = "/^(?<houseNumber>\d{1,10}((\s)?\w{1,3})?)\s+(?<streetName>[a-zA-Z.'\- ]+)/";

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
    ): array {
        $addressArray = [
            $address->getStreetLine1(),
            $address->getStreetLine2(),
            $address->getStreetLine3(),
            $address->getStreetLine4()
        ];

        // Cap the full street to the enabled street lines
        $street = array_slice($addressArray, 0, $customerStreetLinesEnabled);

        $drawHouseNumberWithRegex =
            $houseNumberStreetLine == 0 || // Config is disabled
            $houseNumberStreetLine > $customerStreetLinesEnabled ||  // Not enough street lines enabled
            empty($street[$houseNumberStreetLine - 1]);  // House number field is empty

        if ($drawHouseNumberWithRegex) {
            // Use the regex to get the house number
            return $this->getStreetAndHouseNumberFromArray($street);
        } else {
            // Extract and remove the house number from the street name array
            $houseNumber = $street[$houseNumberStreetLine - 1];
            unset($street[$houseNumberStreetLine - 1]);
            return $this->formatAddressArray(implode(' ', $street), $houseNumber);
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
        return (['name' => trim($street), 'house_number' => trim($houseNumber)]);
    }
}
