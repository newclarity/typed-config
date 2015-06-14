<?php
/*
 * Example showing how to use Typed Config; can be run standalone from the PHP command line
 *
 * This example shows how to use Typed Config to model an expected class structure in JSON in first-class PHP classes
 * complete with methods to set defaults and validate values. This allows you to take any externally generated and
 * untrusted source of JSON data and cleanse it to be in the format your program needs simply and easily.
 *
 * @author: Mike Schinkel <mike@newclarity.net>
 * @license: GPLv2
 */

include( dirname( __DIR__ ) . '/typed-config.php' );

function main() {
  /**
   * Loads the JSON into root $order' object class 'Example_Order'
   * See class definitions for how to model classes to load the JSON.
   *
   * @var \Typed_Config\Data $order
   */
  $order = \Typed_Config\Loader::load( 'order', 'Example_Order', get_json() );

  /**
   * Grab the list of possible hooks
   */
  $hooks = $order->get_hooks();

  /**
   * Strip all the meta properties off except __id__ to make it easier to
   *  visually inspect the data displayed when using print_r().
   */
  $order->strip_meta( '__id__' );

  /**
   * Capture the print_r() value to $output and echo it.
   */
  ob_start();
  print_r( $order );
  echo "\nHOOKS AVAIALBLE(0) AND CALLED(1):\n";
  print_r( $hooks );
  $output = ob_get_clean();
  echo $output;

  /**
   * Grab the hardcoded value we expect this example to return
   */
  $expected = get_expected();

  /**
   * Lastly test to see if they are the same sans whitespace, echo results of our test.
   */
  if ( strip_ws( $output ) == strip_ws( $expected ) )
    echo "SUCCESS! Output compared to test value as expected! :-)\n";
  else
    echo "FAILURE! Output NOT comparable to test value. Hmm. :-(\n";

}
/**
 * Returns the example JSON string
 *
 * @return string
 */
function get_json() {
  $json =<<<JSON
{
  "id": 12345,
  "customer": "John Smith <john.smith@acme.com>",
  "addresses": {
    "billing": {
      "street": "123 Main Street.",
      "city" : "Anytown",
      "country": "USA",
      "post_code": "12345-9876"
    }
  },
  "items": [
    { "sku": "abc123", "description": "Large Blue Widget", "quantity": 1 },
    { "sku": "xyz987", "description": "Small Red Widget", "quantity": 3 }
  ]
}
JSON;
  return $json;
}

/**
 * This class defines both the "schema" to load the JSON as well as it's for the
 */
class Example_Order extends \Typed_Config\Data {

  /**
   * Set any default values for properties here.
   */
  var $id;
  var $customer;
  var $addresses = array();
  var $items = array();

  /**
   * Defines the schema of an Order to using an Example_Customer class for the 'customer' property,
   * the 'addresses' property to be a keyed array of Example_Address objects with 'billing' and 'shipping' keys,
   * and an array of Example_Item objects in the 'items' property.
   */
  protected $__schema__ = array(
    'customer' => 'Example_Customer',
    'addresses' => array(
      'billing' => 'Example_Address',
      'shipping' => 'Example_Address',
    ),
    'items' => array( 'Example_Item' ),
  );

  /**
   * If Shipping is missing then Billing is same as Shipping.
   */
  function get_addresses_shipping_default() {
    return clone $this->addresses['billing'];
  }
}

class Example_Customer extends \Typed_Config\Data {

  /**
   * Set type to default to 'person' if it's not specified in JSON file.
   */
  var $type = 'person';
  var $organization;
  var $name;
  var $email;
  var $phone;

  /**
   * Specify that if a string is provided instead of an object, create
   * an instance and assign the value to the 'name' property.
   */
  protected $__if_string__ = 'name';

  /**
   * If the customer value or name is "Foo Bar <foo@bar.com>" then this splits name and email address into fields.
   */
  function pre_filter_name_value( $name ) {
    if ( preg_match( '#^(.+)<([^>]+)+>$#', trim( $name ), $match ) ) {
      $name = trim( $match[1] );
      $this->email = trim( $match[2] );
    }
    return $name;
  }
  /**
   * If the type specified is 'company' changes to 'organization'
   * If none of 'company', 'organization' or 'person' sets to 'person'
   */
  function initialize() {
    if ( 'company' == $this->type ) {
      $this->type = 'organization';
    } else if ( ! preg_match( '#^(person|organization)$#', $this->type ) ) {
      $this->type = 'person';
      $this->organization = null;
    }
  }
}

class Example_Address extends \Typed_Config\Data {
  var $street;
  var $city;
  var $country;
  var $post_code;
}

class Example_Item extends \Typed_Config\Data {
  var $sku;
  var $description;
  var $quantity;
}

/**
 * Removes all whitespace from a string, makes it easier to compare two strings for sameness.
 *
 * @param string $string
 *
 * @return string
 */
function strip_ws( $string ) {
  return preg_replace( '#\s+#', '', $string );
}

/**
 * Returns the string from a print_r() that shows what we expect this example to load and create.
 * @return string
 */
function get_expected() {
  $expected = <<<EXPECTED
Example_Order Object
(
    [id] => 12345
    [customer] => Example_Customer Object
        (
            [type] => person
            [organization] =>
            [name] => John Smith
            [email] => john.smith@acme.com
            [phone] =>
            [__id__] => customer
        )

    [addresses] => Array
        (
            [billing] => Example_Address Object
                (
                    [street] => 123 Main Street.
                    [city] => Anytown
                    [country] => USA
                    [post_code] => 12345-9876
                    [__id__] => billing
                )

            [shipping] => Example_Address Object
                (
                    [street] => 123 Main Street.
                    [city] => Anytown
                    [country] => USA
                    [post_code] => 12345-9876
                    [__id__] => shipping
                )

        )

    [items] => Array
        (
            [0] => Example_Item Object
                (
                    [sku] => abc123
                    [description] => Large Blue Widget
                    [quantity] => 1
                    [__id__] => items[0]
                )

            [1] => Example_Item Object
                (
                    [sku] => xyz987
                    [description] => Small Red Widget
                    [quantity] => 3
                    [__id__] => items[1]
                )

        )

    [__id__] => order
)
HOOKS AVAIALBLE(0) AND CALLED(1):
Array
(
    [order->customer->pre_filter_type_value] => 0
    [order->customer->pre_filter_organization_value] => 0
    [order->customer->pre_filter_name_value] => 1
    [order->customer->pre_filter_email_value] => 0
    [order->customer->pre_filter_phone_value] => 0
    [order->customer->pre_filter_values] => 0
    [order->customer->filter_type_value] => 0
    [order->customer->filter_organization_value] => 0
    [order->customer->filter_name_value] => 0
    [order->customer->filter_email_value] => 0
    [order->customer->filter_phone_value] => 0
    [order->customer->process_filtered_values] => 0
    [order->customer->post_filter_unused_values] => 0
    [order->customer->initialize] => 1
    [order->customer->set_type_default] => 0
    [order->customer->set_organization_default] => 0
    [order->customer->set_name_default] => 0
    [order->customer->set_email_default] => 0
    [order->customer->set_phone_default] => 0
    [order->customer->set_defaults] => 0
    [order->customer->finalize] => 0
    [order->addresses[billing]->pre_filter_street_value] => 0
    [order->addresses[billing]->pre_filter_city_value] => 0
    [order->addresses[billing]->pre_filter_country_value] => 0
    [order->addresses[billing]->pre_filter_post_code_value] => 0
    [order->addresses[billing]->pre_filter_values] => 0
    [order->addresses[billing]->filter_street_value] => 0
    [order->addresses[billing]->filter_city_value] => 0
    [order->addresses[billing]->filter_country_value] => 0
    [order->addresses[billing]->filter_post_code_value] => 0
    [order->addresses[billing]->process_filtered_values] => 0
    [order->addresses[billing]->post_filter_unused_values] => 0
    [order->addresses[billing]->initialize] => 0
    [order->addresses[billing]->set_street_default] => 0
    [order->addresses[billing]->set_city_default] => 0
    [order->addresses[billing]->set_country_default] => 0
    [order->addresses[billing]->set_post_code_default] => 0
    [order->addresses[billing]->set_defaults] => 0
    [order->addresses[billing]->finalize] => 0
    [order->addresses[shipping]->pre_filter_street_value] => 0
    [order->addresses[shipping]->pre_filter_city_value] => 0
    [order->addresses[shipping]->pre_filter_country_value] => 0
    [order->addresses[shipping]->pre_filter_post_code_value] => 0
    [order->addresses[shipping]->pre_filter_values] => 0
    [order->addresses[shipping]->filter_street_value] => 0
    [order->addresses[shipping]->filter_city_value] => 0
    [order->addresses[shipping]->filter_country_value] => 0
    [order->addresses[shipping]->filter_post_code_value] => 0
    [order->addresses[shipping]->process_filtered_values] => 0
    [order->addresses[shipping]->post_filter_unused_values] => 0
    [order->addresses[shipping]->initialize] => 0
    [order->addresses[shipping]->set_street_default] => 0
    [order->addresses[shipping]->set_city_default] => 0
    [order->addresses[shipping]->set_country_default] => 0
    [order->addresses[shipping]->set_post_code_default] => 0
    [order->addresses[shipping]->set_defaults] => 0
    [order->addresses[shipping]->finalize] => 0
    [order->addresses->pre_filter_id_value] => 0
    [order->addresses->pre_filter_customer_value] => 0
    [order->addresses->pre_filter_addresses_value] => 0
    [order->addresses->pre_filter_items_value] => 0
    [order->addresses->pre_filter_values] => 0
    [order->addresses->filter_id_value] => 0
    [order->addresses->filter_customer_value] => 0
    [order->addresses->filter_addresses_value] => 0
    [order->addresses->filter_items_value] => 0
    [order->addresses->process_filtered_values] => 0
    [order->addresses->post_filter_unused_values] => 0
    [order->addresses->initialize] => 0
    [order->addresses->set_id_default] => 0
    [order->addresses->get_customer_default] => 0
    [order->addresses->set_customer_default] => 0
    [order->addresses->set_addresses_default] => 0
    [order->addresses->set_items_default] => 0
    [order->addresses->get_addresses_billing_default] => 0
    [order->addresses->set_addresses_billing_default] => 0
    [order->addresses->get_addresses_shipping_default] => 1
    [order->addresses->set_addresses_shipping_default] => 0
    [order->addresses->set_addresses_defaults] => 0
    [order->addresses->get_items_0_default] => 0
    [order->addresses->set_items_0_default] => 0
    [order->addresses->set_items_1_default] => 0
    [order->addresses->set_items_defaults] => 0
    [order->addresses->set_defaults] => 0
    [order->addresses->finalize] => 0
    [order->items[0]->pre_filter_sku_value] => 0
    [order->items[0]->pre_filter_description_value] => 0
    [order->items[0]->pre_filter_quantity_value] => 0
    [order->items[0]->pre_filter_values] => 0
    [order->items[0]->filter_sku_value] => 0
    [order->items[0]->filter_description_value] => 0
    [order->items[0]->filter_quantity_value] => 0
    [order->items[0]->process_filtered_values] => 0
    [order->items[0]->post_filter_unused_values] => 0
    [order->items[0]->initialize] => 0
    [order->items[0]->set_sku_default] => 0
    [order->items[0]->set_description_default] => 0
    [order->items[0]->set_quantity_default] => 0
    [order->items[0]->set_defaults] => 0
    [order->items[0]->finalize] => 0
    [order->items[1]->pre_filter_sku_value] => 0
    [order->items[1]->pre_filter_description_value] => 0
    [order->items[1]->pre_filter_quantity_value] => 0
    [order->items[1]->pre_filter_values] => 0
    [order->items[1]->filter_sku_value] => 0
    [order->items[1]->filter_description_value] => 0
    [order->items[1]->filter_quantity_value] => 0
    [order->items[1]->process_filtered_values] => 0
    [order->items[1]->post_filter_unused_values] => 0
    [order->items[1]->initialize] => 0
    [order->items[1]->set_sku_default] => 0
    [order->items[1]->set_description_default] => 0
    [order->items[1]->set_quantity_default] => 0
    [order->items[1]->set_defaults] => 0
    [order->items[1]->finalize] => 0
    [order->items->pre_filter_id_value] => 0
    [order->items->pre_filter_customer_value] => 0
    [order->items->pre_filter_addresses_value] => 0
    [order->items->pre_filter_items_value] => 0
    [order->items->pre_filter_values] => 0
    [order->items->filter_id_value] => 0
    [order->items->filter_customer_value] => 0
    [order->items->filter_addresses_value] => 0
    [order->items->filter_items_value] => 0
    [order->items->process_filtered_values] => 0
    [order->items->post_filter_unused_values] => 0
    [order->items->initialize] => 0
    [order->items->set_id_default] => 0
    [order->items->get_customer_default] => 0
    [order->items->set_customer_default] => 0
    [order->items->set_addresses_default] => 0
    [order->items->set_items_default] => 0
    [order->items->get_addresses_billing_default] => 0
    [order->items->set_addresses_billing_default] => 0
    [order->items->get_addresses_shipping_default] => 1
    [order->items->set_addresses_shipping_default] => 0
    [order->items->set_addresses_defaults] => 0
    [order->items->get_items_0_default] => 0
    [order->items->set_items_0_default] => 0
    [order->items->set_items_1_default] => 0
    [order->items->set_items_defaults] => 0
    [order->items->set_defaults] => 0
    [order->items->finalize] => 0
    [order->pre_filter_id_value] => 0
    [order->pre_filter_customer_value] => 0
    [order->pre_filter_addresses_value] => 0
    [order->pre_filter_items_value] => 0
    [order->pre_filter_values] => 0
    [order->filter_id_value] => 0
    [order->filter_customer_value] => 0
    [order->filter_addresses_value] => 0
    [order->filter_items_value] => 0
    [order->process_filtered_values] => 0
    [order->post_filter_unused_values] => 0
    [order->initialize] => 0
    [order->set_id_default] => 0
    [order->get_customer_default] => 0
    [order->set_customer_default] => 0
    [order->set_addresses_default] => 0
    [order->set_items_default] => 0
    [order->get_addresses_billing_default] => 0
    [order->set_addresses_billing_default] => 0
    [order->get_addresses_shipping_default] => 1
    [order->set_addresses_shipping_default] => 0
    [order->set_addresses_defaults] => 0
    [order->get_items_0_default] => 0
    [order->set_items_0_default] => 0
    [order->set_items_1_default] => 0
    [order->set_items_defaults] => 0
    [order->set_defaults] => 0
    [order->finalize] => 0
)
EXPECTED;
  return $expected;
}

main();

