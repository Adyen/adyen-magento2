<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

// phpcs:ignore
namespace Adyen\Payment\Controller\Return;

use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\PaymentsDetails;
use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Sales\OrderRepository;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;

class Index extends Action
{
    const DETAILS_ALLOWED_PARAM_KEYS = [
        'MD',
        'PaReq',
        'PaRes',
        'billingToken',
        'cupsecureplus.smscode',
        'facilitatorAccessToken',
        'oneTimePasscode',
        'orderID',
        'payerID',
        'payload',
        'paymentID',
        'paymentStatus',
        'redirectResult',
        'threeDSResult',
        'threeds2.challengeResult',
        'threeds2.fingerprint'
    ];

    private ?OrderInterface $order = null;

    /**
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param Session $session
     * @param AdyenLogger $adyenLogger
     * @param StoreManagerInterface $storeManager
     * @param Quote $quoteHelper
     * @param Config $configHelper
     * @param PaymentsDetails $paymentsDetailsHelper
     * @param PaymentResponseHandler $paymentResponseHandler
     * @param CartRepositoryInterface $cartRepository
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        Context                  $context,
        private readonly OrderFactory             $orderFactory,
        private readonly Session                  $session,
        private readonly AdyenLogger              $adyenLogger,
        private readonly StoreManagerInterface    $storeManager,
        private readonly Quote                    $quoteHelper,
        private readonly Config                   $configHelper,
        private readonly PaymentsDetails $paymentsDetailsHelper,
        private readonly PaymentResponseHandler $paymentResponseHandler,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderRepository $orderRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(): void
    {
        // Receive all params as this could be a GET or POST request
        $redirectResponse = $this->getRequest()->getParams();
        $storeId = $this->storeManager->getStore()->getId();

        if ($redirectResponse) {
            $result = $this->validateRedirectResponse($redirectResponse);

            // Adjust the success path, fail path, and restore quote based on if it is a multishipping quote
            if (
                !empty($redirectResponse['merchantReference']) &&
                $this->quoteHelper->getIsQuoteMultiShippingWithMerchantReference($redirectResponse['merchantReference'])
            ) {
                $successPath = $failPath = 'multishipping/checkout/success';
                $setQuoteAsActive = true;
            } else {
                $successPath = $this->configHelper->getAdyenAbstractConfigData('custom_success_redirect_path', $storeId) ??
                    'checkout/onepage/success';
                $failPath = $this->configHelper->getAdyenAbstractConfigData('return_path', $storeId);
                $setQuoteAsActive = false;
            }

            if ($result) {
                $quote = $this->session->getQuote();
                $quote->setIsActive($setQuoteAsActive);
                $this->cartRepository->save($quote);

                // Add OrderIncrementId to redirect parameters for headless support.
                $redirectParams = $this->configHelper->getAdyenAbstractConfigData('custom_success_redirect_path', $storeId)
                    ? ['_query' => ['order_increment_id' => $this->order->getIncrementId()]]
                    : [];
                $this->_redirect($successPath, $redirectParams);
            } else {
                $this->adyenLogger->addAdyenResult(
                    sprintf(
                        'Payment for order %s was unsuccessful, ' .
                        'it will be cancelled when the OFFER_CLOSED notification has been processed.',
                        isset($this->order) ? $this->order->getIncrementId() :
                            ($redirectResponse['merchantReference'] ?? null)
                    )
                );

                $this->session->restoreQuote();
                $this->messageManager->addError(__('Your payment failed, Please try again later'));

                $this->_redirect($failPath);
            }
        } else {
            $this->_redirect($this->configHelper->getAdyenAbstractConfigData('return_path', $storeId));
        }
    }

    /**
     * @throws LocalizedException
     * @throws Exception
     */
    protected function validateRedirectResponse(array $redirectResponse): bool
    {
        $this->adyenLogger->addAdyenResult('Processing redirect response');
        $order = $this->getOrder($redirectResponse['merchantReference'] ?? null);

        try {
            // Make paymentsDetails call to validate the payment
            $request["details"] = $redirectResponse;
            $paymentsDetailsResponse = $this->paymentsDetailsHelper->initiatePaymentDetails($order, $request);
        } catch (Exception $e) {
            $paymentsDetailsResponse['error'] = $e->getMessage();
        }

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $order
        );

        if ($result) {
            $this->order = $order;
        }

        return $result;
    }

    /**
     * @throws LocalizedException
     */
    private function getOrder(?string $incrementId = null): Order
    {
        try {
            if ($incrementId !== null) {
                $order = $this->orderRepository->getByIncrementId($incrementId);
            } else {
                $order = $this->session->getLastRealOrder();
            }
            if (!$order->getId()) {
                throw new NoSuchEntityException();
            }
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Order cannot be loaded'));
        }

        return $order;
    }
}
