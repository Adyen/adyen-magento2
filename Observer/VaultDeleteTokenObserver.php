<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Observer;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Service\Recurring;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class VaultDeleteTokenObserver implements ObserverInterface
{
    /** @var PaymentTokenManagementInterface */
    private $paymentTokenManagement;

    /** @var Session */
    private $customerSession;

    /** @var Data */
    private $dataHelper;

    /** @var Requests */
    private $requestsHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    public function __construct(
        PaymentTokenManagementInterface $paymentTokenManagement,
        Session $customerSession,
        Data $dataHelper,
        Requests $requestsHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->customerSession = $customerSession;
        $this->dataHelper = $dataHelper;
        $this->requestsHelper = $requestsHelper;
        $this->adyenLogger = $adyenLogger;
    }

    public function execute(Observer $observer)
    {
        $customerId = $this->customerSession->getCustomerId();
        $paymentToken = $this->getPaymentToken($observer->getData('request'), $customerId);

        $request = [
            Requests::MERCHANT_ACCOUNT => $this->dataHelper->getAdyenMerchantAccount(
                $paymentToken->getPaymentMethodCode()
            ),
            Requests::SHOPPER_REFERENCE => $this->requestsHelper->getShopperReference($customerId, null),
            Requests::RECURRING_DETAIL_REFERENCE => $paymentToken->getGatewayToken()
        ];

        try {
            $client = $this->dataHelper->initializeAdyenClient();
            $recurringService = new Recurring($client);
            $recurringService->disable($request);
        } catch (AdyenException $e) {
            $this->adyenLogger->error(sprintf(
                'Error while attempting to disable token with id %s: %s',
                $paymentToken->getEntityId(),
                $e->getMessage())
            );
        } catch (NoSuchEntityException $e) {
            $this->adyenLogger->error(sprintf(
                    'No such entity while attempting to disable token with id %s: %s',
                    $paymentToken->getEntityId(),
                    $e->getMessage()
            ));
        }
    }

    /**
     * @param Http $request
     * @param string $customerId
     * @return PaymentTokenInterface|null
     */
    private function getPaymentToken(Http $request, string $customerId): ?PaymentTokenInterface
    {
        $publicHash = $request->getPostValue(PaymentTokenInterface::PUBLIC_HASH);

        if ($publicHash === null) {
            return null;
        }

        return $this->paymentTokenManagement->getByPublicHash(
            $publicHash,
            $customerId
        );
    }
}
