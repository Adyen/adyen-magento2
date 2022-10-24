<?php

class TransportBuilder extends
    \Magento\Framework\Mail\Template\TransportBuilder
{

    private const ADYEN_SUPPORT = 'support@adyen.com';
    public function getMessage()
    {
        return $this->message;
    }

    public function sendEmail($templateId =1, $storeId =1,$templateParams)
    {

        $emailTemplateVariables = array();
        $emailTempVariables['myvar'] = '';

        //todo get these from the form
        $senderName = 'test';

        $senderEmail = 'sender@test.com';

        $postObject = new \Magento\Framework\DataObject();
        $postObject->setData($emailTempVariables);

        $sender = [
            'name' => $senderName,
            'email' => $senderEmail,
        ];

        $transport = $this->_transportBuilder->setTemplateIdentifier($templateId)
            ->setTemplateOptions(['area' => Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $storeId])
            ->setTemplateVars($templateParams)
            ->setFrom('someemail@email.com')
            ->addTo(self::ADYEN_SUPPORT)
            ->setReplyTo($sender)

            ->getTransport();
        $transport->sendMessage();
    }

}
