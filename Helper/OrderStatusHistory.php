<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

class OrderStatusHistory
{
    public function buildComment(array $response, string $actionName): String
    {
        $comment = __("Adyen %1 API response:", $actionName) . '<br />';

        if (isset($response['resultCode'])) {
            $comment .= __("Result Code: %1", $response['resultCode']) . '<br />';
        }

        // Modification responses contain `status` but not `resultCode`.
        if (isset($response['status'])) {
            $comment .= __("Status: %1", $response['status']) . '<br />';
        }

        if (isset($response['pspReference'])) {
            $comment .= __("PSP reference: %1", $response['pspReference']);
        }

        $comment .= '<br />';

        return $comment;
    }
}
