<?php

namespace Adyen\Payment\Test\Unit\Console\Command;

use Adyen\Payment\Console\Command\EnablePaymentMethodsCommand;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ConfigFactory;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethodsFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnablePaymentMethodsCommandTest extends AbstractAdyenTestCase
{
    /**
     * Test the execute method with successful enabling of payment methods.
     */
    public function testExecuteWithSuccess()
    {
        // Mock dependencies
        $availablePaymentMethods = ['method1', 'method2'];
        $paymentMethodMock = $this->createConfiguredMock(PaymentMethods::class, [
            'getAdyenPaymentMethods' => $availablePaymentMethods
        ]);
        $paymentMethodsFactoryMock = $this->createGeneratedMock(
            PaymentMethodsFactory::class,
            ['create']
        );
        $paymentMethodsFactoryMock->method('create')->willReturn($paymentMethodMock);

        $configHelperMock = $this->createMock(Config::class);

        $configHelperFactoryMock = $this->createGeneratedMock(
            ConfigFactory::class,
            ['create']
        );
        $configHelperFactoryMock->method('create')->willReturn($configHelperMock);

        $inputMock = $this->createMock(InputInterface::class);
        $outputMock = $this->createMock(OutputInterface::class);

        // Create the command instance
        $command = new EnablePaymentMethodsCommand($paymentMethodsFactoryMock, $configHelperFactoryMock);

        // Execute the command
        $result = $command->run($inputMock, $outputMock);

        // Assert the expected success return code
        $this->assertEquals(Cli::RETURN_SUCCESS, $result);
    }
}
