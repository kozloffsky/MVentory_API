MVentory_Tm
===========

mVentory API extension for Magento works with [mVentory Android app](https://play.google.com/store/apps/details?id=com.mageventory) (referred to here simply as the app).


## Installation

*This extension requires mVentory_jQuery extension from (). Please install it first.*

Install mVentory_TM extension from (). Log out of the admin and log back in if you do not see or cannot access mVentory group of tabs after the installation.

## Configuration


This section gives a brief configuration overview. See other sections for more detailed information.

The following steps, required for configuration, are outlined below:

1. Grant user access via API
2. Configure attributes to be used in the app
3. Add category mapping
4. Test

####User access

1. Create a Magento Customer with shipping and billing address configured in customer details (required to complete sales).
2. Press `mVentory Access` button (see screenshot below).
3. Email the generated link to the customer so that they open it on the device where the app is installed. The app will be configured automatically when the link is opened on the device. [This video](https://googledrive.com/host/0B5Pkcq-TVIqrREFvbEwtRjVMd2s/profile-config-app.mp4) shows what the customer will see when the link is opened.

![](https://googledrive.com/host/0B5Pkcq-TVIqrNzliTXk5b3U4dWs/cust-access-links.png)

You can manage [finer details](https://github.com/mVentory/MVentory_Tm/wiki/User-configuration) of the user access on `SOAP/XML/RPC - Users` page. 

####Attributes

Magento has many product attributes, but only a few of them are used for product management in practice. The app shows a few basic attributes by default: 
* Price
* Weight
* SKU
* Qty
* Name
* Description 

Other attributes are only shown in the product details in the app only if their *Attribute Code* ends with an `_` (underscore) character. Append an underscore character to the *Attribute Code* of all new attributes that you want to appear in the app. Existing attributes can have the underscore appended / removed by pressing on `Add to mVentory` / `Remove from mVentory` button.

Note: changing an attribute code requires refreshing Magento indexes.

Attribute code examples that are recognised by the app: `cpu_frequency_`, `age_`, `not_a_useful_attribute_`.
Ignored by the app: `cpu_frequency`, `age`, `not_a_useful_attribute`.

Certain attributes are created by the extension and are reserved.

mVentory has many special features for manipulating attributes and their values. Read more [here](https://github.com/mVentory/MVentory_Tm/wiki/Attribute-features).

####Category mapping
The app does not allow the user to choose the category of the product. Instead, the categories are mapped based on product properties.

Open an attribute set and scroll down to `Category Matching section`.

1. Create a default mapping rule by selecting a category and saving the rule. Any product from this attribute set will be placed under the selected category.
2. Create individual rules by selecting attributes and their values from the dropdowns and mapping them to categories on the right.

mVentory extension evaluates attributes from top down. The first matching rule is applied. You can drag saved rule blocks up and down to arrange them from the most specific at the top to the most generic at the bottom.

* Multiple values of the same attribute are combined using OR operand.
* Multiple attributes are combined using AND operand.
* Used attribute and values are greyed out, but can be used as many times as needed. 

Read more about [category matching rules](https://github.com/mVentory/MVentory_Tm/wiki/Category-mapping).

