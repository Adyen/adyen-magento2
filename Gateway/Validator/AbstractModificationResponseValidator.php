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

namespace Adyen\Payment\Gateway\Validator;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

abstract class AbstractModificationResponseValidator extends AbstractValidator
{
    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param AdyenLogger $adyenLogger
     * @param string $modificationType
     * @param array $validStatuses
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly AdyenLogger $adyenLogger,
        private readonly string $modificationType,
        private readonly array $validStatuses
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * @param array $validationSubject
     * @return ResultInterface
     * @throws ValidatorException
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $responseCollection = SubjectReader::readResponse($validationSubject);

        if (empty($responseCollection)) {
            throw new ValidatorException(__("No responses were provided"));
        }

        $isValid = true;
        $errorMessages = [];

        foreach ($responseCollection as $response) {
            if (empty($response['status']) || !in_array($response['status'], $this->getValidStatuses())) {
                $errorMessage = __(
                    'An error occurred while validating the %1 response',
                    $this->getModificationType()
                );

                $logMessage = sprintf(
                    "An error occurred while validating the %s response%s. %s",
                    $this->getModificationType(),
                    isset($response['formattedModificationAmount']) ?
                        ' with amount ' .
                        $response['formattedModificationAmount'] : '',
                    $response['error'] ?? ''
                );

                $this->adyenLogger->error($logMessage);

                $errorMessages[] = $errorMessage;
                $isValid = false;
            }
        }

        return $this->createResult($isValid, $errorMessages);
    }

    /**
     * @return string
     */
    private function getModificationType(): string
    {
        return $this->modificationType;
    }

    /**
     * @return array
     */
    private function getValidStatuses(): array
    {
        return $this->validStatuses;
    }
}
