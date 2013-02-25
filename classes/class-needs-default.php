<?php

class TCLP_Needs_Default {
  var $property_name;
  var $class_name;
  function __construct( $property_name, $class_name ) {
    $this->property_name = $property_name;
    $this->class_name = $class_name;
  }
}
