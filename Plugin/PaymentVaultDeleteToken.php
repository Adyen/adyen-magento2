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

use Adyen\Payment\Model\Api\PaymentRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class PaymentVaultDeleteToken
{
    /**
     * @var PaymentRequest
     */
    protected $paymentRequest;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * PaymentVaultDeleteToken constructor.
     *
     * @param PaymentRequest $paymentRequest
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        PaymentRequest $paymentRequest,
        StoreManagerInterface $storeManager
    ) {
        $this->paymentRequest = $paymentRequest;
        $this->storeManager = $storeManager;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function beforeDelete(
        PaymentTokenRepositoryInterface $subject,
        PaymentTokenInterface $paymentToken
    ) {
        $paymentMethodCode = $paymentToken->getPaymentMethodCode();
        $storeId = $this->storeManager->getStore()->getStoreId();

        if (is_null($paymentMethodCode) || strpos($paymentMethodCode, 'adyen_') !== 0) {
            return [$paymentToken];
        }

        try {
            $this->paymentRequest->disableRecurringContract(
                $paymentToken->getGatewayToken(),
                $paymentToken->getCustomerId(),
                $storeId
            );
        } catch (\Exception $e) {
            throw new LocalizedException(__('Failed to disable this contract'));
        }
    }
}
