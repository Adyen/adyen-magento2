<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Plugin;
use Magento\Quote\Api\CartRepositoryInterface;

class PaymentInformationResetOrderId
{
    /**
     * Quote repository.
     *
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $adyenLogger;

    /**
     * PaymentInformationResetOrderId constructor.
     * @param CartRepositoryInterface $quoteRepository
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    )
    {
        $this->quoteRepository = $quoteRepository;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param \Magento\Checkout\Api\PaymentInformationManagementInterface $subject
     * @param $cartId
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Api\PaymentInformationManagementInterface $subject,
        $cartId
    ) {
        try {
            $this->quoteRepository->get($cartId)->setReservedOrderId(null);
        } catch (\Exception $e) {
            $this->adyenLogger->error("Failed to reset reservedOrderId " . $e->getMessage());
        }
        return null;
    }
}
