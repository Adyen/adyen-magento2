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
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Quote\Model\Quote\AddressFactory as QuoteAddressFactory;
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;
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
    /**
     * @var QuoteAddressFactory
     */
    private $quoteAddressFactory;
    /**
     * @var QuoteAddressResource
     */
    private $quoteAddressResource;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuoteRepository $quoteRepository,
        QuoteAddressFactory $quoteAddressFactory,
        QuoteAddressResource $quoteAddressResource
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteRepository = $quoteRepository;
        $this->quoteAddressFactory = $quoteAddressFactory;
        $this->quoteAddressResource = $quoteAddressResource;
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
        $this->cloneQuoteAddresses($oldQuote, $newQuote);
        return $newQuote;
    }

    /**
     * @param QuoteModel $oldQuote
     * @param QuoteModel $newQuote
     * @return false|QuoteModel
     * @throws AlreadyExistsException
     */
    protected function cloneQuoteAddresses(QuoteModel $oldQuote, QuoteModel $newQuote)
    {
        //New quote address objects
        $quoteShippingAddress = $this->quoteAddressFactory->create();
        $quoteBillingAddress = $this->quoteAddressFactory->create();

        //Loading with old quote address data
        $this->quoteAddressResource->load($quoteShippingAddress, $oldQuote->getShippingAddress()->getId());
        $this->quoteAddressResource->load($quoteBillingAddress, $oldQuote->getBillingAddress()->getId());

        //Unsetting PK and setting new quote ID
        $quoteShippingAddress->setQuoteId($newQuote->getId())->unsetData('address_id');
        $quoteBillingAddress->setQuoteId($newQuote->getId())->unsetData('address_id');

        //Saving new addresses
        $this->quoteAddressResource->save($quoteShippingAddress);
        $this->quoteAddressResource->save($quoteBillingAddress);
    }
}
