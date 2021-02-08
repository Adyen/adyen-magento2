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
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Sales\Model\Order;

class Quote
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuoteRepository $quoteRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param QuoteModel $newQuote
     * @param Order $previousOrder
     * @return false|QuoteModel
     * @throws LocalizedException
     */
    public function cloneQuote(QuoteModel $newQuote, Order $previousOrder)
    {
        $oldQuote = $this->quoteRepository->get($previousOrder->getQuoteId());
        $newQuote->merge($oldQuote)->collectTotals();
        $newQuote->setShippingAddress($oldQuote->getShippingAddress());
        $newQuote->setBillingAddress($oldQuote->getBillingAddress());
        $this->cartRepository->save($newQuote);
        return $newQuote;
    }
}
