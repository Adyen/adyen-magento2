<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Helper\GiftcardPayment;
use Exception;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;

class GetAdyenRedeemedGiftcards implements ResolverInterface
{
    private GiftcardPayment $giftcardPayment;
    private Json $jsonSerializer;
    private QuoteIdMaskFactory $quoteIdMaskFactory;


    public function __construct(
        GiftcardPayment $giftcardPayment,
        Json            $jsonSerializer,
        QuoteIdMaskFactory $quoteIdMaskFactory
    )
    {
        $this->giftcardPayment = $giftcardPayment;
        $this->jsonSerializer = $jsonSerializer;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    public function resolve(
        Field       $field,
                    $context,
        ResolveInfo $info,
        array       $value = null,
        array       $args = null
    )
    {
        if (empty($args['cartId'])) {
            throw new GraphQlInputException(__('Required parameter "cartId" is missing'));
        }
        $cartId = $args['cartId'];
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();
        $quoteId = (int)$quoteId;
        try {
            $redeemedGiftcardsJson = $this->giftcardPayment->fetchRedeemedGiftcards($quoteId);
            $redeemedGiftcardsData = $this->jsonSerializer->unserialize($redeemedGiftcardsJson);
        } catch (\Exception $e) {
            throw new GraphQlInputException(__('An error occurred while fetching redeemed gift cards: %1', $e->getMessage()));
        }

        return [ 'giftcards' => $redeemedGiftcardsData['redeemedGiftcards']]; // Adjust according to your schema
    }
}


