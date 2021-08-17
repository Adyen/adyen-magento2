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
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Checkout\Multishipping;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Model\PaymentResponse;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection;
use Adyen\Payment\Model\Ui\AdyenMultishippingConfigProvider;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Multishipping\Model\Checkout\Type\Multishipping;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Success extends \Magento\Multishipping\Block\Checkout\Success
{
    const FINAL_RESULT_CODES = array(
        PaymentResponseHandler::AUTHORISED,
        PaymentResponseHandler::PENDING,
        PaymentResponseHandler::REFUSED,
        PaymentResponseHandler::PRESENT_TO_SHOPPER
    );

    /**
     * @var PaymentResponse[]
     */
    private $paymentResponseEntities;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var SerializerInterface
     */
    private $serializerInterface;

    /**
     * @var AdyenMultishippingConfigProvider
     */
    private $configProvider;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var []
     */
    private $ordersInfo;

    public function __construct(
        Collection $paymentResponseCollection,
        Data $adyenHelper,
        StoreManagerInterface $storeManager,
        SerializerInterface $serializerInterface,
        AdyenMultishippingConfigProvider $configProvider,
        Context $context,
        Multishipping $multishipping,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = []
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        $this->serializerInterface = $serializerInterface;
        $this->configProvider = $configProvider;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct($context, $multishipping, $data);

        $orderIds = $this->getOrderIds();

        $this->paymentResponseEntities = $paymentResponseCollection
            ->getPaymentResponsesWithMerchantReferences(array_values($orderIds));

        $this->setOrderInfo(array_keys($orderIds));
    }

    /**
     * Returns true if the component must be rendered in the multishipping success page
     * @return bool
     */
    public function renderAction()
    {
        foreach ($this->paymentResponseEntities as $paymentResponseEntity) {
            if (!in_array($paymentResponseEntity['result_code'], self::FINAL_RESULT_CODES)) {
                return true;
            }
        }
        return false;
    }

    public function getPaymentResponseEntities()
    {
        return $this->paymentResponseEntities ?? [];
    }

    public function getLocale()
    {
        return $this->adyenHelper->getCurrentLocaleCode(
            $this->storeManager->getStore()->getId()
        );
    }

    public function getClientKey()
    {
        return $this->adyenHelper->getClientKey();
    }

    public function getEnvironment()
    {
        return $this->adyenHelper->getCheckoutEnvironment(
            $this->storeManager->getStore()->getId()
        );
    }

    public function getSerializedCheckoutConfig()
    {
        return $this->serializerInterface->serialize($this->configProvider->getConfig());
    }

    private function setOrderInfo($orderIds)
    {
        $orders = $this->orderRepository->getList(
            $this->searchCriteriaBuilder->addFilter('entity_id', $orderIds, 'in')->create()
        )->getItems();

        foreach ($orders as $order) {
            $payment = $order->getPayment();
            $additionalInformation = $payment->getAdditionalInformation();
            $this->ordersInfo[$order->getEntityId()]['resultCode'] = $additionalInformation['resultCode'];
            switch ($additionalInformation['resultCode']) {
                case PaymentResponseHandler::AUTHORISED:
                    $this->ordersInfo[$order->getEntityId()]['buttonLabel'] = $this->getPaymentCompletedLabel();
                    break;
                case PaymentResponseHandler::REFUSED:
                    $this->ordersInfo[$order->getEntityId()]['buttonLabel'] = $this->getPaymentFailedLabel();
                    break;
                default:
                    $this->ordersInfo[$order->getEntityId()]['buttonLabel'] = $this->getCompletePaymentLabel();
            }
        }
    }

    public function getIsPaymentCompleted(int $orderId)
    {
        // TODO check for all completed responses, not only Authorised, Refused, Pending or PresentToShopper
        return in_array($this->ordersInfo[$orderId]['resultCode'], self::FINAL_RESULT_CODES);
    }

    public function getPaymentButtonLabel(int $orderId)
    {
        return $this->ordersInfo[$orderId]['buttonLabel'];
    }

    public function getPaymentCompletedLabel()
    {
        return __('Payment Completed');
    }

    public function getCompletePaymentLabel()
    {
        return __('Complete Payment');
    }

    public function getPaymentFailedLabel()
    {
        return __('Payment Failed');
    }
}
