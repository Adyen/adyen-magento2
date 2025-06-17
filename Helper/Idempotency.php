<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

class Idempotency
{
    /**
     * @param array $request Request body
     * @param array|null $idempotencyExtraData
     * @return string
     */
    public function generateIdempotencyKey (
        array $request,
        ?array $idempotencyExtraData = null
    ): string {
        $hashSource['request'] = $request;

        if (isset($idempotencyExtraData)) {
            $hashSource['$idempotencyExtraData'] = $idempotencyExtraData;
        }

        return hash('sha256', json_encode($hashSource));
    }
}
