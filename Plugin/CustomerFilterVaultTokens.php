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

use Magento\Vault\Model\CustomerTokenManagement;
use Adyen\Payment\Helper\Recurring;

class CustomerFilterVaultTokens
{
    /**
     * Returns filtered list of payment tokens for current customer session
     * @param CustomerTokenManagement $customerTokenManagement
     * @param array $customerSessionTokens
     * @return CustomerTokenManagement[]
     */
    public function afterGetCustomerSessionTokens(CustomerTokenManagement $customerTokenManagement, array $customerSessionTokens)
    {
        foreach($customerSessionTokens as $key => $token) {
            if (strpos($token->getPaymentMethodCode(), 'adyen_') === 0) {
                $tokenDetails = json_decode($token->getTokenDetails());
                if ($tokenDetails->tokenType === Recurring::UNSCHEDULED_CARD_ON_FILE || $tokenDetails->tokenType === Recurring::SUBSCRIPTION) {
                    unset($customerSessionTokens[$key]);
                }
            }
        }

        return $customerSessionTokens;
    }
}
