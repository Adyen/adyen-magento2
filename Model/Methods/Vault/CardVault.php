<?php

namespace Adyen\Payment\Model\Methods\Vault;

use Magento\Vault\Model\Method\Vault;

class CardVault extends Vault
{
    const CODE = 'adyen_cc_vault';

    /**
     * @inheritdoc
     * @since 100.1.0
     */
    public function isInitializeNeeded()
    {
        return false;
    }
}
