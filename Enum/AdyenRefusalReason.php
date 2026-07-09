<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Enum;

/**
 * Class AdyenRefusalReason
 *
 * @package Adyen\Payment\Enum
 * @see https://docs.adyen.com/development-resources/refusal-reasons/
 */
enum AdyenRefusalReason: int
{
    case None = 0;
    case Refused = 2;
    case Referral = 3;
    case AcquirerError = 4;
    case BlockedCard = 5;
    case ExpiredCard = 6;
    case InvalidAmount = 7;
    case InvalidCardNumber = 8;
    case IssuerUnavailable = 9;
    case NotSupported = 10;
    case NotAuthenticated3D	= 11;
    case NotEnoughBalance = 12;
    case AcquirerFraud = 14;
    case Cancelled = 15;
    case ShopperCancelled = 16;
    case InvalidPin = 17;
    case PinTriesExceeded = 18;
    case PinValidationNotPossible = 19;
    case Fraud = 20;
    case NotSubmitted = 21;
    case FraudCancelled = 22;
    case TransactionNotPermitted = 23;
    case CVCDeclined = 24;
    case RestrictedCard	 = 25;
    case RevocationOfAuth = 26;
    case DeclinedNonGeneric = 27;
    case WithdrawalAmountExceeded = 28;
    case WithdrawalCountExceeded = 29;
    case IssuerSuspectedFraud = 31;
    case AVSDeclined = 32;
    case CardRequiresOnlinePin = 33;
    case NoCheckingAccountAvailableOnCard = 34;
    case NoSavingsAccountAvailableOnCard = 35;
    case MobilePINRequired = 36;
    case ContactlessFallback = 37;
    case AuthenticationRequired = 38;
    case RReqNotReceivedFromDS = 39;
    case CurrentAIDIsInPenaltyBox = 40;
    case CVMRequiredRestartPayment = 41;
    case AuthenticationError3DS = 42;
    case OnlinePINRequired = 43;
    case TryAnotherInterface = 44;
    case ChipDowngradeMode = 45;
    case TransactionBlockedByAdyen = 46;

    /**
     * @return string
     */
    public function getLabel(): string
    {
        $label = match ($this) {
            self::None => __('Unknown'),
            self::Refused => __('Refused'),
            self::Referral => __('Referral'),
            self::AcquirerError => __('Acquirer error'),
            self::BlockedCard => __('Blocked card'),
            self::ExpiredCard => __('Expired card'),
            self::InvalidAmount => __('Invalid amount'),
            self::InvalidCardNumber => __('Invalid card number'),
            self::IssuerUnavailable => __('Issuer unavailable'),
            self::NotSupported => __('Not supported'),
            self::NotAuthenticated3D => __('3D Not Authenticated'),
            self::NotEnoughBalance => __('Not enough balance'),
            self::AcquirerFraud => __('Acquirer Fraud'),
            self::Cancelled => __('Cancelled'),
            self::ShopperCancelled => __('Shopper cancelled'),
            self::InvalidPin => __('Invalid PIN'),
            self::PinTriesExceeded => __('PIN tries exceeded'),
            self::PinValidationNotPossible => __('PIN validation not possible'),
            self::Fraud => __('Fraud'),
            self::NotSubmitted => __('Not submitted'),
            self::FraudCancelled => __('FRAUD-CANCELLED'),
            self::TransactionNotPermitted => __('Transaction not permitted'),
            self::CVCDeclined => __('CVC Declined'),
            self::RestrictedCard => __('Restricted card'),
            self::RevocationOfAuth => __('Revocation of auth'),
            self::DeclinedNonGeneric => __('Declined non-generic'),
            self::WithdrawalAmountExceeded => __('Withdrawal amount exceeded'),
            self::WithdrawalCountExceeded => __('Withdrawal count exceeded'),
            self::IssuerSuspectedFraud => __('Issuer Suspected Fraud'),
            self::AVSDeclined => __('AVS Declined'),
            self::CardRequiresOnlinePin => __('Card requires online PIN'),
            self::NoCheckingAccountAvailableOnCard => __('No checking account available on card'),
            self::NoSavingsAccountAvailableOnCard => __('No savings account available on card'),
            self::MobilePINRequired => __('Mobile PIN required'),
            self::ContactlessFallback => __('Contactless fallback'),
            self::AuthenticationRequired => __('Authentication required'),
            self::RReqNotReceivedFromDS => __('RReq not received from DS'),
            self::CurrentAIDIsInPenaltyBox => __('Current AID is in penalty box'),
            self::CVMRequiredRestartPayment => __('CVM required restart payment'),
            self::AuthenticationError3DS => __('3DS Authentication Error'),
            self::OnlinePINRequired => __('Online PIN required'),
            self::TryAnotherInterface => __('Try another interface'),
            self::ChipDowngradeMode => __('Chip downgrade mode'),
            self::TransactionBlockedByAdyen => __('Transaction blocked by Adyen to prevent excessive retry fees'),
        };

        return sprintf('%s - %s', $this->value, $label);
    }
}
