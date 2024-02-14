<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\TokenDeactivateInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Exception;

class TokenDeactivate implements TokenDeactivateInterface
{
    /**
     * @var PaymentTokenRepositoryInterface
     */
    protected PaymentTokenRepositoryInterface $paymentTokenRepository;

    /**
     * @var PaymentTokenManagement
     */
    protected PaymentTokenManagement $paymentTokenManagement;

    /**
     * @var AdyenLogger
     */
    protected AdyenLogger $adyenLogger;

    /**
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        PaymentTokenManagement $paymentTokenManagement,
        AdyenLogger $adyenLogger
    ) {
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param string $paymentToken
     * @param string $paymentMethodCode
     * @param int $customerId
     * @return bool
     */
    public function deactivateToken(string $paymentToken, string $paymentMethodCode, int $customerId): bool
    {
        $paymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $paymentToken,
            $paymentMethodCode,
            $customerId
        );

        if (isset($paymentToken)) {
            try {
                return $this->paymentTokenRepository->delete($paymentToken);
            } catch (Exception $e) {
                $this->adyenLogger->error(sprintf(
                    'Error while attempting to deactivate token with id %s: %s',
                    $paymentToken->getEntityId(),
                    $e->getMessage()
                ));
            }
        }

        return false;
    }
}
