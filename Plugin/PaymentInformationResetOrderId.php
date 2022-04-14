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

use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class PaymentInformationResetOrderId
{
    /**
     * Quote repository.
     *
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * PaymentInformationResetOrderId constructor.
     * @param CartRepositoryInterface $quoteRepository
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        AdyenLogger $adyenLogger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @param PaymentInformationManagementInterface $subject
     * @param $cartId
     * @return null
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId
    ) {
        try {
            $quote = $this->quoteRepository->get($cartId);
            if (preg_match('/^adyen_(?!pos_cloud$)/', $quote->getPayment()->getMethod())) {
                $quote->setReservedOrderId(null);
            }
        } catch (Exception $e) {
            $this->adyenLogger->error("Failed to reset reservedOrderId " . $e->getMessage());
        }
        return null;
    }
}
