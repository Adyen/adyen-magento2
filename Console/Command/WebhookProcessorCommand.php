<?php

namespace Adyen\Payment\Console\Command;

use Adyen\Payment\Cron\WebhookProcessorFactory;
use Exception;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebhookProcessorCommand extends Command
{
    /**
     * @var WebhookProcessorFactory
     */
    private $webhookProcessorFactory;

    public function __construct(WebhookProcessorFactory $webhookProcessorFactory)
    {
        $this->webhookProcessorFactory = $webhookProcessorFactory;
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
            $this->webhookProcessorFactory->create()->execute();
        } catch (Exception $e) {
            return Cli::RETURN_FAILURE;
        }
        $output->writeln('Completed webhook processor execution.');
        return Cli::RETURN_SUCCESS;
    }
}
