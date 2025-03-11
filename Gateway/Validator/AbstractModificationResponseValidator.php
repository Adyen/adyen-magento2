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

use Adyen\AdyenException;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AbstractModificationResponseValidator extends AbstractValidator
{
    const REQUIRED_RESPONSE_FIELDS = [
        'status',
        'pspReference'
    ];

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param AdyenLogger $adyenLogger
     * @param string $modificationType
     * @param array $validStatuses
     * @throws AdyenException
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly AdyenLogger $adyenLogger,
        private readonly string $modificationType,
        private readonly array $validStatuses
    ) {
        if (empty($modificationType) || empty($validStatuses)) {
            throw new AdyenException(
                __('Modification response can not be handled due to missing constructor arguments!')
            );
        }

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
            foreach (self::REQUIRED_RESPONSE_FIELDS as $requiredField) {
                if (!array_key_exists($requiredField, $response)) {
                    $errorMessage = __(
                        '%1 field is missing in %2 response.',
                        $requiredField,
                        $this->getModificationType()
                    );

                    $this->adyenLogger->error($errorMessage);

                    $isValid = false;
                    $errorMessages[] = $errorMessage;

                    break;
                }
            }

            if (!in_array($response['status'], $this->getValidStatuses())) {
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
