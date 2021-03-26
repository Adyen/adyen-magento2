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
    const HOUSE_NUMBER_REGEX = '/((\s\d{0,10})|(\s\d{0,10}\s?\w{1,3}))$/i';

    /**
     * @param AddressAdapterInterface $address
     * @param $houseNumberStreetLine
     * @param $customerStreetLinesEnabled
     * @return array
     */
    public function getStreetAndHouseNumberFromAddress(
        AddressAdapterInterface $address,
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

        preg_match(
            self::HOUSE_NUMBER_REGEX,
            trim($addressString),
            $houseNumber,
            PREG_OFFSET_CAPTURE
        );

        if (!empty($houseNumber['0'])) {
            $_houseNumber = trim($houseNumber['0']['0']);
            $position = $houseNumber['0']['1'];
            $streetName = trim(substr($addressString, 0, $position));
            return $this->formatAddressArray($streetName, $_houseNumber);
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
