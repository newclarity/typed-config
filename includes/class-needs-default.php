<?php

namespace Typed_Config;

/**
 * Class Needs_Default
 *
 * @package Typed_Config
 */
class Needs_Default {

	/**
	 * @var string
	 */
	var $property_name;

	/**
	 * @var string
	 */
	var $class_name;

	/**
	 * @param string $property_name
	 * @param string $class_name
	 */
	function __construct( $property_name, $class_name ) {
		$this->property_name = $property_name;
		$this->class_name    = $class_name;
	}
}
