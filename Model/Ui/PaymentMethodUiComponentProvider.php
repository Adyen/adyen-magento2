<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodFactory;
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
    private PaymentMethodFactory $paymentMethodFactory;

    /**
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param Data $dataHelper
     * @param Vault $vaultHelper
     * @param PaymentMethodFactory $paymentMethodFactory
     */
    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        Data $dataHelper,
        Vault $vaultHelper,
        PaymentMethodFactory $paymentMethodFactory
    ) {
        parent::__construct($componentFactory, $dataHelper);
        $this->vaultHelper = $vaultHelper;
        $this->paymentMethodFactory = $paymentMethodFactory;
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
        // If payment method cannot be created based on the type, this implies that a card token was created using
        // an hpp method (googlepay/applepay etc.). Hence, return the card component for this token.
        try {
            $adyenPaymentMethod = $this->paymentMethodFactory::createAdyenPaymentMethod($details['type']);
        } catch (PaymentMethodException $exception) {
            return $this->getCardComponentForToken($paymentToken);
        }

        $details['icon'] = $this->dataHelper->getVariantIcon($details['type']);
        $createdAt = new \DateTime($paymentToken->getCreatedAt());
        $details['created'] = $createdAt->format('Y-m-d');
        $details['displayToken'] = $tokenType === Recurring::CARD_ON_FILE;
        $details['label'] = $adyenPaymentMethod->getLabel();

        return $this->componentFactory->create(
            [
                'config' => [
                    'code' => AdyenHppConfigProvider::HPP_VAULT_CODE,
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $details,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Adyen_Payment/js/view/payment/method-renderer/payment_method_vault'
            ]
        );
    }
}
