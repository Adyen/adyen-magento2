<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Model\Methods;

use Adyen\Payment\Block\Form\Hpp;
use Adyen\Payment\Block\Info\Hpp as HppInfo;
use Adyen\Payment\Model\Api\PaymentRequest;
use Adyen\Payment\Model\Method\PaymentMethodInterface;
use Adyen\Payment\Model\AdyenPaymentMethod;
use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;

class Klarna extends AdyenPaymentMethod implements PaymentMethodInterface
{
    const CODE = 'adyen_klarna';
    const TX_VARIANT = 'klarna';
    const NAME = 'Klarna';

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
        return false;
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
        return false;
    }
}
