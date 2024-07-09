<?php

namespace Adyen\Payment\Console\Command;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethodsFactory;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnablePaymentMethodsCommand extends Command
{
    private PaymentMethodsFactory $paymentMethodsFactory;
    private State $appState;

    public function __construct(
        PaymentMethodsFactory $paymentMethodsFactory,
        State $appState
    ) {
        parent::__construct();

        $this->paymentMethodsFactory = $paymentMethodsFactory;
        $this->appState = $appState;
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
        $this->state->setAreaCode(Area::AREA_GLOBAL);

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
