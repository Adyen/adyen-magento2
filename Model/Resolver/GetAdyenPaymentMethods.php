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
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver;

use Adyen\Payment\Helper\PaymentMethods;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class GetAdyenPaymentMethods implements ResolverInterface
{

    /**
     * @var GetCartForUser
     */
    protected $getCartForUser;
    /**
     * @var PaymentMethods
     */
    protected $_paymentMethodsHelper;
    /**
     * @var Json
     */
    protected $jsonSerializer;


    /**
     * GetAdyenPaymentMethods constructor.
     * @param GetCartForUser $getCartForUser
     * @param PaymentMethods $paymentMethodsHelper
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        PaymentMethods $paymentMethodsHelper,
        Json $jsonSerializer
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->_paymentMethodsHelper = $paymentMethodsHelper;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {

        if (empty($args['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing'));
        }
        $maskedCartId = $args['cart_id'];

        $currentUserId = $context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);

        $country = null;
        $shippingAddress = $cart->getShippingAddress();
        if ($shippingAddress) {
            $country = $shippingAddress->getCountryId();
        }

        $adyenPaymentMethodsResponse = $this->_paymentMethodsHelper->getPaymentMethods($cart->getId(), $country);

        return $adyenPaymentMethodsResponse ? $this->preparePaymentMethodGraphQlResponse($adyenPaymentMethodsResponse) : [];
    }

    /**
     * @param $adyenPaymentMethodsResponse
     * @return mixed
     */
    public function preparePaymentMethodGraphQlResponse($adyenPaymentMethodsResponse)
    {
        $adyenPaymentMethodsResponse = $this->jsonSerializer->unserialize($adyenPaymentMethodsResponse);

        if (isset($adyenPaymentMethodsResponse['paymentMethodsExtraDetails'])) {
            //moved type from key to value because of graphql type limitations
            $extraDetails = [];
            foreach ($adyenPaymentMethodsResponse['paymentMethodsExtraDetails'] as $key => $paymentMethodsExtraDetails) {
                $paymentMethodsExtraDetails['type'] = $key;
                $extraDetails[] = $paymentMethodsExtraDetails;
            }
            $adyenPaymentMethodsResponse['paymentMethodsExtraDetails'] = $extraDetails;
        }
        return $adyenPaymentMethodsResponse;
    }
}
