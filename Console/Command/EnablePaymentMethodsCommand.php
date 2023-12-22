<?php

namespace Adyen\Payment\Console\Command;

use Adyen\Payment\Helper\PaymentMethodsFactory;
use Adyen\Payment\Helper\ConfigFactory;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnablePaymentMethodsCommand extends Command
{
    private PaymentMethodsFactory $paymentMethodsFactory;

    private ConfigFactory $configHelperFactory;

    public function __construct(
        PaymentMethodsFactory $paymentMethodsFactory,
        ConfigFactory $configHelperFactory
    ) {
        $this->paymentMethodsFactory = $paymentMethodsFactory;
        $this->configHelperFactory = $configHelperFactory;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('adyen:enablepaymentmethods:run');
        parent::configure();
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Starting enabling payment methods.');
        $paymentMethods = $this->paymentMethodsFactory->create();
        $availablePaymentMethods = $paymentMethods->getAdyenPaymentMethods();
        $configHelper = $this->configHelperFactory->create();

        foreach ($availablePaymentMethods as $paymentMethod) {
            $value = '1';
            $field = 'active';
            $configHelper->setConfigData($value, $field, $paymentMethod);
            $output->writeln("Enabled payment method: {$paymentMethod}");
        }

        $output->writeln('Completed enabling payment methods.');
        return Cli::RETURN_SUCCESS;
    }
}
