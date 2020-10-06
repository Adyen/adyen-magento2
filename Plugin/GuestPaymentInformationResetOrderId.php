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

class GuestPaymentInformationResetOrderId
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
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * GuestPaymentInformationResetOrderId constructor.
     * @param CartRepositoryInterface $quoteRepository
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
    )
    {
        $this->quoteRepository = $quoteRepository;
        $this->adyenLogger = $adyenLogger;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * @param \Magento\Checkout\Api\GuestPaymentInformationManagementInterface $subject
     * @param $cartId
     * @return null
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Api\GuestPaymentInformationManagementInterface $subject,
        $cartId
    ) {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            $quoteId = $quoteIdMask->getQuoteId();
            $this->quoteRepository->get($quoteId)->setReservedOrderId(null);
        } catch (\Exception $e) {
            $this->adyenLogger->error("Failed to reset reservedOrderId for guest shopper" . $e->getMessage());
        }
        return null;
    }
}
