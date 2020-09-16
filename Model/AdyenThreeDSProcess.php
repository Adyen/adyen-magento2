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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\AdyenThreeDSProcessInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Payment as OrderPaymentResource;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;

class AdyenThreeDSProcess implements AdyenThreeDSProcessInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $_adyenHelper;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $_adyenLogger;
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $_orderRepository;
    /**
     * @var OrderPaymentExtensionInterfaceFactory
     */
    private $paymentExtensionFactory;
    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;
    /**
     * @var OrderPaymentResource
     */
    private $orderPaymentResource;
    /**
     * @var PaymentTokenFactoryInterface
     */
    private $paymentTokenFactory;
    /**
     * @var Api\PaymentRequest
     */
    private $_paymentRequest;

    /**
     * AdyenThreeDS2Process constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository ,
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param Api\PaymentRequest $paymentRequest
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param OrderPaymentResource $orderPaymentResource
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        OrderPaymentResource $orderPaymentResource,
        PaymentTokenFactoryInterface $paymentTokenFactory
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_orderRepository = $orderRepository;
        $this->_adyenLogger = $adyenLogger;
        $this->_paymentRequest = $paymentRequest;

        $this->orderFactory = $orderFactory;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->serializer = $serializer;
        $this->orderPaymentResource = $orderPaymentResource;
        $this->paymentTokenFactory = $paymentTokenFactory;
    }

    /**
     * {@inheridoc}
     */
    public function headlessAuthorize($orderId, $payload)
    {
        // Decode payload from frontend
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('3D secure failed because the request was not a valid JSON')
            );
        }

        $order = $this->orderFactory->create()->load($orderId);
        return $this->authorize(
            $order,
            $payload['MD'],
            $payload['PaRes']
        );
    }

    /**
     * {@inheridoc}
     */
    public function authorize(Order $order, $requestMD, $requestPaRes): string
    {
        $active = null;
        $success = null;
        if ($order->getPayment()) {
            $active = $order->getPayment()->getAdditionalInformation('3dActive');
            $success = $order->getPayment()->getAdditionalInformation('3dSuccess');
            $checkoutAPM = $order->getPayment()->getAdditionalInformation('checkoutAPM');
        }

        // check if 3D secure is active. If not just go to success page
        if ($active && $success != true) {
            $this->_adyenLogger->addAdyenResult("3D secure is active");

            // check if the GET request contains the required 3DS params
            if ($requestPaRes && $requestMD) {
                $this->_adyenLogger->addAdyenResult("Process 3D secure payment");

                //Reset the payment's additional info to the new MD and PaRes
                $order->getPayment()->setAdditionalInformation('md', $requestMD);
                $order->getPayment()->setAdditionalInformation('paRequest', $requestPaRes);
                $order->getPayment()->setAdditionalInformation('paResponse', $requestPaRes);

                try {
                    $result = $this->_authorise3d($order->getPayment());
                    $responseCode = $result['resultCode'];
                } catch (\Exception $e) {
                    $this->_adyenLogger->addAdyenResult("Process 3D secure payment was refused");
                    $responseCode = 'Refused';
                }

                $this->_adyenLogger->addAdyenResult("Process 3D secure payment result is: " . $responseCode);

                // check if authorise3d was successful
                if ($responseCode == 'Authorised') {
                    $this->markOrderAsAuthorized($order, $result);
                    return self::AUTHORIZED;
                } else {
                    /*
                     * Since responseCode!='Authorised' the order could be cancelled immediately,
                     * but redirect payments can have multiple conflicting responses.
                     * The order will be cancelled if an Authorization
                     * Success=False notification is processed instead
                    */
                    $order->addStatusHistoryComment(
                        __(
                            '3D-secure validation was unsuccessful. This order will be cancelled when the related
                                notification has been processed.'
                        )
                    )->save();
                    return self::UNSUCCESSFUL;
                }
            } else {
                $this->_adyenLogger->addAdyenResult("Customer was redirected to bank for 3D-secure validation.");
                $order->addStatusHistoryComment(
                    __(
                        'Customer was redirected to bank for 3D-secure validation. Once the shopper authenticated,
                        the order status will be updated accordingly.
                        <br />Make sure that your notifications are being processed!
                        <br />If the order is stuck on this status, the shopper abandoned the session.
                        The payment can be seen as unsuccessful.
                        <br />The order can be automatically cancelled based on the OFFER_CLOSED notification.
                        Please contact Adyen Support to enable this.'
                    )
                )->save();
                return self::NEEDS_REDIRECT;
            }
        } elseif (!empty($checkoutAPM)) {
            return self::NEEDS_REDIRECT;
        } else {
            return self::ALREADY_SUCCESSFUL;
        }
    }

    /**
     * Called by redirect controller when cc payment has 3D secure
     *
     * @param $payment
     * @return mixed
     * @throws \Exception
     */
    private function _authorise3d($payment)
    {
        try {
            $response = $this->_paymentRequest->authorise3d($payment);
        } catch (\Exception $e) {
            throw $e;
        }
        return $response;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $authorizeResult
     */
    private function markOrderAsAuthorized(\Magento\Sales\Model\Order $order, $authorizeResult): void
    {
        $order->addStatusHistoryComment(__('3D-secure validation was successful'))->save();
        // set back to false so when pressed back button on the success page
        // it will reactivate 3D secure
        $order->getPayment()->setAdditionalInformation('3dActive', '');
        $order->getPayment()->setAdditionalInformation('3dSuccess', true);

        if (!$this->_adyenHelper->isCreditCardVaultEnabled() &&
            !empty($authorizeResult['additionalData']['recurring.recurringDetailReference'])) {
            $this->_adyenHelper->createAdyenBillingAgreement($order, $authorizeResult['additionalData']);
        } elseif (!empty($authorizeResult['additionalData']['recurring.recurringDetailReference'])
        ) {
            try {
                $additionalData = $authorizeResult['additionalData'];
                $token = $additionalData['recurring.recurringDetailReference'];
                $expirationDate = $additionalData['expiryDate'];
                $cardType = $additionalData['paymentMethod'];
                $cardSummary = $additionalData['cardSummary'];
                /** @var PaymentTokenInterface $paymentToken */
                $paymentToken = $this->paymentTokenFactory->create(
                    PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
                );
                $paymentToken->setGatewayToken($token);
                $paymentToken->setExpiresAt($this->getExpirationDate($expirationDate));
                $details = [
                    'type' => $cardType,
                    'maskedCC' => $cardSummary,
                    'expirationDate' => $expirationDate
                ];
                $paymentToken->setTokenDetails(json_encode($details));
                $extensionAttributes = $this->getExtensionAttributes($order->getPayment());
                $extensionAttributes->setVaultPaymentToken($paymentToken);
                $orderPayment = $order->getPayment()->setExtensionAttributes($extensionAttributes);
                $add = $this->serializer->unserialize($orderPayment->getAdditionalData());
                $add['force_save'] = true;
                $orderPayment->setAdditionalData($this->serializer->serialize($add));
                $this->orderPaymentResource->save($orderPayment);
            } catch (\Exception $e) {
                $this->_adyenLogger->error((string)$e->getMessage());
            }
        }

        $this->_orderRepository->save($order);
    }

    /**
     * @param $expirationDate
     * @return string
     */
    private function getExpirationDate($expirationDate)
    {
        $expirationDate = explode('/', $expirationDate);
        //add leading zero to month
        $month = sprintf("%02d", $expirationDate[0]);
        $expDate = new \DateTime(
            $expirationDate[1]
            . '-'
            . $month
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );
        // add one month
        $expDate->add(new \DateInterval('P1M'));
        return $expDate->format('Y-m-d 00:00:00');
    }

    /**
     * Get payment extension attributes
     *
     * @param InfoInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getExtensionAttributes(InfoInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if (null === $extensionAttributes) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }
}
