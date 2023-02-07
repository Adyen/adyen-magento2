<?php

namespace Adyen\Payment\Model\Methods\Vault;

use Magento\Vault\Model\Method\Vault;

class KlarnaVault extends Vault
{
    const CODE = 'adyen_klarna_vault';

    /**
     * @inheritdoc
     * @since 100.1.0
     */
    public function isInitializeNeeded()
    {
        return false;
    }
}
