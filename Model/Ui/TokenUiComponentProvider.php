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

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\Recurring;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    /**
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param Data $dataHelper
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        Data $dataHelper,
        Vault $vaultHelper
    ) {
        $this->componentFactory = $componentFactory;
        $this->dataHelper = $dataHelper;
        $this->vaultHelper = $vaultHelper;
    }

    /**
     * Get UI component for token
     *
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken): TokenUiComponentInterface
    {
        $tokenType = $this->vaultHelper->getAdyenTokenType($paymentToken);
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        $details['icon'] = $this->dataHelper->getVariantIcon($details['type']);
        $createdAt = new \DateTime($paymentToken->getCreatedAt());
        $details['created'] = $createdAt->format('Y-m-d');
        $details['displayToken'] = $tokenType === Recurring::CARD_ON_FILE || is_null($tokenType);

        return $this->componentFactory->create(
            [
                'config' => [
                    'code' => AdyenCcConfigProvider::CC_VAULT_CODE,
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $details,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Adyen_Payment/js/view/payment/method-renderer/adyen-vault-method'
            ]
        );
    }
}
