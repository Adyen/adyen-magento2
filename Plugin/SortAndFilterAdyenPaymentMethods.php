<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Plugin;

use Adyen\AdyenException;
use Adyen\Payment\Helper\PaymentMethodsFilter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;

class SortAndFilterAdyenPaymentMethods
{
    /**
     * @param PaymentMethodsFilter $paymentMethodsFilter
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        private readonly PaymentMethodsFilter $paymentMethodsFilter,
        private readonly CartRepositoryInterface $quoteRepository
    ) {}

    /**
     * @param PaymentMethodManagementInterface $paymentMethodManagement
     * @param array $paymentMethodManagementResult
     * @param int $cartId
     * @return array
     * @throws AdyenException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function afterGetList(
        PaymentMethodManagementInterface $paymentMethodManagement,
        array $paymentMethodManagementResult,
        int $cartId
    ): array {
        list($filteredPaymentMethods) = $this->paymentMethodsFilter->sortAndFilterPaymentMethods(
            $paymentMethodManagementResult,
            $this->quoteRepository->get($cartId)
        );

        return $filteredPaymentMethods;
    }
}
