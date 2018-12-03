# WHMCS module

## WHMCS domain registration plugin for the NameISP API

### Requirements

- php 5.3+
- PHP cURL module installed

### Installation

- extract archive contents into your `/var/www/whmcs/modules/registrars` folder
- cd `/var/www/whmcs/modules/registrars/namesrs`
- run **install.php**

The installation script makes the following modifications:

1. creates 2 email templates
  - when generating and sending EPP code for a domain
  - when there is an error in the registration process (payment required, registration rejected)
2. creates 3 new tables in the database
  - **mod_namesrssession** - used to cache the session key used to communicate with the API
  - **tblnamesrshandles** - used to map domain IDs between WHMCS and NameSRS API
  - **tblnamesrsjobs** - used as a queue for API requests which do not return immediate response
3. creates a custom field `OrgNr` for client accounts which holds VAT/EIK registration number
 
### Principle of operation

Most of the API endpoints provide an immediate result (e.g. updating name servers or contact information). However, registration/transfer/renewal operations are  asynchronous by nature so the module puts such requests in a queue and expects the API to call a web hook (`/var/www/whmcs/modules/registrars/namesrs/callback.php`) when the requested operation has been performed. You should provide the URL of this script (how it can be reached from Internet) when applying for an API key.

### Configuring the plugin

![](https://github.com/nameisp/whmcs/raw/master/configuration.png)

### Debugging

The module sends debugging information to the WHMCS module logging mechanism

![](https://github.com/nameisp/whmcs/raw/master/logging.png)
