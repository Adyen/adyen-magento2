<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Customer;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Vault;
use Magento\Framework\View\Element\Template\Context;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractTokenRenderer;

class PaymentMethodRenderer extends AbstractTokenRenderer
{
    private Data $dataHelper;
    private Vault $vaultHelper;

    public function __construct(
        Context $context,
        Data $dataHelper,
        Vault $vaultHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->dataHelper = $dataHelper;
        $this->vaultHelper = $vaultHelper;
    }

    public function getText(): string
    {
        $details = $this->getTokenDetails();

        return array_key_exists(Vault::TOKEN_LABEL, $details) ? $details[Vault::TOKEN_LABEL] : '';
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
        $details = json_decode($token->getTokenDetails() ?: '{}', true);
        $showToken = array_key_exists(Vault::TOKEN_TYPE, $details) &&
            $details[Vault::TOKEN_TYPE] === Vault::CARD_ON_FILE;

        return $this->vaultHelper->isAdyenPaymentCode($token->getPaymentMethodCode()) && $showToken;
    }
}
