<?php

/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V  .
 */

namespace Adyen\Payment\Model\Methods;

use Adyen\Payment\Model\AdyenPaymentMethod;
use Adyen\Payment\Model\Method\PaymentMethodInterface;

class Multibanco extends AdyenPaymentMethod implements PaymentMethodInterface
{
	public const CODE = 'adyen_multibanco';
	public const TX_VARIANT = 'multibanco';
	public const NAME = 'Multibanco';

	public function supportsRecurring(): bool
	{
		return false;
	}


	public function supportsManualCapture(): bool
	{
		return false;
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
