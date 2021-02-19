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

use Magento\Quote\Model\Quote\Address;
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
     * @var QuoteRepository
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
        foreach ([Address::ADDRESS_TYPE_SHIPPING, Address::ADDRESS_TYPE_BILLING] as $type) {
            $quoteAddress = $this->quoteAddressFactory->create();
            if ($type == Address::ADDRESS_TYPE_SHIPPING) {
                $this->quoteAddressResource->load($quoteAddress, $oldQuote->getShippingAddress()->getId());
            } else {
                $this->quoteAddressResource->load($quoteAddress, $oldQuote->getBillingAddress()->getId());
            }
            $quoteAddress->setQuoteId($newQuote->getId())->unsetData('address_id');
            $this->quoteAddressResource->save($quoteAddress);
        }
    }
}
