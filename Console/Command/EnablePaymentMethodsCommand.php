<?php

namespace Adyen\Payment\Console\Command;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethodsFactory;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnablePaymentMethodsCommand extends Command
{
    private PaymentMethodsFactory $paymentMethodsFactory;

    public function __construct(
        PaymentMethodsFactory $paymentMethodsFactory,
    )
    {
        $this->paymentMethodsFactory = $paymentMethodsFactory;
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
        /** @var PaymentMethods $paymentMethods */
        $activatedPaymentMethods = $paymentMethods->togglePaymentMethodsActivation(true);

        foreach ($activatedPaymentMethods as $paymentMethod) {
            $output->writeln("Enabled payment method: {$paymentMethod}");
        }

        $output->writeln('Completed enabling payment methods.');

        return Cli::RETURN_SUCCESS;
    }
}
