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

namespace Adyen\Payment\Plugin;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class PaymentVaultDeleteToken
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /** @var PaymentMethods */
    protected $paymentMethodsHelper;

    /** @var Data */
    protected $dataHelper;

    /** @var AdyenLogger */
    protected $adyenLogger;

    /** @var Requests */
    protected $requestsHelper;

    public function __construct(
        StoreManagerInterface $storeManager,
        PaymentMethods $paymentMethodsHelper,
        Data $dataHelper,
        AdyenLogger $adyenLogger,
        Requests $requestsHelper
    ) {
        $this->storeManager = $storeManager;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->dataHelper = $dataHelper;
        $this->adyenLogger = $adyenLogger;
        $this->requestsHelper = $requestsHelper;
    }

    /**
     * @param PaymentTokenRepositoryInterface $subject
     * @param PaymentTokenInterface $paymentToken
     * @return PaymentTokenInterface[]|void
     * @throws NoSuchEntityException
     */
    public function beforeDelete(
        PaymentTokenRepositoryInterface $subject,
        PaymentTokenInterface $paymentToken
    ) {
        $paymentMethodCode = $paymentToken->getPaymentMethodCode();
        $storeId = $this->storeManager->getStore()->getStoreId();

        if (is_null($paymentMethodCode) || !$this->paymentMethodsHelper->isAdyenPayment($paymentMethodCode)) {
            return [$paymentToken];
        }

        $request = $this->createDisableTokenRequest($paymentToken);

        try {
            $client = $this->dataHelper->initializeAdyenClient($storeId);
            $recurringService = $this->dataHelper->createAdyenRecurringService($client);
            $recurringService->disable($request);
        } catch (AdyenException $e) {
            $this->adyenLogger->error(sprintf(
            'Error while attempting to disable token with id %s: %s',
            $paymentToken->getEntityId(),
            $e->getMessage())
            );
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error(sprintf(
            'No such entity while attempting to disable token with id %s: %s',
            $paymentToken->getEntityId(),
            $e->getMessage())
            );
        }
    }

    private function createDisableTokenRequest(PaymentTokenInterface $paymentToken): array
    {
        return [
            Requests::MERCHANT_ACCOUNT => $this->dataHelper->getAdyenMerchantAccount(
                $paymentToken->getPaymentMethodCode()
            ),
            Requests::SHOPPER_REFERENCE =>
                $this->requestsHelper->getShopperReference($paymentToken->getCustomerId(), null),
            Requests::RECURRING_DETAIL_REFERENCE => $paymentToken->getGatewayToken()
        ];
    }
}
