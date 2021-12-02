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

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment as QuotePaymentResourceModel;
use Magento\Sales\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;

class AdyenClearStateDataObserver implements ObserverInterface
{

    const ERROR_MSG = "State data was not cleaned-up: %s";

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;
    /**
     * @var QuotePaymentResourceModel
     */
    private $quotePaymentResourceModel;
    /**
     * @var OrderPaymentResourceModel
     */
    private $orderPaymentResourceModel;
    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuotePaymentResourceModel $quotePaymentResourceModel,
        OrderPaymentResourceModel $orderPaymentResourceModel,
        AdyenLogger $adyenLogger

    ) {
        $this->cartRepository = $cartRepository;
        $this->quotePaymentResourceModel = $quotePaymentResourceModel;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
        $this->adyenLogger = $adyenLogger;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();
        $paymentMethod = $order->getPayment()->getMethod();
        if (strpos($paymentMethod, 'adyen_') !== false) {

            $quote = $this->cartRepository->get($order->getQuoteId());

            try {
                $quotePayment = $quote->getPayment();
                $quotePayment->unsAdditionalInformation(StateData::STATE_DATA_KEY);
                $this->quotePaymentResourceModel->save($quotePayment);
            } catch (\Exception $exception) {
                $this->adyenLogger->addError(__(self::ERROR_MSG, $exception->getMessage()));
            }

            try {
                $orderPayment = $order->getPayment();
                $orderPayment->unsAdditionalInformation(StateData::STATE_DATA_KEY);
                $this->orderPaymentResourceModel->save($orderPayment);
            } catch (\Exception $exception) {
                $this->adyenLogger->addError(__(self::ERROR_MSG, $exception->getMessage()));
            }
        }
    }
}
