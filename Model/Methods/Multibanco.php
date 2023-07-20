<?php

/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V  .
 */

namespace Adyen\Payment\Model\Methods;

use Adyen\Payment\Block\Form\Hpp;
use Adyen\Payment\Block\Info\Hpp as HppInfo;
use Adyen\Payment\Model\AdyenPaymentMethod;
use Adyen\Payment\Model\Api\PaymentRequest;
use Adyen\Payment\Model\Method\PaymentMethodInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;

class Multibanco extends AdyenPaymentMethod implements PaymentMethodInterface
{
	public const CODE = 'adyen_multibanco';
	public const TX_VARIANT = 'multibanco';
	public const NAME = 'Multibanco';

    public function __construct(
        PaymentRequest $paymentRequest,
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null
    ) {
        $code = self::CODE;
        $formBlockType = Hpp::class;
        $infoBlockType = HppInfo::class;

        parent::__construct($paymentRequest, $eventManager, $valueHandlerPool,
            $paymentDataObjectFactory, $code, $formBlockType, $infoBlockType, $commandPool, $validatorPool);
    }

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
