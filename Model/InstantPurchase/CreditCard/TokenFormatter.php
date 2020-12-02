<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\InstantPurchase\CreditCard;

use Magento\InstantPurchase\PaymentMethodIntegration\PaymentTokenFormatterInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Adyen stored credit card formatter.
 */
class TokenFormatter implements PaymentTokenFormatterInterface
{
    /**
     * Most used credit card types
     *
     * @var array
     */
    public static $baseCardTypes = [
        'AE' => 'American Express',
        'VI' => 'Visa',
        'MC' => 'MasterCard',
        'DI' => 'Discover',
        'JBC' => 'JBC',
        'CUP' => 'China Union Pay',
        'MI' => 'Maestro',
    ];

    /**
     * Most used HPP types
     *
     * @var array
     */
    public static $baseHppTypes = [
        'sepadirectdebit' => 'SEPA Direct Debit'
    ];

    /**
     * @inheritdoc
     */
    public function formatPaymentToken(PaymentTokenInterface $paymentToken): string
    {
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);

        // Credit card type vaults
        if (PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD === $paymentToken->getType()) {
            if (!isset($details['type'], $details['maskedCC'], $details['expirationDate'])) {
                throw new \InvalidArgumentException('Invalid Adyen credit card token details.');
            }

            if (isset(self::$baseCardTypes[$details['type']])) {
                $ccType = self::$baseCardTypes[$details['type']];
            } else {
                $ccType = $details['type'];
            }

            return sprintf(
                '%s: %s, %s: %s (%s: %s)',
                __('Credit Card'),
                $ccType,
                __('ending'),
                $details['maskedCC'],
                __('expires'),
                $details['expirationDate']
            );
        } else {
            // Account type vaults
            if (!isset($details['type'], $details['maskedCC'], $details['expirationDate'])) {
                throw new \InvalidArgumentException('Invalid Adyen local payment method token details.');
            }

            if (isset(self::$baseHppTypes[$details['type']])) {
                $hppType = self::$baseHppTypes[$details['type']];
            } else {
                $hppType = $details['type'];
            }

            return sprintf(
                '%s: %s, %s: %s (%s: %s)',
                __('Account'),
                $hppType,
                __('number'),
                $details['maskedCC'],
                __('expires'),
                $details['expirationDate']
            );
        }
    }
}
