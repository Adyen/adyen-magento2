<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\Billing\AgreementFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement;
use Adyen\Payment\Observer\AdyenPaymentMethodDataAssignObserver;

class Recurring
{
    const MODE_MAGENTO_VAULT = 'Magento Vault';
    const MODE_ADYEN_TOKENIZATION = 'Adyen Tokenization';

    const CARD_ON_FILE = 'CardOnFile';
    const SUBSCRIPTION = 'Subscription';
    const UNSCHEDULED_CARD_ON_FILE = 'UnscheduledCardOnFile';

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var AgreementFactory */
    private $billingAgreementFactory;

    /** @var Agreement  */
    private $billingAgreementResourceModel;

    /** @var Config */
    private $config;

    /**
     * @var Vault
     */
    private $vaultHelper;

    /**
     * Recurring constructor.
     */
    public function __construct(
        AdyenLogger $adyenLogger,
        AgreementFactory $agreementFactory,
        Agreement $billingAgreementResourceModel,
        Config $config,
        Vault $vaultHelper
    )
    {
        $this->adyenLogger = $adyenLogger;
        $this->billingAgreementFactory = $agreementFactory;
        $this->billingAgreementResourceModel = $billingAgreementResourceModel;
        $this->config = $config;
        $this->vaultHelper = $vaultHelper;
    }

    /**
     * @return string[]
     */
    public static function getRecurringTypes(): array
    {
        return [
            self::CARD_ON_FILE,
            self::SUBSCRIPTION,
            self::UNSCHEDULED_CARD_ON_FILE
        ];
    }

    /**
     * @return string[]
     */
    public static function getRecurringMethods(): array
    {
        return [
            self::MODE_MAGENTO_VAULT,
            self::MODE_ADYEN_TOKENIZATION
        ];
    }

    /**
     * @param $order
     * @param $additionalData
     */
    public function createAdyenBillingAgreement($order, $additionalData)
    {
        if (!empty($additionalData['recurring.recurringDetailReference'])) {
            try {
                // Get or create billing agreement
                /** @var \Adyen\Payment\Model\Billing\Agreement $billingAgreement */
                $billingAgreement = $this->billingAgreementFactory->create();
                $billingAgreement->load($additionalData['recurring.recurringDetailReference'], 'reference_id');

                // check if BA exists
                if (!($billingAgreement && $billingAgreement->getAgreementId() > 0 && $billingAgreement->isValid())) {
                    // create new BA
                    $billingAgreement = $this->billingAgreementFactory->create();
                    $billingAgreement->setStoreId($order->getStoreId());
                    $billingAgreement->importOrderPaymentWithRecurringDetailReference(
                        $order->getPayment(),
                        $additionalData['recurring.recurringDetailReference']
                    );

                    $message = __(
                        'Created billing agreement #%1.',
                        $additionalData['recurring.recurringDetailReference']
                    );
                } else {
                    $billingAgreement->setIsObjectChanged(true);
                    $message = __(
                        'Updated billing agreement #%1.',
                        $additionalData['recurring.recurringDetailReference']
                    );
                }

                // Populate billing agreement data
                $storeOneClick = $order->getPayment()->getAdditionalInformation('store_cc');
                $payment = $order->getPayment();

                if ($payment->getMethod() === PaymentMethods::ADYEN_CC) {
                    $billingAgreement->setCcBillingAgreement($additionalData, $storeOneClick, $order->getStoreId());
                }

                $billingAgreementErrors = $billingAgreement->getErrors();

                if ($billingAgreement->isValid() && empty($billingAgreementErrors)) {
                    if (!$this->billingAgreementResourceModel->getOrderRelation(
                        $billingAgreement->getAgreementId(),
                        $order->getId()
                    )) {
                        // save into billing_agreement_order
                        $billingAgreement->addOrderRelation($order);
                    }
                    // add to order to save agreement
                    $order->addRelatedObject($billingAgreement);
                } else {
                    $message = __('Failed to create billing agreement for this order. Reason(s): ') . join(
                            ', ',
                            $billingAgreementErrors
                        );
                    throw new \Exception($message);
                }
            } catch (\Exception $exception) {
                $message = $exception->getMessage();
                $this->adyenLogger->error("exception: " . $message);
            }

            $comment = $order->addStatusHistoryComment($message, $order->getStatus());

            $order->addRelatedObject($comment);
            $order->save();
        }
    }


    /**
     * Get the recurring type to be assigned to a token based on the admin settings
     *
     * @param null|int|string $storeId
     * @return null|string
     */
    public function getRecurringTypeFromSetting($storeId = null): ?string
    {
        $vaultEnabled = $this->vaultHelper->isCardVaultEnabled($storeId);
        $adyenTokensEnabled = $this->areAdyenTokensEnabled($storeId);

        if ($vaultEnabled) {
            return self::SUBSCRIPTION;
        } elseif ($adyenTokensEnabled) {
            return $this->config->getCardRecurringType($storeId);
        }

        return null;
    }

    /**
     * Check if Adyen tokens are enabled
     *
     * @param null $storeId
     * @return bool
     */
    public function areAdyenTokensEnabled($storeId = null): bool
    {
        return $this->config->getCardRecurringMode($storeId) === self::MODE_ADYEN_TOKENIZATION;
    }
}
