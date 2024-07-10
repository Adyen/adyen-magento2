<?php

namespace Adyen\Payment\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetAreaCodeCommand extends Command
{
    private PaymentMethodsFactory $paymentMethodsFactory;
    private State $appState;

    public function __construct(
        State $appState
    )
    {
        $this->appState = $appState;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('adyen:setareacode:run');
        parent::configure();
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->setAreaCode(Area::AREA_GLOBAL);
            $output->writeln("Area code set to 'global'");
            return Cli::RETURN_SUCCESS;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $output->writeln("Area code was already set: {$e->getMessage()}");
            return Cli::RETURN_FAILURE;
        }
    }
}
