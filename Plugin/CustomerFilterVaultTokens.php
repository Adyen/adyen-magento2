<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Plugin;

use Adyen\Payment\Helper\Vault;
use Magento\Vault\Model\CustomerTokenManagement;
use Adyen\Payment\Helper\Recurring;

class CustomerFilterVaultTokens
{
    /**
     * Returns filtered list of payment tokens for current customer session
     * Hide token if it is specifically set to SUBSCRIPTION or UNSCHEDULED_CARD_ON_FILE
     *
     * @param CustomerTokenManagement $customerTokenManagement
     * @param array $customerSessionTokens
     * @return array
     */
    public function afterGetCustomerSessionTokens(
        CustomerTokenManagement $customerTokenManagement,
        array $customerSessionTokens
    ): array {
        foreach ($customerSessionTokens as $key => $token) {
            if (strpos($token->getPaymentMethodCode(), 'adyen_') === 0) {
                $tokenDetails = json_decode($token->getTokenDetails());
                if (property_exists($tokenDetails, Vault::TOKEN_TYPE) &&
                    in_array($tokenDetails->tokenType, [
                        Recurring::SUBSCRIPTION,
                        Recurring::UNSCHEDULED_CARD_ON_FILE]
                    )
                ) {
                    unset($customerSessionTokens[$key]);
                }
            }
        }

        return $customerSessionTokens;
    }
}
