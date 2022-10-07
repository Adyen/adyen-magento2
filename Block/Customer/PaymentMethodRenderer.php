<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Customer;

use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodFactory;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodInterface;
use Adyen\Payment\Model\Ui\AdyenHppConfigProvider;
use Magento\Framework\View\Element\Template\Context;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractTokenRenderer;

class PaymentMethodRenderer extends AbstractTokenRenderer
{
    /** @var Data */
    private $dataHelper;

    /** @var PaymentMethodFactory */
    private $paymentMethodFactory;


    public function __construct(
        Context $context,
        Data $dataHelper,
        PaymentMethodFactory $paymentMethodFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->dataHelper = $dataHelper;
        $this->paymentMethodFactory = $paymentMethodFactory;
    }

    public function getText(): string
    {
        try {
            $paymentMethod = $this->paymentMethodFactory::createAdyenPaymentMethod($this->getTokenDetails()['type']);
            $text = $paymentMethod->getPaymentMethodName();
        } catch (PaymentMethodException $exception) {
            $text = '';
        }

        return $text;
    }

    public function getIconUrl(): string
    {
        return $this->dataHelper->getVariantIcon($this->getTokenDetails()['type'])['url'];
    }

    public function getIconHeight(): int
    {
        return $this->dataHelper->getVariantIcon($this->getTokenDetails()['type'])['height'];
    }

    public function getIconWidth(): int
    {
        return $this->dataHelper->getVariantIcon($this->getTokenDetails()['type'])['width'];
    }

    public function canRender(PaymentTokenInterface $token): bool
    {
        return $token->getPaymentMethodCode() === AdyenHppConfigProvider::CODE;
    }
}
