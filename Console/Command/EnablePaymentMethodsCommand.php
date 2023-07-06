<?php

namespace Adyen\Payment\Console\Command;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnablePaymentMethodsCommand extends Command
{
    private PaymentMethods $paymentMethods;

    private Config $configHelper;

    public function __construct(
        PaymentMethods $paymentMethods,
        Config $configHelper
    ) {
        $this->paymentMethods = $paymentMethods;
        $this->configHelper = $configHelper;
        parent::__construct();
    }

    protected function configure()
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
        $availablePaymentMethods = $this->paymentMethods->getAdyenPaymentMethods();

        foreach ($availablePaymentMethods as $paymentMethod) {
            $value = '1';
            $field = 'active';
            $this->configHelper->setConfigData($value, $field, $paymentMethod);
            $output->writeln("Enabled payment method: {$paymentMethod}");
        }

        $output->writeln('Completed enabling payment methods.');
    }
}
