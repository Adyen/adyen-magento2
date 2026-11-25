<?php

namespace Adyen\Payment\Console\Command;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethodsFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnablePaymentMethodsCommand extends Command
{
    public const OPTION_SCOPE = 'scope';
    public const OPTION_SCOPE_CODE = 'scope-code';
    public const OPTION_DISABLE = 'disable';

    public const MERCHANT_ACCOUNT_PATH = 'payment/adyen_abstract/merchant_account';

    public const SCOPES = [
        ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        ScopeInterface::SCOPE_WEBSITES,
        ScopeInterface::SCOPE_STORES
    ];

    /**
     * @var PaymentMethodsFactory
     */
    private PaymentMethodsFactory $paymentMethodsFactory;

    /**
     * @var Config
     */
    private Config $configHelper;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param PaymentMethodsFactory $paymentMethodsFactory
     * @param Config $configHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        PaymentMethodsFactory $paymentMethodsFactory,
        Config $configHelper,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->paymentMethodsFactory = $paymentMethodsFactory;
        $this->configHelper = $configHelper;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('adyen:enablepaymentmethods:run');
        $this->setDescription(
            'Enables the Adyen payment methods. Without --scope, only where a merchant account is configured: ' .
            'globally when it is set in the default scope, otherwise on each website or store view that has its own.'
        );
        $this->addOption(
            self::OPTION_SCOPE,
            null,
            InputOption::VALUE_REQUIRED,
            'Configuration scope to change: default, websites or stores.'
        );
        $this->addOption(
            self::OPTION_SCOPE_CODE,
            null,
            InputOption::VALUE_REQUIRED,
            'Website or store view code, required with --scope=websites or --scope=stores.'
        );
        $this->addOption(
            self::OPTION_DISABLE,
            null,
            InputOption::VALUE_NONE,
            'Disable the payment methods instead of enabling them.'
        );
        parent::configure();
    }

    /**
     * Enables or disables the Adyen payment methods in the requested or detected scopes.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isActive = !$input->getOption(self::OPTION_DISABLE);
        $action = $isActive ? 'enabling' : 'disabling';

        try {
            $scopes = $this->getScopes($input);
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        if (empty($scopes)) {
            $output->writeln(
                'No merchant account is configured in any scope, no payment methods were changed. ' .
                'Use --scope to choose the scope explicitly.'
            );

            return Cli::RETURN_SUCCESS;
        }

        $output->writeln("Starting $action payment methods.");
        /** @var PaymentMethods $paymentMethods */
        $paymentMethods = $this->paymentMethodsFactory->create();

        foreach ($scopes as [$scope, $scopeId, $label]) {
            $toggledPaymentMethods = $paymentMethods->togglePaymentMethodsActivation($isActive, $scope, $scopeId);
            // Keeps the "Payment methods enabled" switch in the admin in line with the methods it controls.
            $this->configHelper->setConfigData(
                $isActive ? '1' : '0',
                Config::XML_PAYMENT_METHODS_ACTIVE,
                Config::XML_ADYEN_ABSTRACT_PREFIX,
                $scope,
                $scopeId
            );

            foreach ($toggledPaymentMethods as $paymentMethod) {
                $output->writeln(($isActive ? 'Enabled' : 'Disabled') . " payment method: $paymentMethod ($label)");
            }
        }

        $output->writeln("Completed $action payment methods.");

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Returns the scopes to change as [scope, scope id, label] entries.
     *
     * @param InputInterface $input
     * @return array
     * @throws LocalizedException
     */
    private function getScopes(InputInterface $input): array
    {
        $scope = $input->getOption(self::OPTION_SCOPE);

        if ($scope !== null) {
            return [$this->resolveScope((string) $scope, (string) $input->getOption(self::OPTION_SCOPE_CODE))];
        }

        if ($this->hasMerchantAccount(ScopeConfigInterface::SCOPE_TYPE_DEFAULT)) {
            return [[ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0, 'default scope']];
        }

        $scopes = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteId = (int) $website->getId();
            if ($this->hasMerchantAccount(ScopeInterface::SCOPE_WEBSITES, $websiteId)) {
                $scopes[] = [ScopeInterface::SCOPE_WEBSITES, $websiteId, 'website ' . $website->getCode()];
                continue;
            }

            foreach ($this->storeManager->getStores() as $store) {
                $storeId = (int) $store->getId();
                if ((int) $store->getWebsiteId() === $websiteId
                    && $this->hasMerchantAccount(ScopeInterface::SCOPE_STORES, $storeId)
                ) {
                    $scopes[] = [ScopeInterface::SCOPE_STORES, $storeId, 'store view ' . $store->getCode()];
                }
            }
        }

        return $scopes;
    }

    /**
     * Resolves a scope given on the command line to a [scope, scope id, label] entry.
     *
     * @param string $scope
     * @param string $scopeCode
     * @return array
     * @throws LocalizedException
     */
    private function resolveScope(string $scope, string $scopeCode): array
    {
        if (!in_array($scope, self::SCOPES, true)) {
            throw new LocalizedException(
                __('Invalid scope "%1", use one of: %2.', $scope, implode(', ', self::SCOPES))
            );
        }

        if ($scope === ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return [$scope, 0, 'default scope'];
        }

        if ($scopeCode === '') {
            throw new LocalizedException(
                __('The --%1 option is required with --scope=%2.', self::OPTION_SCOPE_CODE, $scope)
            );
        }

        try {
            $entity = $scope === ScopeInterface::SCOPE_WEBSITES
                ? $this->storeManager->getWebsite($scopeCode)
                : $this->storeManager->getStore($scopeCode);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('There is no %1 scope with the code "%2".', $scope, $scopeCode));
        }

        $label = ($scope === ScopeInterface::SCOPE_WEBSITES ? 'website ' : 'store view ') . $entity->getCode();

        return [$scope, (int) $entity->getId(), $label];
    }

    /**
     * Checks whether a merchant account is configured in the given scope, including inherited values.
     *
     * @param string $scope
     * @param int|null $scopeId
     * @return bool
     */
    private function hasMerchantAccount(string $scope, ?int $scopeId = null): bool
    {
        return !empty($this->scopeConfig->getValue(self::MERCHANT_ACCOUNT_PATH, $scope, $scopeId));
    }
}
