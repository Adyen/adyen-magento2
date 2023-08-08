<?php

namespace Adyen\Payment\Model\Methods\Vault;

use Magento\Vault\Model\Method\Vault;

class SepaDirectDebitVault extends Vault
{
    const CODE = 'adyen_sepadirectdebit_vault';

    /**
     * @inheritdoc
     * @since 100.1.0
     */
    public function isInitializeNeeded()
    {
        return false;
    }
}
