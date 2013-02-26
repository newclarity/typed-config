<?php
/*
 * Example showing how to use Typed Config; can be run standalone from the PHP command line
 *
 * This example shows how to use Typed Config to model an expected class structure in JSON in first-class
 * PHP classes complete with methods to set defaults and validate values. This allows you to take any externally
 * generated and untrusted source of JSON data and cleanse it to be pristine and in the format for your
 * program to use reliabably and easiy.
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
   * @var Typed_Config $order
   */
  $order = Typed_Config_Loader::load( 'order', 'Example_Order', get_json() );

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
    echo "Results were as expected!\n";
  else
    echo "Results NOT as expected. Hmm.";

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
EXPECTED;
  return $expected;
}

/**
 * This class defines both the "schema" to load the JSON as well as it's for the
 */
class Example_Order extends Typed_Config {

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
  function get_addresses_shipping_default( $value ) {
    return $this->addresses['billing'];
  }
}

class Example_Customer extends Typed_Config {

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
  function filter_name_value( $name ) {
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
  function get_type_default( $value ) {
    if ( 'company' == $value ) {
      $value = 'organization';
    } else if ( ! preg_match( '#^(person|organization)$#', $value ) ) {
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

main();

