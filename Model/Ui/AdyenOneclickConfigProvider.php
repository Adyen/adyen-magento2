<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * @deprecated All tokenization functionalities moved to vault.
 * Adyen Oneclick payment method related classes will be removed on the next major version.
 */
class AdyenOneclickConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_oneclick';

    public function getConfig(): array
    {
        return [];
    }
}
