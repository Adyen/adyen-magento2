<?php
/**
 *
 * Adyen Payment Module
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2023 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\Payment\Model\Method;

/**
 * @deprecated
 */
interface PaymentMethodInterface
{
    public function isWallet(): bool;

    public function supportsCardOnFile(): bool;

    public function supportsSubscription(): bool;

    public function supportsManualCapture(): bool;

    public function supportsAutoCapture(): bool;

    public function supportsUnscheduledCardOnFile(): bool;
}
