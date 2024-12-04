<?php

namespace Adyen\Payment\Test\Unit\Controller\Adminhtml\Configuration;

use Adyen\Payment\Controller\Adminhtml\Configuration\DownloadApplePayDomainAssociationFile;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class DownloadApplePayDomainAssociationFileTest extends AbstractAdyenTestCase
{
    protected ?DownloadApplePayDomainAssociationFile $downloadApplePayDomainAssociationFile;
    protected MockObject|Context $contextMock;
    protected MockObject|DirectoryList $directoryListMock;
    protected MockObject|File $fileIoMock;
    protected MockObject|AdyenLogger $adyenLoggerMock;
    protected MockObject|ResultFactory $resultFactoryMock;
    protected MockObject|ResultInterface $resultMock;
    protected MockObject|RedirectInterface $redirectMock;
    protected MockObject|ManagerInterface $managerMock;

    public function setUp(): void
    {
        $this->redirectMock = $this->createMock(RedirectInterface::class);

        $this->resultMock = $this->createGeneratedMock(
            Redirect::class
        );

        $this->resultFactoryMock = $this->createGeneratedMock(
            ResultFactory::class,
            ['create']
        );
        $this->resultFactoryMock->method('create')->willReturn($this->resultMock);

        $this->managerMock = $this->createMock(ManagerInterface::class);

        $this->contextMock = $this->createMock(Context::class);
        $this->contextMock->method('getResultFactory')->willReturn($this->resultFactoryMock);
        $this->contextMock->method('getRedirect')->willReturn($this->redirectMock);
        $this->contextMock->method('getMessageManager')->willReturn($this->managerMock);

        $this->directoryListMock = $this->createMock(DirectoryList::class);
        $this->fileIoMock = $this->createMock(File::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);


        $this->downloadApplePayDomainAssociationFile = new DownloadApplePayDomainAssociationFile(
            $this->contextMock,
            $this->directoryListMock,
            $this->fileIoMock,
            $this->adyenLoggerMock
        );
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    public function testDownloadFileSuccessfully(): void
    {
        $this->directoryListMock->method('getPath')
            ->with('pub')
            ->willReturn('/var/www/html/pub');

        $this->fileIoMock->method('checkAndCreateFolder')
            ->with('/var/www/html/pub/.well-known')
            ->willReturn(true);

        $this->fileIoMock->method('read')
            ->with(
                'https://eu.adyen.link/.well-known/apple-developer-merchantid-domain-association',
                '/var/www/html/pub/.well-known/apple-developer-merchantid-domain-association'
            )
            ->willReturn(true);

        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('addAdyenDebug');
        $this->managerMock->expects($this->once())->method('addSuccessMessage');

        $redirectResponse = $this->downloadApplePayDomainAssociationFile->execute();

        $this->assertInstanceOf(Redirect::class, $redirectResponse);
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    public function testHttpNotFound(): void
    {
        $this->directoryListMock->method('getPath')
            ->with('pub')
            ->willReturn('/var/www/html/pub');

        $this->fileIoMock->method('checkAndCreateFolder')
            ->with('/var/www/html/pub/.well-known')
            ->willReturn(true);

        $this->fileIoMock->method('read')
            ->with(
                'https://eu.adyen.link/.well-known/apple-developer-merchantid-domain-association',
                '/var/www/html/pub/.well-known/apple-developer-merchantid-domain-association'
            )
            ->willReturn(false);

        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('error');
        $this->managerMock->expects($this->once())->method('addErrorMessage');

        $redirectResponse = $this->downloadApplePayDomainAssociationFile->execute();

        $this->assertInstanceOf(Redirect::class, $redirectResponse);
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    public function testFileSystemIOError(): void
    {
        $this->directoryListMock->method('getPath')
            ->with('pub')
            ->willReturn('/var/www/html/pub');

        $this->fileIoMock->method('checkAndCreateFolder')
            ->willThrowException(new LocalizedException(__('mock error message')));

        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('error');
        $this->managerMock->expects($this->once())->method('addErrorMessage');

        $redirectResponse = $this->downloadApplePayDomainAssociationFile->execute();

        $this->assertInstanceOf(Redirect::class, $redirectResponse);
    }
}