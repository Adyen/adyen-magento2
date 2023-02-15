<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Setup\Patch\Data;

use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use DateInterval;
use DateTime;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class VaultMigration implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private PaymentTokenManagementInterface $tokenManagement;
    private PaymentTokenFactoryInterface $tokenFactory;
    private PaymentTokenRepositoryInterface $tokenRepository;
    private EncryptorInterface $encryptor;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        PaymentTokenManagementInterface $tokenManagement,
        PaymentTokenFactoryInterface $tokenFactory,
        PaymentTokenRepositoryInterface $tokenRepository,
        EncryptorInterface $encryptor
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->tokenManagement = $tokenManagement;
        $this->tokenFactory = $tokenFactory;
        $this->tokenRepository = $tokenRepository;
        $this->encryptor = $encryptor;
    }

    /**
     * Do Upgrade
     *
     * @return void
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->migrateBillingAgreementsToVault($this->moduleDataSetup);
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function migrateBillingAgreementsToVault(ModuleDataSetupInterface $setup)
    {
        $paypalTable = $setup->getTable('paypal_billing_agreement');
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from($paypalTable)
            ->where(
                'method_code = ?',
                'adyen_oneclick'
            )
            ->where(
                'status = ?',
                'active'
            );

        $adyenBillingAgreements = $connection->fetchAll($select);

        foreach ($adyenBillingAgreements as $adyenBillingAgreement) {
            $paymentToken = $this->tokenManagement->getByGatewayToken(
                $adyenBillingAgreement['reference_id'],
                AdyenCcConfigProvider::CODE,
                $adyenBillingAgreement['customer_id']
            );

            if (is_null($paymentToken)) {
                $this->saveVaultToken(
                    intval($adyenBillingAgreement['customer_id']),
                    $adyenBillingAgreement['reference_id'],
                    $adyenBillingAgreement['created_at'],
                    $adyenBillingAgreement['agreement_data']
                );
            }
        }
    }

    /**
     * TODO: Check if gateway token already exists
     *
     *
     *
     * @param int $customerId
     * @param string $gatewayToken
     * @param DateTime $expirationDate
     * @param string $createdAt
     * @param string $tokenDetails
     * @return PaymentTokenInterface
     */
    private function saveVaultToken(
        int $customerId,
        string $gatewayToken,
        string $createdAt,
        string $tokenDetails
    ): PaymentTokenInterface {

        $expirationDate = new DateTime();
        $expirationDate->add(new DateInterval('P10Y'));

        $paymentToken = $this->tokenFactory->create(
            PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
        );
        $paymentToken->setCustomerId($customerId);
        $paymentToken->setPaymentMethodCode(AdyenCcConfigProvider::CODE);
        $paymentToken->setGatewayToken($gatewayToken);
        $paymentToken->setIsActive(true);
        $paymentToken->setIsVisible(true);
        $paymentToken->setCreatedAt($createdAt);
        $paymentToken->setTokenDetails($this->transformTokenDetails($tokenDetails));
        $paymentToken->setExpiresAt($expirationDate);
        $paymentToken->setPublicHash($this->generatePublicHash($customerId, $tokenDetails));

        $this->tokenRepository->save($paymentToken);

        return $paymentToken;
    }

    private function generatePublicHash(int $customerId, string $tokenDetails): string
    {
        $hashKey = $customerId;

        $hashKey .= AdyenCcConfigProvider::CODE
            . PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
            . $tokenDetails;

        return $this->encryptor->getHash($hashKey);
    }

    /**
     * TODO: Handle case where there are more contract types than 1, test v7 tokens
     *
     * @param string $baAgreementData
     * @return string
     */
    private function transformTokenDetails(string $baAgreementData): string
    {
        $baJson = json_decode($baAgreementData, true);
        $vaultDetails = [
            'type' => $baJson['variant'],
            'maskedCC' => $baJson['card']['number'],
            'expirationDate' => $baJson['card']['expiryMonth'] . '/' . $baJson['card']['expiryYear'],
            'tokenType' => $baJson['contractTypes'][0]
        ];

        return json_encode($vaultDetails);
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }
}
