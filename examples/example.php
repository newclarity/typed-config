<?php
/*
 * This example shows how to use Typed Config to model an expected class structure in JSON in first-class
 * PHP classes complete with methods to set defaults and validate values. This allows you to take any externally
 * generated and untrusted source of JSON data and cleanse it to be pristine and in the format for your
 * program to use reliabably and easiy.
 *
 * Author: Mike Schinkel <mike@newclarity.net>
 * License: GPLv2
 *
 */
include( dirname( __DIR__ ) . '/typed-config.php' );

function main() {
  /**
   * Loads the JSON in $json_filepath into the root 'order' object class 'Example_Order'
   * @var Typed_Config $order
   */
  $order = Typed_Config_Loader::load( 'order', 'Example_Order', get_json() );
  $order->strip_meta( '__id__' );
  ob_start();
  print_r( $order );
  $output = ob_get_clean();
  $expected = get_expected();
  echo $output;
  echo strip_ws( $output ) == strip_ws( $expected ) ? "Results were as expected!\n" : "Results NOT as expected. Hmm.";

}
function strip_ws( $string ) {
  return preg_replace( '#\s+#', '', $string );
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
      "city" : "Anytown, USA",
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
                    [city] => Anytown, USA
                    [country] => USA
                    [post_code] => 12345-9876
                    [__id__] => billing
                )

            [shipping] => Example_Address Object
                (
                    [street] => 123 Main Street.
                    [city] => Anytown, USA
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
EXPECTED;
  return $expected;
}

/**
 * This class defines both the "schema" to load the JSON as well as it's for the
 */
class Example_Order extends Typed_Config {
  protected $__schema__ = array(
    'items' => array( 'Example_Item' ),
    'customer' => 'Example_Customer',
    'addresses' => array(
      'billing' => 'Example_Address',
      'shipping' => 'Example_Address',
    ),
  );

  var $id;
  var $customer;
  var $addresses = array();
  var $items = array();
  function get_addresses_shipping_default( $value ) {
    /**
     * If Shipping is missing then Billing is same as Shipping.
     */
    return $this->addresses['billing'];
  }
}

class Example_Customer extends Typed_Config {
  protected $__if_string__ = 'name';
  var $type = 'person';
  var $organization;
  var $name;
  var $email;
  var $phone;
  function filter_name_value( $name ) {
    if ( preg_match( '#^(.+)<([^>]+)+>$#', trim( $name ), $match ) ) {
      $name = trim( $match[1] );
      $this->email = trim( $match[2] );
    }
    return $name;
  }
  function get_type_default( $value ) {
    if ( ! preg_match( '#^(person|organization)$#', $value ) ) {
      $value = 'person';
    }
    return $value;
  }
}


class Example_Address extends Typed_Config {
  var $street;
  var $city;
  var $country;
  var $post_code;
}

class Example_Item extends Typed_Config {
  var $sku;
  var $description;
  var $quantity;
}

main();

