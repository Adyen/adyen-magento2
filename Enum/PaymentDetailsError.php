<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Enum;

enum PaymentDetailsError
{
    case Refused;
    case Canceled;
    case InvalidJson;
    case ApiCallFailed;
}
