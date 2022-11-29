<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui\Adminhtml;

use Magento\Checkout\Model\ConfigProviderInterface;

class AdyenMotoConfigProvider implements ConfigProviderInterface
{
    final const CODE = 'adyen_moto';
    final const API_KEY_PLACEHOLDER = 'api_key_placeholder';

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'isActive' => true
                ]
            ]
        ];
    }
}
