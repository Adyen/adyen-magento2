<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Api\Data\CreditmemoInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo as CreditMemoResourceModel;
use Adyen\Payment\Model\CreditmemoFactory;
use Adyen\Payment\Model\Creditmemo as AdyenCreditmemoModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo as MagentoCreditMemoModel;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order\CreditmemoFactory as MagentoCreditMemoFactory;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo as MagentoCreditMemoResourceModel;

/**
 * Helper class for anything related to the creditmemo entity
 *
 * @package Adyen\Payment\Helper
 */
class Creditmemo extends AbstractHelper
{
    /**
     * @var Data
     */
    protected $adyenDataHelper;

    /**
     * @var OrderPaymentResourceModel
     */
    protected $orderPaymentResourceModel;

    /**
     * @var CreditmemoResourceModel
     */
    private $adyenCreditmemoResourceModel;

    /**
     * @var CreditmemoFactory
     */
    private $adyenCreditmemoFactory;

    /**
     * Creditmemo constructor.
     * @param Context $context
     * @param Data $adyenDataHelper
     * @param CreditmemoFactory $adyenCreditmemoFactory
     * @param CreditmemoResourceModel $adyenCreditmemoResourceModel
     * @param OrderPaymentResourceModel $orderPaymentResourceModel
     */
    public function __construct(
        Context $context,
        Data $adyenDataHelper,
        CreditmemoFactory $adyenCreditmemoFactory,
        CreditmemoResourceModel $adyenCreditmemoResourceModel,
        OrderPaymentResourceModel $orderPaymentResourceModel
    )
    {
        parent::__construct($context);
        $this->adyenDataHelper = $adyenDataHelper;
        $this->adyenCreditmemoFactory = $adyenCreditmemoFactory;
        $this->adyenCreditmemoResourceModel = $adyenCreditmemoResourceModel;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
    }

    /**
     * Link all the adyen_creditmemos related to the adyen_order_payment with the passed creditMemoModel
     *
     * @param Order\Payment $payment
     * @param string $pspReference
     * @param string $originalReference
     * @param float $refundAmount
     * @return AdyenCreditmemoModel
     * @throws AlreadyExistsException
     */
    public function createAdyenCreditMemo(
        Order\Payment $payment,
        string $pspReference,
        string $originalReference,
        float $refundAmount
    ): AdyenCreditmemoModel {
        // get adyen_order_payment record
        /** @var OrderPaymentInterface $adyenOrderPayment */
        $adyenOrderPayment = $this->orderPaymentResourceModel->getOrderPaymentDetails(
            $originalReference,
            $payment->getEntityId()
        );

        // create adyen_credit_memo record
        /** @var AdyenCreditmemoModel $adyenCreditmemo */
        $adyenCreditmemo = $this->adyenCreditmemoFactory->create();
        $adyenCreditmemo->setPspreference($pspReference);
        $adyenCreditmemo->setOriginalReference($originalReference);
        $adyenCreditmemo->setAdyenPaymentOrderId(
            $adyenOrderPayment[OrderPaymentInterface::ENTITY_ID]
        );
        $adyenCreditmemo->setAmount($refundAmount);
        $adyenCreditmemo->setStatus(AdyenCreditmemoModel::WAITING_FOR_WEBHOOK_STATUS);

        $this->adyenCreditmemoResourceModel->save($adyenCreditmemo);

        return $adyenCreditmemo;
    }

    /**
     * Link all the adyen_creditmemos related to the adyen_order_payment with the given magento entity of the creditmemo
     * @throws AlreadyExistsException
     */
    public function linkAndUpdateAdyenCreditmemos(
        Payment $adyenOrderPayment,
        MagentoCreditmemoModel $magentoCreditmemo
    ): void {
        $adyenCreditmemoLoader = $this->adyenCreditmemoFactory->create();

        $adyenCreditmemos = $this->adyenCreditmemoResourceModel->getAdyenCreditmemosByAdyenPaymentid(
            $adyenOrderPayment->getEntityId()
        );

        if (isset($adyenCreditmemos)) {
            foreach ($adyenCreditmemos as $adyenCreditmemo) {
                /** @var AdyenCreditmemoModel $currAdyenCreditmemo */
                $currAdyenCreditmemo = $adyenCreditmemoLoader->load(
                    $adyenCreditmemo[CreditmemoInterface::ENTITY_ID],
                    CreditmemoInterface::ENTITY_ID
                );

                if ($currAdyenCreditmemo->getCreditmemoId() !== null) {
                    continue;
                }

                if ($currAdyenCreditmemo->getAmount() == $magentoCreditmemo->getGrandTotal()) {
                    $currAdyenCreditmemo->setCreditmemoId($magentoCreditmemo->getEntityId());
                    $this->adyenCreditmemoResourceModel->save($currAdyenCreditmemo);
                    break;
                }
            }
        }
    }

    public function updateAdyenCreditmemosStatus(AdyenCreditmemoModel $adyenCreditmemo, string $status)
    {
        $adyenCreditmemo->setStatus($status);
        $this->adyenCreditmemoResourceModel->save($adyenCreditmemo);
    }

    public function getAdyenCreditmemoByPspreference(string $pspreference): ?AdyenCreditmemoModel {
        $results = $this->adyenCreditmemoResourceModel->getAdyenCreditmemoByPspreference($pspreference);

        if (is_null($results)) {
            return null;
        }

        return $this->adyenCreditmemoFactory->create()->load($results['entity_id']);
    }
}
