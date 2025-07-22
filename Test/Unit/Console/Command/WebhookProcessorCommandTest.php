<?php

namespace Adyen\Payment\Test\Unit\Console\Command;

use Adyen\Payment\Console\Command\WebhookProcessorCommand;
use Adyen\Payment\Cron\WebhookProcessor;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebhookProcessorCommandTest extends AbstractAdyenTestCase
{
    /**
     * Test the execute method with successful webhook processing.
     */
    public function testExecuteWithSuccess()
    {
        // Mock dependencies
        $webhookProcessorMock = $this->createMock(WebhookProcessor::class);
        $inputMock = $this->createMock(InputInterface::class);
        $outputMock = $this->createMock(OutputInterface::class);

        // Expect the webhook processor to be executed
        $webhookProcessorMock->expects($this->once())
            ->method('execute');

        // Create the command instance
        $command = new WebhookProcessorCommand($webhookProcessorMock);

        // Execute the command
        $result = $command->run($inputMock, $outputMock);

        // Assert the expected success return code
        $this->assertEquals(Cli::RETURN_SUCCESS, $result);
    }

    /**
     * Test the execute method with an exception thrown during webhook processing.
     */
    public function testExecuteWithException()
    {
        // Mock dependencies
        $webhookProcessorMock = $this->createMock(WebhookProcessor::class);
        $inputMock = $this->createMock(InputInterface::class);
        $outputMock = $this->createMock(OutputInterface::class);

        // Expect the webhook processor to throw an exception
        $webhookProcessorMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Error processing webhook'));

        // Expect the output to have the correct start message
        $outputMock->expects($this->once())
            ->method('writeln')
            ->with('Starting webhook processor.');

        // Create the command instance
        $command = new WebhookProcessorCommand($webhookProcessorMock);

        // Execute the command
        $result = $command->run($inputMock, $outputMock);

        // Assert the expected failure return code
        $this->assertEquals(Cli::RETURN_FAILURE, $result);
    }
}
