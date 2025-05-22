<?php
/**
 * Reflet Communication
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is available
 * through the world-wide-web at this URL: https://opensource.org/license/osl-3-0-php/
 * If you are unable to obtain it through the world-wide-web, please email agence@reflet-digital.com,
 * so we can send you a copy immediately.
 *
 * @author Reflet Communication
 * @license http://www.opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Adyen\Payment\Enum;

enum PaymentDetailsError: int
{
    case Refused = 0;
    case InvalidJson = 1;
    case ApiCallFailed = 2;
}
