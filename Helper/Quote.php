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

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\OrderRepository;

class Quote
{
    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ProductResourceModel
     */
    private $productResourceModel;

    /**
     * @var QuoteModel
     */
    private $quoteModel;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var QuoteResourceModel
     */
    private $quoteResourceModel;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    public function __construct(
        ProductFactory $productFactory,
        ProductResourceModel $productResourceModel,
        QuoteModel $quoteModel,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel,
        OrderRepository $orderRepository
    ) {
        $this->productFactory = $productFactory;
        $this->productResourceModel = $productResourceModel;
        $this->quoteModel = $quoteModel;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param QuoteModel $newQuote
     * @param Order $previousOrder
     * @return false|QuoteModel
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function cloneQuote(QuoteModel $newQuote, Order $previousOrder)
    {
        //Add order lines to new quote
        $items = $previousOrder->getAllVisibleItems();
        /** @var Item $item */
        foreach ($items as $item) {
            //Create new product object to add as quote item
            $productId = $item->getProduct()->getId();
            $productObj = $this->productFactory->create();
            $this->productResourceModel->load($productObj, $productId);

            //Add line options and quantities
            $options = $item->getProductOptions();
            $optionsInfo = new DataObject();
            if (!empty($options['info_buyRequest'])) {
                $optionsInfo->setData($options['info_buyRequest']);
            }
            $optionsInfo->setData('qty', $item->getQtyOrdered());

            //Add quote item
            $newQuote->addProduct($productObj, $optionsInfo);
        }

        //Set as active and apply coupon code
        $this->quoteResourceModel->save(
            $newQuote->setIsActive(1)
                ->setCouponCode($previousOrder->getCouponCode())
                ->collectTotals());
        return $newQuote;
    }
}