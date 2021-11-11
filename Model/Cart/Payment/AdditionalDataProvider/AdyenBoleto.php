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
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Cart\Payment\AdditionalDataProvider;

use Magento\QuoteGraphQl\Model\Cart\Payment\AdditionalDataProviderInterface;

/**
 * Interface for payment method additional data provider
 */
class AdyenBoleto implements AdditionalDataProviderInterface
{
    /**
     * @param array $data
     * @return array
     */
    public function getData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $additionalData) {
            if ($key == 'adyen_additional_data_boleto') {
                foreach ($data['adyen_additional_data_boleto'] as $adyenKey => $adyenAdditionalData) {
                    $result[$adyenKey] = $adyenAdditionalData;
                }
            } else {
                $result[$key] = $additionalData;
            }
        }
        return $result;
    }
}
