MVentory_Tm
===========

mVentory API extension for Magento works with [mVentory android app](https://play.google.com/store/apps/details?id=com.mageventory).


## Installation

Install the extension from ().
Log out of the admin and log back in if you do not see or cannot access mVentory group of tabs after the installation.

##### jQuery conflict
This extension may fail to install if another mVentory extension is already installed because they use the same jQuery library from the same location.

`CONNECT ERROR: Package file is invalid './js/jquery/jquery-min.js' already exists`

You can delete the existing file and re-try the installation.


## Configuration


This section gives a brief configuration overview. See other sections for more detailed information.

1. Grand user access via API
2. Configure attributes to be used in the app
3. Add category mapping
4. Test

####User access

Create a customer in the target store (the user will have access to that store only) or in the admin store (access to all stores).

Make sure the customer has shipping and billing address configured in customer details (required to complete sales).

![](https://googledrive.com/host/0B5Pkcq-TVIqrNzliTXk5b3U4dWs/cust-billing-addr.png)



Save the customer and bring up the customer details on the screen. 

Press `mVentory access` button.

![](https://googledrive.com/host/0B5Pkcq-TVIqrNzliTXk5b3U4dWs/cust-mv-access-button.png)

Email the generated link to the customer so that they open it on the device where the app is installed.
The customer has to have the app already installed and when the link is clicked it will complete the app configuration automatically.

![](https://googledrive.com/host/0B5Pkcq-TVIqrNzliTXk5b3U4dWs/cust-access-links.png)

You can manage finer details of the user access on `SOAP/XML/RPC - Users` page.

####Attributes

Magento has many product attributes, but only a few of them are used for product management. The app shows a few basic attributes by default: 
* Price
* Weight
* SKU
* Qty
* Name
* Description 

Add an `_` (underscore) to the attribute code on attribute details page to inclkude it in the product details for the app. Existing attributes can have the underscore added / removed by pressing on `Add/Remove to mVentory` button.

Note, changing an attribute code requires refreshing Magento indexes.

Attrbute code examples that are recognised by the app: `cpu_frequency_`, `age_`, `not_a_useful_attribute_`
Ignored by the app: `cpu_frequency`, `age`, `not_a_useful_attribute`

Certain attributes are created by the extension and are reserved.

####Category mapping
The app does not allow the user to choose the category of the product. Instead, the categories are mapped based on product properties.

Open an attribute set and scroll down to `Category Matching section`.

1. Create a default mapping rule by selecting a category and saving the rule. Any product from this attribute set will be placed under the selected category.
2. Create individual rules by selecting attributes and their values from the dropdowns and mapping them to categories on the right.

mVentory extension evaluates attributes from top down. The first matching rule is applied. You can drag saved rule blocks up and down to arrange them from the most specific at the top to the most generic at the bottom.

* Multiple values of the same attribute are combined using OR operand.
* Multiple attributes are combined using AND operand.
* Used attribute and values are greyed out, but can be used as many times as needed. 



