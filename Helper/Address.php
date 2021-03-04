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

class Address
{
    /**
     * The billing address retrieved from the Quote and the one retrieved from the Order has some differences
     * Therefore we need to check if the getStreetFull function exists and use that if yes, otherwise use the more
     * commont getStreetLine1
     *
     * @param $address
     * @return array
     */
    public function getStreetStringFromAddress($address): array
    {
        if (method_exists($address, 'getStreetFull')) {
            // Parse address into street and house number where possible
            $address = $this->getStreetFromString($address->getStreetFull());
        } else {
            $address = $this->getStreetFromString(
                implode(
                    ' ',
                    [
                        $address->getStreetLine1(),
                        $address->getStreetLine2(),
                        $address->getStreetLine3(),
                        $address->getStreetLine4()
                    ]
                )
            );
        }

        return $address;
    }

    /**
     * Street format
     *
     * @param string $streetLine
     * @return array
     */
    private function getStreetFromString($streetLine): array
    {
        $street = $this->formatStreet([$streetLine]);
        $streetName = $street['0'];
        unset($street['0']);
        $streetNr = implode(' ', $street);
        return (['name' => trim($streetName), 'house_number' => $streetNr]);
    }

    /**
     * Fix this one string street + number
     *
     * @param array $street
     * @return array $street
     * @example street + number
     */
    private function formatStreet($street): array
    {
        if (count($street) != 1) {
            return $street;
        }

        $street['0'] = trim($street['0']);

        preg_match(
            '/((\s\d{0,10})|(\s\d{0,10}\s?\w{1,3}))$/i',
            $street['0'],
            $houseNumber,
            PREG_OFFSET_CAPTURE
        );

        if (!empty($houseNumber['0'])) {
            $_houseNumber = trim($houseNumber['0']['0']);
            $position = $houseNumber['0']['1'];
            $streetName = trim(substr($street['0'], 0, $position));
            $street = [$streetName, $_houseNumber];
        }

        return $street;
    }
}
