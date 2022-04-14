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
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestPaymentInformationResetOrderId
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
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * GuestPaymentInformationResetOrderId constructor.
     * @param CartRepositoryInterface $quoteRepository
     * @param AdyenLogger $adyenLogger
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        AdyenLogger $adyenLogger,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->adyenLogger = $adyenLogger;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * @param GuestPaymentInformationManagementInterface $subject
     * @param $cartId
     * @return null
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagementInterface $subject,
        $cartId
    ) {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            $quoteId = $quoteIdMask->getQuoteId();
            $quote = $this->quoteRepository->get($quoteId);
            if (preg_match('/^adyen_(?!pos_cloud$)/', $quote->getPayment()->getMethod())) {
                $quote->setReservedOrderId(null);
            }
        } catch (Exception $e) {
            $this->adyenLogger->error("Failed to reset reservedOrderId for guest shopper" . $e->getMessage());
        }
        return null;
    }
}
