<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model;

use Adyen\Payment\Model\TransportBuilder;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Laminas\Mime\Mime;
use Magento\Framework\App\TemplateTypesInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Address;
use Magento\Framework\Mail\AddressConverter;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\MimeInterface;
use Magento\Framework\Mail\MimeMessageInterfaceFactory;
use Magento\Framework\Mail\MimePartInterfaceFactory;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\TemplateInterface;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class TransportBuilderTest extends AbstractAdyenTestCase
{
    private FactoryInterface&MockObject $templateFactory;
    private SenderResolverInterface&MockObject $senderResolver;
    private ObjectManagerInterface&MockObject $objectManager;
    private TransportInterfaceFactory&MockObject $transportFactory;

    private EmailMessageInterfaceFactory&MockObject $emailMessageFactory;
    private MimeMessageInterfaceFactory&MockObject $mimeMessageFactory;
    private MimePartInterfaceFactory&MockObject $mimePartFactory;
    private AddressConverter&MockObject $addressConverter;

    private TransportBuilder $subject;

    protected function setUp(): void
    {
        // Factories/converter: safe to use createGeneratedMock with explicit methods (less noisy).
        $this->templateFactory = $this->createGeneratedMock(FactoryInterface::class, ['get']);
        $this->senderResolver = $this->createGeneratedMock(SenderResolverInterface::class, ['resolve']);
        $this->objectManager = $this->createGeneratedMock(ObjectManagerInterface::class);
        $this->transportFactory = $this->createGeneratedMock(TransportInterfaceFactory::class, ['create']);

        $this->emailMessageFactory = $this->createGeneratedMock(EmailMessageInterfaceFactory::class, ['create']);
        $this->mimeMessageFactory = $this->createGeneratedMock(MimeMessageInterfaceFactory::class, ['create']);
        $this->mimePartFactory = $this->createGeneratedMock(MimePartInterfaceFactory::class, ['create']);
        $this->addressConverter = $this->createGeneratedMock(AddressConverter::class, ['convert', 'convertMany']);

        $this->subject = new TransportBuilder(
            $this->templateFactory,
            $this->senderResolver,
            $this->objectManager,
            $this->transportFactory,
            $this->emailMessageFactory,
            $this->mimeMessageFactory,
            $this->mimePartFactory,
            $this->addressConverter
        );
    }

    public function testGetTransportBuildsMessageWithAddressesAndResetsState(): void
    {
        $this->prepareTemplateMock(
            TemplateTypesInterface::TYPE_HTML,
            '<b>Hello</b>',
            'Subject &amp; stuff'
        );

        // Address is a concrete class; safe with createGeneratedMock
        $toAddress = $this->createGeneratedMock(Address::class);
        $cc1 = $this->createGeneratedMock(Address::class);
        $cc2 = $this->createGeneratedMock(Address::class);

        $this->addressConverter->expects($this->once())
            ->method('convert')
            ->with('to@example.com', 'To Name')
            ->willReturn($toAddress);

        $this->addressConverter->expects($this->once())
            ->method('convertMany')
            ->with([
                ['email' => 'cc1@example.com', 'name' => 'CC1'],
                ['email' => 'cc2@example.com', 'name' => 'CC2'],
            ])
            ->willReturn([$cc1, $cc2]);

        // IMPORTANT: do NOT use createGeneratedMock(MimePartInterface::class, ['getCharset'])
        // PHPUnit 10 will fatal because the interface has many abstract methods.
        $contentPart = $this->createMock(\Magento\Framework\Mail\MimePartInterface::class);
        $contentPart->method('getCharset')->willReturn('utf-8');

        $this->mimePartFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $args) {
                return $args['content'] === '<b>Hello</b>'
                    && $args['type'] === MimeInterface::TYPE_HTML;
            }))
            ->willReturn($contentPart);

        $mimeMessage = $this->createMock(\Magento\Framework\Mail\MimeMessageInterface::class);
        $this->mimeMessageFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $args) use ($contentPart) {
                return isset($args['parts'])
                    && count($args['parts']) === 1
                    && $args['parts'][0] === $contentPart;
            }))
            ->willReturn($mimeMessage);

        $message = $this->createMock(MessageInterface::class);

        $this->emailMessageFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) use ($mimeMessage) {
                return $data['encoding'] === 'utf-8'
                    && $data['body'] === $mimeMessage
                    && $data['subject'] === 'Subject & stuff'
                    && isset($data['to'][0]) && $data['to'][0] instanceof Address
                    && isset($data['cc']) && count($data['cc']) === 2
                    && $data['cc'][0] instanceof Address
                    && $data['cc'][1] instanceof Address;
            }))
            ->willReturn($message);

        $transport = $this->createMock(TransportInterface::class);
        $this->transportFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $args) use ($message) {
                return isset($args['message'])
                    && $args['message'] instanceof MessageInterface
                    && $args['message'] !== $message; // cloned
            }))
            ->willReturn($transport);

        $result = $this->subject
            ->setTemplateIdentifier('adyen_template')
            ->setTemplateModel('Some\Model')
            ->setTemplateVars(['a' => 'b'])
            ->setTemplateOptions(['area' => 'frontend'])
            ->addTo('to@example.com', 'To Name')
            ->addCc([
                ['email' => 'cc1@example.com', 'name' => 'CC1'],
                ['email' => 'cc2@example.com', 'name' => 'CC2'],
            ])
            ->getTransport();

        self::assertSame($transport, $result);

        self::assertNull($this->readProtectedProperty($this->subject, 'templateIdentifier'));
        self::assertNull($this->readProtectedProperty($this->subject, 'templateVars'));
        self::assertNull($this->readProtectedProperty($this->subject, 'templateOptions'));
    }

    public function testGetTransportAddsAttachmentParts(): void
    {
        $this->prepareTemplateMock(
            TemplateTypesInterface::TYPE_TEXT,
            'Hello',
            'Plain subject'
        );

        $contentPart = $this->createMock(\Magento\Framework\Mail\MimePartInterface::class);
        $contentPart->method('getCharset')->willReturn('utf-8');

        $attachmentPart = $this->createMock(\Magento\Framework\Mail\MimePartInterface::class);

        $createCall = 0;

        $this->mimePartFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (array $args) use (&$createCall, $contentPart, $attachmentPart) {
                $createCall++;

                if ($createCall === 1) {
                    self::assertSame('Hello', $args['content']);
                    self::assertSame(MimeInterface::TYPE_TEXT, $args['type']);
                    return $contentPart;
                }

                self::assertSame('PDF_BYTES', $args['content']);
                self::assertSame(Mime::TYPE_OCTETSTREAM, $args['type']);
                self::assertSame(Mime::DISPOSITION_ATTACHMENT, $args['disposition']);
                self::assertSame(Mime::ENCODING_BASE64, $args['encoding']);
                self::assertSame('invoice.pdf', $args['fileName']);

                return $attachmentPart;
            });

        $mimeMessage = $this->createMock(\Magento\Framework\Mail\MimeMessageInterface::class);
        $this->mimeMessageFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $args) use ($contentPart, $attachmentPart) {
                return isset($args['parts'])
                    && count($args['parts']) === 2
                    && $args['parts'][0] === $contentPart
                    && $args['parts'][1] === $attachmentPart;
            }))
            ->willReturn($mimeMessage);

        $message = $this->createMock(MessageInterface::class);
        $this->emailMessageFactory->method('create')->willReturn($message);

        $transport = $this->createMock(TransportInterface::class);
        $this->transportFactory->method('create')->willReturn($transport);

        $this->subject
            ->setTemplateIdentifier('t')
            ->setTemplateModel('m')
            ->setTemplateVars([])
            ->setTemplateOptions([])
            ->setAttachment('PDF_BYTES', 'invoice.pdf');

        self::assertSame($transport, $this->subject->getTransport());
    }

    public function testPrepareMessageThrowsForUnknownTemplateType(): void
    {
        $this->prepareTemplateMock(999, 'X', 'S');

        $this->subject
            ->setTemplateIdentifier('t')
            ->setTemplateModel('m')
            ->setTemplateVars([])
            ->setTemplateOptions([]);

        $this->expectException(LocalizedException::class);
        $this->subject->getTransport();
    }

    public function testSetFromByScopeUsesSenderResolverAndAddressConverter(): void
    {
        $fromAddress = $this->createGeneratedMock(Address::class);

        $this->senderResolver->expects($this->once())
            ->method('resolve')
            ->with('general', 5)
            ->willReturn(['email' => 'from@example.com', 'name' => 'From Name']);

        $this->addressConverter->expects($this->once())
            ->method('convert')
            ->with('from@example.com', 'From Name')
            ->willReturn($fromAddress);

        $this->subject->setFromByScope('general', 5);

        $messageData = $this->readPrivateProperty($this->subject, 'messageData');
        self::assertArrayHasKey('from', $messageData);
        self::assertCount(1, $messageData['from']);
        self::assertInstanceOf(Address::class, $messageData['from'][0]);
    }

    public function testAddBccWithArrayMergesAddresses(): void
    {
        $bcc1 = $this->createGeneratedMock(Address::class);
        $bcc2 = $this->createGeneratedMock(Address::class);

        $this->addressConverter->expects($this->once())
            ->method('convertMany')
            ->with([
                ['email' => 'a@example.com'],
                ['email' => 'b@example.com'],
            ])
            ->willReturn([$bcc1, $bcc2]);

        $this->subject->addBcc([
            ['email' => 'a@example.com'],
            ['email' => 'b@example.com'],
        ]);

        $messageData = $this->readPrivateProperty($this->subject, 'messageData');

        self::assertArrayHasKey('bcc', $messageData);
        self::assertCount(2, $messageData['bcc']);
        self::assertSame($bcc1, $messageData['bcc'][0]);
        self::assertSame($bcc2, $messageData['bcc'][1]);
    }

    public function testSetReplyToAddsReplyToAddress(): void
    {
        $replyTo = $this->createGeneratedMock(Address::class);

        $this->addressConverter->expects($this->once())
            ->method('convert')
            ->with('reply@example.com', 'Reply Name')
            ->willReturn($replyTo);

        $this->subject->setReplyTo('reply@example.com', 'Reply Name');

        $messageData = $this->readPrivateProperty($this->subject, 'messageData');

        self::assertArrayHasKey('replyTo', $messageData);
        self::assertSame($replyTo, $messageData['replyTo'][0]);
    }

    public function testAddToMultipleTimesAppends(): void
    {
        $addr1 = $this->createGeneratedMock(Address::class);
        $addr2 = $this->createGeneratedMock(Address::class);

        $this->addressConverter->expects($this->exactly(2))
            ->method('convert')
            ->willReturnOnConsecutiveCalls($addr1, $addr2);

        $this->subject->addTo('a@example.com');
        $this->subject->addTo('b@example.com');

        $messageData = $this->readPrivateProperty($this->subject, 'messageData');

        self::assertCount(2, $messageData['to']);
        self::assertSame($addr1, $messageData['to'][0]);
        self::assertSame($addr2, $messageData['to'][1]);
    }

    public function testSetAttachmentWithNullContentDoesNothing(): void
    {
        $this->subject->setAttachment(null, 'file.pdf');

        $attachments = $this->readPrivateProperty($this->subject, 'attachments');
        self::assertEmpty($attachments);
    }

    public function testGetTransportResetsStateOnException(): void
    {
        $this->prepareTemplateMock(999, 'X', 'S');

        $this->subject
            ->setTemplateIdentifier('t')
            ->setTemplateVars(['a' => 'b'])
            ->setTemplateModel('m')
            ->setTemplateOptions([]);

        try {
            $this->subject->getTransport();
        } catch (LocalizedException $e) {
            // expected
        }

        self::assertNull($this->readProtectedProperty($this->subject, 'templateIdentifier'));
        self::assertNull($this->readProtectedProperty($this->subject, 'templateVars'));
    }

    public function testSubjectIsHtmlDecoded(): void
    {
        $this->prepareTemplateMock(
            TemplateTypesInterface::TYPE_TEXT,
            'Hello',
            'Order &amp; Confirmation'
        );

        $contentPart = $this->createMock(\Magento\Framework\Mail\MimePartInterface::class);
        $contentPart->method('getCharset')->willReturn('utf-8');

        $this->mimePartFactory->method('create')->willReturn($contentPart);
        $this->mimeMessageFactory->method('create')->willReturn(
            $this->createMock(\Magento\Framework\Mail\MimeMessageInterface::class)
        );

        $this->emailMessageFactory->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) {
                return $data['subject'] === 'Order & Confirmation';
            }))
            ->willReturn($this->createMock(MessageInterface::class));

        $this->transportFactory->method('create')
            ->willReturn($this->createMock(TransportInterface::class));

        $this->subject
            ->setTemplateIdentifier('t')
            ->setTemplateModel('m')
            ->setTemplateVars([])
            ->setTemplateOptions([])
            ->getTransport();
    }

    private function prepareTemplateMock(int $type, string $processedContent, string $subject): TemplateInterface
    {
        // Use createMock() here: TemplateInterface inherits TemplateTypesInterface::isPlain().
        // Using createGeneratedMock() with onlyMethods() can fatal if a method is missing.
        $template = $this->createMock(TemplateInterface::class);

        $template->method('setVars')->willReturnSelf();
        $template->method('setOptions')->willReturnSelf();
        $template->method('processTemplate')->willReturn($processedContent);
        $template->method('getType')->willReturn($type);
        $template->method('getSubject')->willReturn($subject);
        $template->method('isPlain')->willReturn($type === TemplateTypesInterface::TYPE_TEXT);

        $this->templateFactory->expects($this->once())
            ->method('get')
            ->willReturn($template);

        return $template;
    }

    private function readProtectedProperty(object $obj, string $prop): mixed
    {
        $ref = new \ReflectionClass($obj);
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        return $p->getValue($obj);
    }

    private function readPrivateProperty(object $obj, string $prop): mixed
    {
        $ref = new \ReflectionClass($obj);
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        return $p->getValue($obj);
    }
}
