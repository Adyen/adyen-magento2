<?php

namespace Adyen\Payment\Console\Command;

use Adyen\Payment\Cron\WebhookProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebhookProcessorCommand extends Command
{
    /**
     * @var WebhookProcessor
     */
    private $webhookProcessor;

    public function __construct(WebhookProcessor $webhookProcessor)
    {
        $this->webhookProcessor = $webhookProcessor;
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
        $this->webhookProcessor->execute();
        $output->writeln('Completed webhook processor execution.');
    }
}
