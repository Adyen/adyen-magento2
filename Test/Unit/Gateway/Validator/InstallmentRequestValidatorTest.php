<?php

namespace Adyen\Payment\Test\Unit\Gateway\Validator;

use Adyen\Payment\Gateway\Validator\InstallmentRequestValidator;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class InstallmentRequestValidatorTest extends AbstractAdyenTestCase
{
    protected ?InstallmentRequestValidator $validator;
    protected Config|MockObject $configMock;
    protected SerializerInterface|MockObject $serializerMock;
    protected QuoteRepository|MockObject $quoteRepositoryMock;
    protected ChargedCurrency|MockObject $chargedCurrencyMock;
    protected Data|MockObject $adyenHelperMock;
    protected ResultInterfaceFactory|MockObject $resultFactoryMock;

    protected function setUp(): void
    {
        $this->quoteRepositoryMock = $this->createMock(QuoteRepository::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->configMock = $this->createMock(Config::class);
        $this->resultFactoryMock = $this->createGeneratedMock(ResultInterfaceFactory::class, [
            'create'
        ]);

        $this->validator = new InstallmentRequestValidator(
            $this->resultFactoryMock,
            $this->configMock,
            $this->serializerMock,
            $this->quoteRepositoryMock,
            $this->chargedCurrencyMock,
            $this->adyenHelperMock
        );
    }

    protected function tearDown(): void
    {
        $this->validator = null;
    }

    private static function dataProvider(): array
    {
        return [
            ['quoteId' => 1],
            ['quoteId' => null],
        ];
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $quoteId
     * @return void
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function testValidate($quoteId)
    {
        $quoteAmountCurrency = $this->createMock(AdyenAmountCurrency::class);
        $quoteAmountCurrency->method('getAmount')->willReturn('100.00');

        $this->chargedCurrencyMock->method('getQuoteAmountCurrency')->willReturn($quoteAmountCurrency);

        $paymentMock = $this->createGeneratedMock(
            Payment::class,
            ['getAdditionalInformation'],
            ['getQuoteId']
        );
        $paymentMock->expects($this->once())->method('getQuoteId')->willReturn($quoteId);
        $paymentMock->expects($this->any())->method('getAdditionalInformation')
            ->willReturnMap([
                ['number_of_installments', 5],
                ['cc_type', 'visa']
            ]);

        $quoteMock = $this->createMock(Quote::class);

        $this->quoteRepositoryMock->expects($this->any())->method('get')
            ->with($quoteId)
            ->willReturn($quoteMock);

        $this->configMock->expects($this->any())->method('getAdyenCcConfigData')
            ->willReturnMap([
                ['enable_installments', null, true],
                ['installments', null, ['MOCK_INSTALLMENTS_IN_DB']],
            ]);

        $this->adyenHelperMock->expects($this->any())->method('getMagentoCreditCartType')
            ->with('visa')
            ->willReturn('VI');

        $unserializedInstallments = [
            'VI' => [
                10 => [3, 5],
                20 => [7, 10]
            ]
        ];

        $this->serializerMock->expects($this->any())
            ->method('unserialize')
            ->willReturn($unserializedInstallments);

        $this->resultFactoryMock->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => [],
                'errorCodes' => []
            ])
            ->willReturn($this->createMock(ResultInterface::class));

        $validationSubject = ['payment' => $paymentMock];
        $result = $this->validator->validate($validationSubject);

        $this->assertInstanceOf(ResultInterface::class, $result);
    }
}
