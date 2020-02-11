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
 * Copyright (c) 2020 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;

class AdyenOrderPaymentStatus implements \Adyen\Payment\Api\AdyenOrderPaymentStatusInterface
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * AdyenOrderPaymentStatus constructor.
     *
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    )
    {
        $this->orderRepository = $orderRepository;
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * {@inheritDoc}
     * @throws MagentoNoSuchEntityException
     * @throws AdyenException
     */
    public function getOrderPaymentStatus($orderId)
    {
        try {
            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();

            if ($payment->getMethod() === AdyenCcConfigProvider::CODE) {
                return $this->adyenHelper->buildThreeDS2ProcessResponseJson(
                    $payment->getAdditionalInformation('threeDSType'),
                    $payment->getAdditionalInformation('threeDS2Token')
                );
            } else {
                return $result;
            }
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error("Exception: " . $e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__('This order no longer exists.'));
        }

        return $result;
    }
}
