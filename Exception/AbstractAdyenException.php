<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Exception;

use Magento\Framework\Exception\LocalizedException;

class AbstractAdyenException extends LocalizedException
{
    /**
     * Error codes that are safe to display to the shopper.
     * @see https://docs.adyen.com/development-resources/error-codes
     *
     * For the correct mapping of the error codes,
     * please update etc/authorize_error_mapping.xml together with the following list.
     */
    const SAFE_ERROR_CODES = ['124'];
}
