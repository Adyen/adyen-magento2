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

use Adyen\Payment\Helper\Recurring;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use DateInterval;
use DateTime;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
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
    private WriterInterface $configWriter;
    private ReinitableConfigInterface $reinitableConfig;
    private PaymentTokenManagementInterface $tokenManagement;
    private PaymentTokenFactoryInterface $tokenFactory;
    private PaymentTokenRepositoryInterface $tokenRepository;
    private EncryptorInterface $encryptor;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig,
        PaymentTokenManagementInterface $tokenManagement,
        PaymentTokenFactoryInterface $tokenFactory,
        PaymentTokenRepositoryInterface $tokenRepository,
        EncryptorInterface $encryptor
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
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

        $today = new DateTime();
        $today->add(new DateInterval('P10Y'));

        foreach ($adyenBillingAgreements as $adyenBillingAgreement) {
            $this->saveVaultToken(
                intval($adyenBillingAgreement['customer_id']),
                $adyenBillingAgreement['reference_id'],
                $today,
                $adyenBillingAgreement['created_at'],
                $adyenBillingAgreement['agreement_data']
            );
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
        DateTime $expirationDate,
        string $createdAt,
        string $tokenDetails
    ): PaymentTokenInterface
    {
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

    private function transformTokenDetails(string $baAgreementData): string
    {
        $baJson = json_decode($baAgreementData, true);
        $vaultDetails = [
            'type' => $baJson['variant'],
            'maskedCC' => $baJson['card']['number'],
            'expirationDate' => $baJson['expiryMonth'] . '/' . $baJson['expiryYear'],
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
