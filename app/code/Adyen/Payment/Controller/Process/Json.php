<?php


namespace Adyen\Payment\Controller\Process;


use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Class Json
 * @package Adyen\Payment\Controller\Process
 */
class Json extends \Magento\Framework\App\Action\Action
{


    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $_resultFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context
    ) {
        parent::__construct($context);
        $this->_objectManager = $context->getObjectManager();
        $this->_resultFactory = $context->getResultFactory();
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {

        //TODO validate the notification with authentication!!

        // check duplicates

        try {
//            $notificationItems = json_decode(file_get_contents('php://input'), true);
            $notificationItems = json_decode('{"live":"false","notificationItems":[{"NotificationRequestItem":{"additionalData":{"expiryDate":"12\/2012"," NAME1 ":"VALUE1","authCode":"1234","cardSummary":"7777","totalFraudScore":"10","hmacSignature":"yGnVWLP+UcpqjHTJbO5IUkG4ZdIk3uHCu62QAJvbbyg=","NAME2":"  VALUE2  ","fraudCheck-6-ShopperIpUsage":"10"},"amount":{"currency":"EUR","value":10100},"eventCode":"AUTHORISATION","eventDate":"2015-09-11T13:53:21+02:00","merchantAccountCode":"MagentoMerchantByteShop1","merchantReference":"000000023","operations":["CANCEL","CAPTURE","REFUND"],"paymentMethod":"visa","pspReference":"test_AUTHORISATION_1","reason":"1234:7777:12\/2012","success":"true"}}]}', true);


            $notificationMode = isset($notificationItems['live']) ? $notificationItems['live'] : "";

            if($notificationMode != "" && $this->_validateNotificationMode($notificationMode))
            {
                foreach($notificationItems['notificationItems'] as $notificationItem)
                {
                    $status = $this->_processNotification($notificationItem['NotificationRequestItem']);
                    if($status == "401"){
                        $this->_return401();
                        return;
                    }
                }
                $this->getResponse()
                    ->clearHeader('Content-Type')
                    ->setHeader('Content-Type', 'text/html')
                    ->setBody("[accepted]");
                return;
            } else
            {
                if($notificationMode == "") {
                    $this->_return401();
                    return;
                }

                throw new \Magento\Framework\Exception\LocalizedException(__('Mismatch between Live/Test modes of Magento store and the Adyen platform'));
            }


        } catch (Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @param $notificationMode
     * @return bool
     */
    protected function _validateNotificationMode($notificationMode)
    {
//        $mode = $this->_getConfigData('demoMode');
//        if ($mode=='Y' &&  $notificationMode == "false" || $mode=='N' &&  $notificationMode == 'true') {
//            return true;
//        }
//        return false;
        return true;
    }


    /**
     * $desc save notification into the database for cronjob to execute notificaiton
     * @param $response
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _processNotification($response)
    {
        try {

            $notification = $this->_objectManager->create('Adyen\Payment\Model\Notification');

            if(isset($response['pspReference'])) {
                $notification->setPspreference($response['pspReference']);
            }
            if(isset($response['merchantReference'])) {
                $notification->setMerchantReference($response['merchantReference']);
            }
            if(isset($response['eventCode'])) {
                $notification->setEventCode($response['eventCode']);
            }
            if(isset($response['success'])) {
                $notification->setSuccess($response['success']);
            }
            if(isset($response['paymentMethod'])) {
                $notification->setPaymentMethod($response['paymentMethod']);
            }
            if(isset($response['amount'])) {
                $notification->setAmountValue($response['amount']['value']);
                $notification->setAmountCurrency($response['amount']['currency']);
            }
            if(isset($response['reason'])) {
                $notification->setReason($response['reason']);
            }
            if(isset($response['additionalData'])) {
                $notification->setAddtionalData(serialize($response['additionalData']));
            }
            if(isset($response['done'])) {
                $notification->setDone($response['done']);
            }

            $notification->save();

        } catch(Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    /**
     *
     */
    protected function _return401()
    {

        $this->getResponse()->setHttpResponseCode(401);
    }
}