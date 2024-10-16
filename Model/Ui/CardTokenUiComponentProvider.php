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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class CardTokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    const GATEWAY_TOKEN = 'gatewayToken';
    const TOKEN_ID = 'tokenId';

    protected TokenUiComponentInterfaceFactory $componentFactory;
    protected Data $dataHelper;
    protected UrlInterface $url;
    protected RequestInterface $request;

    public function __construct(
        TokenUiComponentInterfaceFactory $componentFactory,
        Data $dataHelper,
        UrlInterface $url,
        RequestInterface $request
    ) {
        $this->componentFactory = $componentFactory;
        $this->dataHelper = $dataHelper;
        $this->url = $url;
        $this->request = $request;
    }

    public function getComponentForToken(PaymentTokenInterface $paymentToken): TokenUiComponentInterface
    {
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        $details['icon'] = $this->dataHelper->getVariantIcon($details['type']);

        return $this->componentFactory->create(
            [
                'config' => [
                    'code' => AdyenCcConfigProvider::CC_VAULT_CODE,
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $details,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash(),
                    self::GATEWAY_TOKEN => $paymentToken->getGatewayToken(),
                    self::TOKEN_ID => $paymentToken->getEntityId(),
                    'successPage' => $this->url->getUrl(
                        'checkout/onepage/success',
                        ['_secure' => $this->request->isSecure()]
                    )
                ],
                'name' => 'Adyen_Payment/js/view/payment/method-renderer/adyen-cc-vault-method'
            ]
        );
    }
}
