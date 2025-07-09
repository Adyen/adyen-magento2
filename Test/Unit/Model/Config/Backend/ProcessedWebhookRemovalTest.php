<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Model\Config\Backend;

use Adyen\Payment\Model\Config\Backend\ProcessedWebhookRemoval;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\MockObject;

class ProcessedWebhookRemovalTest extends AbstractAdyenTestCase
{
    protected ?ProcessedWebhookRemoval $processedWebhookRemoval;
    protected Context|MockObject $contextMockMock;
    protected Registry|MockObject $registryMock;
    protected ScopeConfigInterface|MockObject $configMock;
    protected TypeListInterface|MockObject $cacheTypeListMock;
    protected ManagerInterface|MockObject $messageManagerMock;
    protected AbstractResource|MockObject $resourceMock;
    protected AbstractDb|MockObject $resourceCollection;
    protected array $data = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextMockMock = $this->createMock(Context::class);
        $this->registryMock = $this->createMock(Registry::class);
        $this->configMock = $this->createMock(ScopeConfigInterface::class);
        $this->cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->resourceMock = $this->createMock(AbstractResource::class);
        $this->resourceCollection = $this->createMock(AbstractDb::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->processedWebhookRemoval = null;
    }

    /**
     * `data` needs to be set during the test execution.
     * Therefore, SUT generation has been extracted from `setUp()` method.
     *
     * @param array $data
     * @return void
     */
    private function generateSut(array $data): void
    {
        $this->processedWebhookRemoval = new ProcessedWebhookRemoval(
            $this->contextMockMock,
            $this->registryMock,
            $this->configMock,
            $this->cacheTypeListMock,
            $this->messageManagerMock,
            $this->resourceMock,
            $this->resourceCollection,
            $data
        );
    }

    public function testAfterSave()
    {
        // Set old value as `0` to mimic the disabled feature
        $this->configMock->method('getValue')->willReturn('0');
        $expectedMessage =
            'You enabled the automatic removal of Adyen\'s processed webhooks. Processed webhooks older than 45 days will be removed.';

        $this->messageManagerMock->expects($this->once())
            ->method('addWarningMessage')
            ->with($expectedMessage);

        $data = [
            'fieldset_data' => [
                'processed_webhook_removal_time' => '45'
            ],
            'value' => '1'
        ];

        $this->generateSut($data);

        $result = $this->processedWebhookRemoval->afterSave();
        $this->assertInstanceOf(ProcessedWebhookRemoval::class, $result);
    }
}
