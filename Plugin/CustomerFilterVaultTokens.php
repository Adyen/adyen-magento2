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
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Model\CustomerTokenManagement;
use Magento\Framework\App\Request\Http;

class CustomerFilterVaultTokens
{
    private Vault $vaultHelper;
    private StoreManagerInterface $storeManager;
    private Http $request;

    public function __construct(
        Vault $vaultHelper,
        StoreManagerInterface $storeManager,
        Http $request
    ) {
        $this->vaultHelper = $vaultHelper;
        $this->storeManager = $storeManager;
        $this->request = $request;
    }

    /**
     * Returns filtered list of payment tokens for current customer session for Checkout
     * Hide token if it is specifically set to SUBSCRIPTION or UNSCHEDULED_CARD_ON_FILE
     */
    public function afterGetCustomerSessionTokens(
        CustomerTokenManagement $customerTokenManagement,
        array $customerSessionTokens
    ): array {
        $controllerModule 	= $this->request->getControllerModule();
        if($controllerModule == 'Magento_Checkout') {
            foreach ($customerSessionTokens as $key => $token) {
                if (strpos((string)$token->getPaymentMethodCode(), 'adyen_') === 0) {
                    $tokenDetails = json_decode((string)$token->getTokenDetails());
                    $storeId = $this->storeManager->getStore()->getId();
                    if ((property_exists($tokenDetails, Vault::TOKEN_TYPE) &&
                        in_array($tokenDetails->tokenType, [
                                Vault::SUBSCRIPTION,
                                Vault::UNSCHEDULED_CARD_ON_FILE]
                        )) ||
                        !$this->vaultHelper->getPaymentMethodRecurringActive($token->getPaymentMethodCode(), $storeId)
                    ) {
                        unset($customerSessionTokens[$key]);
                    }
                }
            }
        }

        return $customerSessionTokens;
    }
}
