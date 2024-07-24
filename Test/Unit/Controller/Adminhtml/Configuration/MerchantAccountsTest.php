<?php
namespace Adyen\Payment\Test\Unit\Controller\Adminhtml\Configuration;

use Adyen\Payment\Controller\Adminhtml\Configuration\MerchantAccounts;
use Adyen\Payment\Helper\ManagementHelper;
use Adyen\Service\Management\AccountMerchantLevelApi;
use Adyen\Service\Management\MyAPICredentialApi;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;

class MerchantAccountsTest extends AbstractAdyenTestCase
{
    public function testExecute(): void
    {
        $apiKey = 'testApiKey';
        $demoMode = 1;
        $requestMock = $this->createMock(RequestInterface::class);
        $requestMock->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(['apiKey', ''], ['demoMode'])
            ->willReturnOnConsecutiveCalls($apiKey, $demoMode);

        $contextMock = $this->createConfiguredMock(Context::class,
            [
                'getRequest' => $requestMock
            ]
        );

        $accountMerchantLevelApiMock = $this->createMock(AccountMerchantLevelApi::class);
        $myAPICredentialApiMock = $this->createMock(MyAPICredentialApi::class);
        $managementHelperMock = $this->createConfiguredMock(ManagementHelper::class,[
            'getAccountMerchantLevelApi' => $accountMerchantLevelApiMock,
            'getMyAPICredentialApi' => $myAPICredentialApiMock,
            'getMerchantAccountsAndClientKey' => [
                'merchantAccounts' => [],
                'clientKey' => '123',
                'currentMerchantAccount' => 'MerchantAccount1'
            ]
        ]);

        $result = $this->createConfiguredMock(Json::class, []);
        $resultJsonFactoryMock = $this->createConfiguredMock(JsonFactory::class, [
            'create' =>  $result
        ]);

        $result->expects($this->once())->method('setData')->with([
            'merchantAccounts' => [],
            'clientKey' => '123',
            'currentMerchantAccount' => 'MerchantAccount1'
        ]);

        $merchantAccountsController = new MerchantAccounts(
            $contextMock,
            $managementHelperMock,
            $resultJsonFactoryMock
        );

        $merchantAccountsController->execute();
    }
}
