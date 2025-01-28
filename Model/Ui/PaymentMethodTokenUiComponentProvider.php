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

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Vault;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class PaymentMethodTokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    private TokenUiComponentInterfaceFactory $componentFactory;
    private Data $dataHelper;
    private Vault $vaultHelper;

    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        Data $dataHelper,
        Vault $vaultHelper
    ) {
        $this->componentFactory = $componentFactory;
        $this->dataHelper = $dataHelper;
        $this->vaultHelper = $vaultHelper;
    }

    public function getComponentForToken(PaymentTokenInterface $paymentToken): TokenUiComponentInterface
    {
        $tokenType = $this->vaultHelper->getAdyenTokenType($paymentToken);
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        $details['icon'] = $this->dataHelper->getVariantIcon($details['type']);
        $createdAt = new \DateTime($paymentToken->getCreatedAt());
        $details['created'] = $createdAt->format('Y-m-d');
        $details['displayToken'] = $tokenType === Vault::CARD_ON_FILE;
        $details['label'] = array_key_exists(Vault::TOKEN_LABEL, $details) ? $details[Vault::TOKEN_LABEL] : '';

        return $this->componentFactory->create(
            [
                'config' => [
                    'code' => $paymentToken->getPaymentMethodCode() . '_vault',
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $details,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-vault-method'
            ]
        );
    }
}
