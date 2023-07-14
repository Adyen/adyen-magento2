<?php

/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V  .
 */

namespace Adyen\Payment\Model\Methods;

use Adyen\Payment\Model\AdyenPaymentMethod;
use Adyen\Payment\Model\Method\PaymentMethodInterface;

class Applepay extends AdyenPaymentMethod implements PaymentMethodInterface
{
	public const CODE = 'adyen_applepay';
	public const TX_VARIANT = 'applepay';
	public const NAME = 'Apple Pay';

	public function supportsRecurring(): bool
	{
		return true;
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
		return true;
	}


	public function supportsSubscription(): bool
	{
		return true;
	}


	public function supportsUnscheduledCardOnFile(): bool
	{
		return true;
	}


	public function isWallet(): bool
	{
		return true;
	}
}
