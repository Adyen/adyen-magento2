<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Webhook;

use Adyen\Payment\API\Webhook\WebhookAcceptorInterface;
use Adyen\Payment\Model\Webhook\StandardWebhookAcceptor;
use Adyen\Payment\Model\Webhook\TokenWebhookAcceptor;
use Adyen\Payment\Model\Webhook\WebhookAcceptorFactory;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(WebhookAcceptorFactory::class)]
class WebhookAcceptorFactoryTest extends AbstractAdyenTestCase
{
    private MockObject $standardAcceptor;
    private MockObject $tokenAcceptor;
    private WebhookAcceptorFactory $factory;

    protected function setUp(): void
    {
        $this->standardAcceptor = $this->createMock(StandardWebhookAcceptor::class);
        $this->tokenAcceptor = $this->createMock(TokenWebhookAcceptor::class);

        $this->factory = new WebhookAcceptorFactory(
            $this->standardAcceptor,
            $this->tokenAcceptor
        );
    }

    public function testGetStandardAcceptor(): void
    {
        $result = $this->factory->getAcceptor(WebhookAcceptorInterface::TYPE_STANDARD);
        self::assertSame($this->standardAcceptor, $result);
    }

    public function testGetTokenAcceptor(): void
    {
        $result = $this->factory->getAcceptor(WebhookAcceptorInterface::TYPE_TOKEN);
        self::assertSame($this->tokenAcceptor, $result);
    }

    public function testThrowsExceptionForInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported webhook type [invalid-type]');

        $this->factory->getAcceptor('invalid-type');
    }
}
