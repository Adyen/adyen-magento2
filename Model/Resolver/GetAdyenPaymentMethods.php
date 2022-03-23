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

use Adyen\AdyenException;
use Adyen\Payment\Exception\GraphQlAdyenException;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
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
     * @var AdyenLogger
     */
    protected $adyenLogger;


    /**
     * GetAdyenPaymentMethods constructor.
     * @param GetCartForUser $getCartForUser
     * @param PaymentMethods $paymentMethodsHelper
     * @param Json $jsonSerializer
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        GetCartForUser $getCartForUser,
        PaymentMethods $paymentMethodsHelper,
        Json $jsonSerializer,
        AdyenLogger $adyenLogger
    ) {
        $this->getCartForUser = $getCartForUser;
        $this->_paymentMethodsHelper = $paymentMethodsHelper;
        $this->jsonSerializer = $jsonSerializer;
        $this->adyenLogger = $adyenLogger;
    }


    /**
     * @inheritdoc
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|Value|mixed
     * @throws GraphQlAdyenException
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
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

        try {
            $cart = $this->getCartForUser->execute($maskedCartId, $currentUserId, $storeId);

            $country = null;
            $shippingAddress = $cart->getShippingAddress();
            if ($shippingAddress) {
                $country = $shippingAddress->getCountryId();
            }
            $adyenPaymentMethodsResponse = $this->_paymentMethodsHelper->getPaymentMethods($cart->getId(), $country);

            return $adyenPaymentMethodsResponse ? $this->preparePaymentMethodGraphQlResponse($adyenPaymentMethodsResponse) : [];
        } catch (GraphQlAuthorizationException | GraphQlInputException | GraphQlNoSuchEntityException $exception) {
            $this->adyenLogger->addError(sprintf('GraphQl payment methods call failed with error message: %s', $exception->getMessage()));
            throw new $exception;
        } catch (Exception $exception) {
            $this->adyenLogger->addError(sprintf('GraphQl payment methods call failed with error message: %s', $exception->getMessage()));
            // In the future, use the message and the code passed by the exception. Since currently the message and code are not
            // being passed, use this generic message.
            throw new GraphQlAdyenException(__('An unknown error has occurred'), null, 000);
        }
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
