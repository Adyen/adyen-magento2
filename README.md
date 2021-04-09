# Adyen Payment plugin for Magento2
Use Adyen's plugin for Magento 2 to offer frictionless payments online, in-app, and in-store.

## Integration
The plugin integrates card component(Secured Fields) using Adyen Checkout for all card payments. Local/redirect payment methods are integrated with DirectoryLookup and HPP. For Point Of Sale (POS) payments we use Terminal API using Cloud-based communication. Boleto and SEPA are a direct API integration into Adyen.

### Support Vault and Instant Purchase ###
Inside Adyen toggle the following settings on inside the API and Responses section (settings -> API and Responses)
* Recurring details
* Card summary
* Expiry date
* Variant

## Requirements
This plugin supports Magento2 version 
* 2.2.9 and higher
* 2.3.1 and higher
* 2.4 

## Contributing
We strongly encourage you to join us in contributing to this repository so everyone can benefit from:
* New features and functionality
* Resolved bug fixes and issues
* Any general improvements

Read our [**contribution guidelines**](CONTRIBUTING.md) to find out how.


## Installation
You can install our plugin through Composer:
```
composer require adyen/module-payment
bin/magento module:enable Adyen_Payment
bin/magento setup:upgrade
```
For more information see our [installation section](https://docs.adyen.com/developers/plugins/magento-2/set-up-the-plugin-in-magento?redirect#step1installtheplugin)

 ## Documentation
[Magento 2 documentation](https://docs.adyen.com/developers/plugins/magento-2)


## Setup Cron
Make sure that your Magento cron is running every minute. We are using a cronjob to process the notifications (our webhook service) and to update Adyen servers' IP addresses. The cronjobs will be executed every minute.

```
<group id="adyen_payment">
    <job name="adyen_payment_process_notification" instance="Adyen\Payment\Model\Cron" method="processNotification">
        <schedule>*/1 * * * *</schedule>
    </job>
    <job name="adyen_payment_server_address_caching" instance="Adyen\Payment\Cron\ServerIpAddress" method="execute">
        <schedule>*/1 * * * *</schedule>
    </job>
</group>
```

The notification processing service queries the records that have been received at least 2 minutes ago. This is to ensure that Magento has created the order, and all save after events are executed. A handy tool to get insight into your cronjobs is AOE scheduler. You can download this tool through Magento Connect or GitHub.

If you need to setup your cronjob in Magento <a href="http://devdocs.magento.com/guides/v2.0/config-guide/cli/config-cli-subcommands-cron.html" target="_blank">this is described here</a>

## Verified payment methods

 * Card payment methods
    * Card payment method (non-3DS, 3DS1 and 3DS2)
    * One click payment methods (using Billing agreements or Vault)
    * Recurring payment methods (using Billing agreements or Vault) 
   

* Local payment methods
   * Afterpay
   * Apple Pay
   * Bancontact
   * Banktransfer IBAN
   * Doku
   * Google Pay
   * iDeal
   * Klarna
   * Oney
   * Zip
   * Multibanco
   * Konbini
   * Swish
   * MBway
   * Twint
   * Paypal
   * SEPA Direct Debit
   * PIX
   * Ratepay
   * Oxxo
   * Econtexts

_This is not an extensive list of all the supported payment methods but only the ones that have been already verified. In case you would like to see other payment methods in the list as well please contact us to verify those too._

## Support
If you have a feature request, or spotted a bug or a technical problem, create a GitHub issue. For other questions, contact our [support team](https://support.adyen.com/hc/en-us/requests/new?ticket_form_id=360000705420).

## API Library
This module is using the Adyen APIs Library for PHP for all (API) connections to Adyen.
<a href="https://github.com/Adyen/adyen-php-api-library" target="_blank">This library can be found here</a>

## License
MIT license. For more information, see the LICENSE file.
