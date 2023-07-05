<?php

/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V  .
 */

namespace Adyen\Payment\Model\Methods;

use Adyen\Payment\Model\AdyenPaymentMethod;
use Adyen\Payment\Model\Method\PaymentMethodInterface;

class Facilypay3x extends AdyenPaymentMethod implements PaymentMethodInterface
{
	public const CODE = 'adyen_facilypay_3x';
	public const TX_VARIANT = 'facilypay_3x';
	public const NAME = '3x Oney';

	public function supportsRecurring(): bool
	{
		return false;
	}


	public function supportsManualCapture(): bool
	{
		return true;
	}


	public function supportsAutoCapture(): bool
	{
		return true;
	}


	public function supportsCardOnFile(): bool
	{
		return false;
	}


	public function supportsSubscription(): bool
	{
		return false;
	}


	public function supportsUnscheduledCardOnFile(): bool
	{
		return false;
	}


	public function isWallet(): bool
	{
		return false;
	}
}
