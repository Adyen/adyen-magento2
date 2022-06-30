<?php
/**
 *
 * Adyen Payment Module
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2022 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */
namespace Adyen\Payment\Helper\PaymentMethods;

interface PaymentMethodInterface
{
    public function getTxVariant(): string;

    public function getPaymentMethodName(): string;

    public function supportsCardOnFile(): bool;

    public function supportsSubscription(): bool;

    public function supportsManualCapture(): bool;

    public function supportsAutoCapture(): bool;

    public function isWalletPaymentMethod(): bool;
}
