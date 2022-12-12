<?php

namespace Adyen\Payment\Model;

class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
    public function addAttachment(
        $body,
        $mimeType    = \Zend_Mime::TYPE_OCTETSTREAM,
        $disposition = \Zend_Mime::DISPOSITION_ATTACHMENT,
        $encoding    = \Zend_Mime::ENCODING_BASE64,
        $filename    = null
    ) {
        $this->message->createAttachment($body, $mimeType, $disposition, $encoding, $filename);
        return $this;
    }
}
