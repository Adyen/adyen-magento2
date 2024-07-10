<?php

namespace Adyen\Payment\Console\Command;

use Adyen\Payment\Cron\WebhookProcessor;
use Exception;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebhookProcessorCommand extends Command
{
    /**
     * @var WebhookProcessor
     */
    private $webhookProcessor;

    /**
     * @var State
     */
    private $appState;

    public function __construct(WebhookProcessor $webhookProcessor)
    {
        $this->webhookProcessor = $webhookProcessor;
        $this->appState = $appState;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('adyen:webhook:run');
        parent::configure();
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Starting webhook processor.');
        try {
            $this->appState->setAreaCode(Area::AREA_GLOBAL);
            $this->webhookProcessor->execute();
        } catch (Exception $e) {
            return Cli::RETURN_FAILURE;
        }
        $output->writeln('Completed webhook processor execution.');
        return Cli::RETURN_SUCCESS;
    }
}
