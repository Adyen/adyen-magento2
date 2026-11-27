<?php

namespace Adyen\Payment\Test\Unit\Console\Command;

use Adyen\Payment\Console\Command\EnablePaymentMethodsCommand;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethodsFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;

class EnablePaymentMethodsCommandTest extends AbstractAdyenTestCase
{
    /**
     * @var PaymentMethods|MockObject
     */
    private PaymentMethods|MockObject $paymentMethodsMock;

    /**
     * @var Config|MockObject
     */
    private Config|MockObject $configHelperMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private ScopeConfigInterface|MockObject $scopeConfigMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private StoreManagerInterface|MockObject $storeManagerMock;

    /**
     * @var CommandTester
     */
    private CommandTester $commandTester;

    /**
     * Merchant accounts saved in the configuration, keyed by "scope:scopeId".
     *
     * @var array
     */
    private array $merchantAccounts = [];

    protected function setUp(): void
    {
        $this->paymentMethodsMock = $this->createMock(PaymentMethods::class);
        $paymentMethodsFactoryMock = $this->createGeneratedMock(PaymentMethodsFactory::class, ['create']);
        $paymentMethodsFactoryMock->method('create')->willReturn($this->paymentMethodsMock);

        $this->configHelperMock = $this->createMock(Config::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfigMock->method('getValue')->willReturnCallback(
            fn (string $path, string $scope, $scopeId = null) => $this->merchantAccounts[$scope . ':' . (int) $scopeId]
                ?? null
        );

        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeManagerMock->method('getWebsites')->willReturn([
            $this->createWebsite(1, 'base'),
            $this->createWebsite(2, 'second'),
            $this->createWebsite(3, 'third')
        ]);
        $this->storeManagerMock->method('getStores')->willReturn([
            $this->createStore(1, 'default', 1),
            $this->createStore(2, 'second_store', 2),
            $this->createStore(3, 'third_store', 3)
        ]);

        $command = new EnablePaymentMethodsCommand(
            $paymentMethodsFactoryMock,
            $this->configHelperMock,
            $this->scopeConfigMock,
            $this->storeManagerMock
        );
        $this->commandTester = new CommandTester($command);
    }

    public function testEnablesGloballyWhenMerchantAccountIsSetInDefaultScope(): void
    {
        $this->merchantAccounts['default:0'] = 'MerchantAccount';

        $this->paymentMethodsMock->expects($this->once())
            ->method('togglePaymentMethodsActivation')
            ->with(true, 'default', 0)
            ->willReturn(['adyen_cc']);
        $this->configHelperMock->expects($this->once())
            ->method('setConfigData')
            ->with('1', Config::XML_PAYMENT_METHODS_ACTIVE, Config::XML_ADYEN_ABSTRACT_PREFIX, 'default', 0);

        $this->assertEquals(Cli::RETURN_SUCCESS, $this->commandTester->execute([]));
        $this->assertStringContainsString(
            'Enabled payment method: adyen_cc (default scope)',
            $this->commandTester->getDisplay()
        );
    }

    public function testEnablesOnlyWebsitesAndStoreViewsWithTheirOwnMerchantAccount(): void
    {
        $this->merchantAccounts['websites:2'] = 'SecondWebsiteAccount';
        $this->merchantAccounts['stores:3'] = 'ThirdStoreAccount';

        $toggledScopes = [];
        $this->paymentMethodsMock->method('togglePaymentMethodsActivation')->willReturnCallback(
            function (bool $isActive, string $scope, int $scopeId) use (&$toggledScopes) {
                $toggledScopes[] = [$isActive, $scope, $scopeId];

                return ['adyen_cc'];
            }
        );
        $this->configHelperMock->expects($this->exactly(2))->method('setConfigData');

        $this->assertEquals(Cli::RETURN_SUCCESS, $this->commandTester->execute([]));
        $this->assertSame([[true, 'websites', 2], [true, 'stores', 3]], $toggledScopes);
    }

    public function testChangesNothingWhenNoMerchantAccountIsConfigured(): void
    {
        $this->paymentMethodsMock->expects($this->never())->method('togglePaymentMethodsActivation');
        $this->configHelperMock->expects($this->never())->method('setConfigData');

        $this->assertEquals(Cli::RETURN_SUCCESS, $this->commandTester->execute([]));
        $this->assertStringContainsString('No merchant account is configured', $this->commandTester->getDisplay());
    }

    public function testEnablesTheGivenWebsite(): void
    {
        $this->storeManagerMock->method('getWebsite')->with('second')->willReturn($this->createWebsite(2, 'second'));

        $this->paymentMethodsMock->expects($this->once())
            ->method('togglePaymentMethodsActivation')
            ->with(true, 'websites', 2)
            ->willReturn(['adyen_cc']);

        $result = $this->commandTester->execute(['--scope' => 'websites', '--scope-code' => 'second']);

        $this->assertEquals(Cli::RETURN_SUCCESS, $result);
    }

    public function testDisablesTheGivenStoreView(): void
    {
        $this->storeManagerMock->method('getStore')->with('third_store')
            ->willReturn($this->createStore(3, 'third_store', 3));

        $this->paymentMethodsMock->expects($this->once())
            ->method('togglePaymentMethodsActivation')
            ->with(false, 'stores', 3)
            ->willReturn(['adyen_cc']);
        $this->configHelperMock->expects($this->once())
            ->method('setConfigData')
            ->with('0', Config::XML_PAYMENT_METHODS_ACTIVE, Config::XML_ADYEN_ABSTRACT_PREFIX, 'stores', 3);

        $result = $this->commandTester->execute(
            ['--scope' => 'stores', '--scope-code' => 'third_store', '--disable' => true]
        );

        $this->assertEquals(Cli::RETURN_SUCCESS, $result);
        $this->assertStringContainsString(
            'Disabled payment method: adyen_cc (store view third_store)',
            $this->commandTester->getDisplay()
        );
    }

    public function testFailsForUnknownScopeCode(): void
    {
        $this->storeManagerMock->method('getWebsite')->willThrowException(new NoSuchEntityException());
        $this->paymentMethodsMock->expects($this->never())->method('togglePaymentMethodsActivation');

        $result = $this->commandTester->execute(['--scope' => 'websites', '--scope-code' => 'missing']);

        $this->assertEquals(Cli::RETURN_FAILURE, $result);
        $this->assertStringContainsString(
            'There is no websites scope with the code "missing"',
            $this->commandTester->getDisplay()
        );
    }

    public function testFailsWithoutScopeCode(): void
    {
        $this->paymentMethodsMock->expects($this->never())->method('togglePaymentMethodsActivation');

        $this->assertEquals(Cli::RETURN_FAILURE, $this->commandTester->execute(['--scope' => 'websites']));
    }

    public function testFailsForInvalidScope(): void
    {
        $this->paymentMethodsMock->expects($this->never())->method('togglePaymentMethodsActivation');

        $this->assertEquals(Cli::RETURN_FAILURE, $this->commandTester->execute(['--scope' => 'website']));
    }

    private function createWebsite(int $id, string $code): WebsiteInterface
    {
        return $this->createConfiguredMock(WebsiteInterface::class, ['getId' => $id, 'getCode' => $code]);
    }

    private function createStore(int $id, string $code, int $websiteId): StoreInterface
    {
        return $this->createConfiguredMock(
            StoreInterface::class,
            ['getId' => $id, 'getCode' => $code, 'getWebsiteId' => $websiteId]
        );
    }
}
