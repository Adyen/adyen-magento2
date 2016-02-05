<?php
/**
 * Loads catalog attributes configuration from multiple XML files by merging them together
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Adyen\Payment\Model\Config;

class Reader extends \Magento\Framework\Config\Reader\Filesystem
{
    /**
     * List of identifier attributes for merging
     *
     * @var array
     */
    protected $_idAttributes = [
        '/payment/adyen_credit_cards/type' => 'id'
    ];


    /**
     * Load configuration scope
     *
     * @param string|null $scope
     * @return array
     */
    public function read($scope = null)
    {
        $scope = $scope ?: $this->_defaultScope;
        $fileList = $this->_fileResolver->get($this->_fileName, $scope);
        if (!count($fileList)) {
            return [];
        }
        $output = $this->_readFiles($fileList);
        return $output;
    }

    /**
     * Read configuration files
     *
     * @param array $fileList
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _readFiles($fileList)
    {
        /** @var \Magento\Framework\Config\Dom $configMerger */
        $configMerger = null;
        foreach ($fileList as $key => $content) {
            try {
                if (!$configMerger) {
                    $configMerger = $this->_createConfigMerger($this->_domDocumentClass, $content);
                } else {
                    $configMerger->merge($content);
                }
            } catch (\Magento\Framework\Config\Dom\ValidationException $e) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    new \Magento\Framework\Phrase("Invalid XML in file %1:\n%2", [$key, $e->getMessage()])
                );
            }
        }
        if ($this->validationState->isValidationRequired()) {
            $errors = [];
            if ($configMerger && !$configMerger->validate($this->_schemaFile, $errors)) {
                $message = "Invalid Document \n";
                throw new \Magento\Framework\Exception\LocalizedException(
                    new \Magento\Framework\Phrase($message . implode("\n", $errors))
                );
            }
        }

        $output = [];
        if ($configMerger) {
            $output = $this->_converter->convert($configMerger->getDom());
        }
        return $output;
    }
}
