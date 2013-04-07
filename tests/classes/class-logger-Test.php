<?php

require strstr( __DIR__, '/tests', true ) . '/classes/class-logger.php';

class Typed_Config_Logger_Test_Case extends PHPUnit_Framework_TestCase {
  var $instance;

  function setup() {
    $this->instance = new Typed_Config_Logger();
  }

  function test_has_notice_method() {
    $this->assertTrue( method_exists( $this->instance, 'notice' ), 'There is no notice() method.' );
  }

}
