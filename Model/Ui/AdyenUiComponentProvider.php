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

use Adyen\Payment\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;

class AdyenUiComponentProvider
{
    const GATEWAY_TOKEN = 'gatewayToken';
    const TOKEN_ID = 'tokenId';

    /**
     * @var TokenUiComponentInterfaceFactory
     */
    protected $componentFactory;

    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param Data $dataHelper
     */
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

    /**
     * Get UI component for token
     *
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     */
    public function getCardComponentForToken(PaymentTokenInterface $paymentToken): TokenUiComponentInterface
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
                'name' => 'Adyen_Payment/js/view/payment/method-renderer/vault'
            ]
        );
    }
}
