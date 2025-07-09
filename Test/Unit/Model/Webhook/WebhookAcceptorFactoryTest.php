<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Webhook;

use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;
use Adyen\Payment\Model\Webhook\WebhookAcceptorType;
use Adyen\Payment\Model\Webhook\StandardWebhookAcceptor;
use Adyen\Payment\Model\Webhook\TokenWebhookAcceptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookAcceptorFactory::class)]
class WebhookAcceptorFactoryTest extends TestCase
{
    private WebhookAcceptorFactory $factory;

    private MockObject $standardAcceptor;
    private MockObject $tokenAcceptor;

    protected function setUp(): void
    {
        $this->standardAcceptor = $this->createMock(StandardWebhookAcceptor::class);
        $this->tokenAcceptor = $this->createMock(TokenWebhookAcceptor::class);

        $this->factory = new WebhookAcceptorFactory(
            $this->standardAcceptor,
            $this->tokenAcceptor
        );
    }

    public function testReturnsStandardWebhookAcceptor(): void
    {
        $result = $this->factory->getAcceptor(WebhookAcceptorType::STANDARD);
        $this->assertSame($this->standardAcceptor, $result);
    }

    public function testReturnsTokenWebhookAcceptor(): void
    {
        $result = $this->factory->getAcceptor(WebhookAcceptorType::TOKEN);
        $this->assertSame($this->tokenAcceptor, $result);
    }
}
