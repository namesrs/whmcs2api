# WHMCS modules from NameSRS - version 75 (02 May 2023, 11:08)
-----
# NOTE 1
```
Once you have a reseller account with Name SRS you can request for the API key 
and base URL needed for this module
```

# NOTE 2
```
After module installation you need to give the callback URL to Name SRS for enabling the webhook
```

# NOTE 3
```
In case you used a third party module to handle domains managed by Name SRS, 
then you need to send a list of those domains including two columns. 
One with the WHMCS ID's and the other with the domain names. 
Name SRS will then import those to ensure the callbacks works optimal for you.
```

-----
## 1. WHMCS registrar module

### Requirements

- PHP 5.6+
- PHP cURL module installed

### Installation

- extract archive contents inside your `/var/www/whmcs` folder
- cd `/var/www/whmcs/modules/registrars/namesrs`
- run `/usr/bin/php install.php`

The installation script makes the following modifications:

1. creates 2 email templates
  - when generating and sending EPP auth code for a domain
  - when there is an error in the registration process (payment required, registration rejected)
2. creates 3 new tables in the database
  - **mod_namesrssession** - to cache the session key used to communicate with the API
  - **tblnamesrshandles** - used to map domain IDs between WHMCS and NameSRS API
  - **tblnamesrsjobs** - used as a queue for API requests which do not provide immediate end result
3. ~~creates a custom field `OrgNr` for client accounts which holds VAT/EIK registration number or Personal ID - required by the SE/NU registries~~ The module no longer creates the field for you - instead you are expected to provide the field name that is specific for your WHMCS installation as part of the module configuration
4. creates the product group **Domain owner change** - used by the WHMCS provisioning module below
5. creates the custom product **Change registrant** - used by the WHMCS provisioning module below
6. assigns a price of 1.00 for the above product in each of the currently defined in WHMCS currencies - the reseller can then update this `profit margin`
7. creates a custom field for the above product - used by the WHMCS provisioning module below
 
### Principle of operation

Most of the API endpoints provide an immediate result (e.g. updating name servers or contact information). However, registration/transfer/renewal operations are  asynchronous by nature so the module puts such requests in a queue and expects the API to call a web hook (`/var/www/whmcs/modules/registrars/namesrs/callback.php`) when the requested operation has been performed. You should provide the URL of the `callback.php` script (how it can be reached from the public Internet) when applying for an API key from NameSRS.

### To get an idea how the callbacks work - take a look at the [callback flow chart](https://github.com/nameisp/whmcs/raw/master/callback_flow.png)

### The principle of operation of the module is illustrated on [its flowchart](https://github.com/nameisp/whmcs/raw/master/registrar_flow.png)

### Configuring the plugin

![](https://github.com/nameisp/whmcs/raw/master/configuration.png)

Domain registrations will fail if you do not provide the name of your custom field holding the Company/Person ID in the module config.

### Debugging

The module sends debugging information to the WHMCS module logging mechanism

![](https://github.com/nameisp/whmcs/raw/master/logging.png)

-----

## 2. WHMCS addon module - for bulk import of the NameSRS price-list

### Requirements

- PHP 5.6+
- NameSRS registrar module

### Installation

- extract archive contents into your `/var/www/whmcs` folder
- within the WHMCS admin area, go to **Setup -> Addon Modules**
- activate the **NameSRS Prices Importer** module
- configure the module - fill in your admin username and give the module `Full Administrator` Access Control permission.

### Importing prices

- within the WHMCS admin area, go to **Addons -> NameSRS Prices Importer**
- click the LOAD button to fetch the pricelist
- update the selling prices (either with a fixed margin, with a multiplier, or a combination of the two)
- select the desired TLDs to be imported (or all of them) - using the checkboxes
- select the desired domain addons (E-mail forwarding, DNS management, WHOIS protection, etc.) to be offered
- finally, import the prices into WHMCS

## Note

The bulk import is performed in 3 steps:

* the first step is to fetch the price list from the NameSRS through their API (with the help of NameSRS registrar module)
* on the second step you will be presented with a table with supported TLDs as rows and cost/selling prices for Registration/Renewal/Restoration/Transfer as columns
* you will be able to adjust the selling prices with the combination of a multiplier and an additive fixed amount - SELLING = COST * Multiplier + FixedAmount
* only the currencies which are both defined in the WHMCS and supported by the NameSRS price list will be listed; if you see "N/A" in one or more table cells - the corresponding operation is not supported for the relevant TLD
* if the whole table is empty - then your WHMCS does not have defined any of the currencies supported by the NameSRS price list (at the moment - SEK/EUR/USD)
* and the third step is to select (with checkboxes) the prices for which TLDs you would like to import - then press the IMPORT button

-----

## 3. WHMCS provisioning module - to handle registrant (domain owner) change

## This module uses a custom product ("Change registrant") to take into account the charge/fee for registrant changes (enforced by some TLDs) when using the NameISP domain registrar

### Requirements

- PHP 5.6+
- NameSRS registrar module

### Installation

- extract archive contents into your `/var/www/whmcs` folder

### Configuration

- within the WHMCS admin area, go to **Setup -> Products/Services -> Products/Services**
- find the product **Change registrant (hidden)** and click the `edit` icon at the right end of the line
- go to the **Pricing** tab
- enter your profit margin (the fixed amount that will be added to the price coming from the NameSRS API) under the **One Time** column - preferrably for all of the available currencies 

### Principle of operation

WHMCS recognizes only **3** possible actions on domains - `registration`, `renewal` and `transfer`. However, there are some registries which charge a fee when you want to change the domain owner (registrant).
We need to put something in the order so that we are able to charge the customer. This is achieved by using a custom product (ours is called **Change registrant**).
The NameSRS registrar module already provides a custom screen for changing the registrant details - once the customer saves the changes, our custom product will be added to the cart and the customer will be redirected to the checkout screen.
Once the order/invoice is marked as paid, WHMCS will call our provisioning module (the one you have just installed) - it will read the registrant details from the order and call the NameSRS API to change the registrant.