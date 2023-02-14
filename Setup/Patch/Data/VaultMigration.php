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
        $today->add(new DateInterval('P1Y'));

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
     * TODO: Transform token details, add functionality to create public hash, update sql to only get active BA
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
        $paymentToken->setTokenDetails($tokenDetails);
        $paymentToken->setExpiresAt($expirationDate);
        $paymentToken->setPublicHash($this->generatePublicHash($customerId, $tokenDetails));

        $this->tokenRepository->save($paymentToken);

        return $paymentToken;
    }

    /**
     * Generate vault payment public hash
     *
     * @param PaymentTokenInterface $paymentToken
     * @return string
     */
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
        return '';
    }

    /**
     * Update a config which has a specific path and a specific value
     *
     * @param ModuleDataSetupInterface $setup
     * @param string $path
     * @param string $valueToUpdate
     * @param string $updatedValue
     */
    private function updateConfigValue(
        ModuleDataSetupInterface $setup,
        string $path,
        string $valueToUpdate,
        string $updatedValue
    ): void {
        $config = $this->findConfig($setup, $path, $valueToUpdate);
        if (isset($config)) {
            $this->configWriter->save(
                $path,
                $updatedValue,
                $config['scope'],
                $config['scope_id']
            );
        }
        // re-initialize otherwise it will cause errors
        $this->reinitableConfig->reinit();
    }

    /**
     * Return the config based on the passed path and value. If value is null, return the first item in array
     *
     * @param ModuleDataSetupInterface $setup
     * @param string $path
     * @param string|null $value
     * @return array|null
     */
    private function findConfig(ModuleDataSetupInterface $setup, string $path, ?string $value): ?array
    {
        $config = null;
        $configDataTable = $setup->getTable('core_config_data');
        $connection = $setup->getConnection();

        $select = $connection->select()
            ->from($configDataTable)
            ->where(
                'path = ?',
                $path
            );

        $matchingConfigs = $connection->fetchAll($select);

        if (!empty($matchingConfigs) && is_null($value)) {
            $config = reset($matchingConfigs);
        } else {
            foreach ($matchingConfigs as $matchingConfig) {
                if ($matchingConfig['value'] === $value) {
                    $config = $matchingConfig;
                }
            }
        }

        return $config;
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
