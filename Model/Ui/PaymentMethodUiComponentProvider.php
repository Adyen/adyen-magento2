<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Recurring;
use Adyen\Payment\Helper\Vault;
use Exception;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class PaymentMethodUiComponentProvider extends AdyenUiComponentProvider implements TokenUiComponentProviderInterface
{

    private Vault $vaultHelper;

    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        Data $dataHelper,
        Vault $vaultHelper
    ) {
        parent::__construct($componentFactory, $dataHelper);
        $this->vaultHelper = $vaultHelper;
    }

    /**
     * Get UI component for token
     *
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     * @throws PaymentMethodException
     * @throws Exception
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken): TokenUiComponentInterface
    {
        $tokenType = $this->vaultHelper->getAdyenTokenType($paymentToken);
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        $details['icon'] = $this->dataHelper->getVariantIcon($details['type']);
        $createdAt = new \DateTime($paymentToken->getCreatedAt());
        $details['created'] = $createdAt->format('Y-m-d');
        $details['displayToken'] = $tokenType === Recurring::CARD_ON_FILE;
        $details['label'] = array_key_exists(Vault::TOKEN_LABEL, $details) ? $details[Vault::TOKEN_LABEL] : '';

        return $this->componentFactory->create(
            [
                'config' => [
                    'code' => $paymentToken->getPaymentMethodCode() . '_vault',
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $details,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Adyen_Payment/js/view/payment/method-renderer/payment_method_vault'
            ]
        );
    }
}
