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

use Adyen\Payment\Api\AdyenOrderPaymentStatusInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\PaymentResponse;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection;
use Adyen\Payment\Model\Ui\AdyenMultishippingConfigProvider;
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
        PaymentResponseHandler::PRESENT_TO_SHOPPER
    );

    /**
     * @var Collection
     */
    private $paymentResposeCollection;

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
     * @var \Magento\Checkout\Model\CompositeConfigProvider
     */
    private $configProvider;
    /**
     * @var AdyenOrderPaymentStatusInterface
     */
    private $adyenOrderPaymentStatus;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param Context $context
     * @param Multishipping $multishipping
     * @param array $data
     */
    public function __construct(
        Collection $paymentResponseCollection,
        Data $adyenHelper,
        StoreManagerInterface $storeManager,
        SerializerInterface $serializerInterface,
        AdyenMultishippingConfigProvider $configProvider,
        Context $context,
        Multishipping $multishipping,
        OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        $this->paymentResposeCollection = $paymentResponseCollection;
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        $this->serializerInterface = $serializerInterface;
        $this->configProvider = $configProvider;
        $this->orderRepository = $orderRepository;
        parent::__construct($context, $multishipping, $data);

        $orderIds = $this->getOrderIds();

        $this->paymentResponseEntities = $this->paymentResposeCollection
            ->getPaymentResponsesWithMerchantReferences(array_values($orderIds));
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

    public function getIsPaymentCompleted(int $orderId)
    {
        $order = $this->orderRepository->get($orderId);

        if (empty($order)) {
            return false;
        }

        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();

        if (empty($additionalInformation['resultCode'])) {
            return false;
        }

        // TODO check for all completed responses, not only Authorised
        return $additionalInformation['resultCode'] === PaymentResponseHandler::AUTHORISED;
    }
}
