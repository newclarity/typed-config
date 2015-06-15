<?php

namespace Typed_Config;
/**
 * Class \Typed_Config\Data
 */
abstract class Data {

	/**
	 * @var string
	 */
	protected $__schema__;

	/**
	 * @var string
	 */
	protected $__if_string__;

	/**
	 * @var string
	 */
	var $__id__;

	/**
	 * @var string
	 */
	protected $__filepath__;

	/**
	 * @var bool|Data
	 */
	protected $__root__;

	/**
	 * @var bool|Data
	 */
	protected $__parent__;

	/**
	 * @var array
	 */
	protected $__unused__ = array();

	/**
	 * @var bool|object
	 */
	protected $__logger__;

	/**
	 * @var array
	 */
	protected $__hooks__ = array();

	/**
	 * @var array
	 */
	protected $__after_hooks__ = array();

	protected $__after_hook_names__ = array();

	function root() {
	 	return $this->__parent__;
	}

	function parent() {
	 	return $this->__parent__;
	}

	/**
	 * Function to allow the loader to set the __filepath__ property
	 *
	 * @param Typed_Config_Logger
	 */
	function set_logger( $logger ) {
		$this->__logger__ = $logger;
	}

	/**
	 * Function to allow the loader to set the __filepath__ property
	 *
	 * @return \Typed_Config\Logger
	 */
	function get_logger() {
		return $this->__logger__;
	}

	/**
	 * Function to allow the loader to set the __filepath__ property
	 *
	 * @param $filepath
	 */
	function set_filepath( $filepath ) {
		$this->__filepath__ = $filepath;
	}

	/**
	 * Function to retrieve the value of the __filepath__ property
	 */
	function get_filepath() {
		return $this->__filepath__;
	}

	protected function _try_hook( $object, $method_name, $value = null ) {

		if ( $object->__hooks__[ $method_name ] = is_callable( $callable = array( $object, $method_name ) ) ) {

			if ( is_null( $args = array_slice( func_get_args(), 2 ) ) ) {

				$value = call_user_func( $callable );

			} else {

				$value = call_user_func_array( $callable, $args );

			}

		}
		if ( isset( $args[ 1 ] ) && property_exists( $object, $args[ 1 ] ) ) {

			$property_name = $args[ 1 ];
			$object->$property_name = $value;

		}

		if ( preg_match( '#^set_#', $method_name ) ) {

			$this->__after_hooks__[] = array( $object, $after_hook = "{$method_name}_after" );
			$this->__after_hook_names__[] =  get_class( $object ) . "::{$after_hook}";
		}

		return $value;

	}

	/**
	 * @return mixed
	 */
	protected function _try_after_hooks() {
		for ( $i = count( $this->__after_hooks__ ) - 1; 0 <= $i; $i-- ) {
			list( $data, $after_method ) = $this->__after_hooks__[ $i ];
			if ( is_callable( $callable = array( $data, $after_method ) ) ) {
				call_user_func( $callable );
			}
		}
	}


	/**
	 * Returns an array for all the properties that are not meta properties for an instance
	 *
	 * @param $object_or_array
	 *
	 * @return array
	 */
	function _get_public_properties( $object_or_array ) {
		$array = array();
		if ( is_object( $object_or_array ) ) {
			foreach ( get_object_vars( $object_or_array ) as $name => $value ) {
				if ( ! $this->_is_meta_property( $name ) ) {
					$array[ $name ] = $object_or_array->$name;
				}
			}
		} else if ( ! is_null( $object_or_array ) ) {
			foreach ( array_keys( $object_or_array ) as $name ) {
				if ( ! $this->_is_meta_property( $name ) ) {
					$array[ $name ] = $object_or_array[ $name ];
				}
			}
		}

		return $array;
	}

	/**
	 * @param string $id
	 * @param bool|object|mixed $value Either a JSON object or a scalar
	 * @param bool|Data $root
	 * @param WPPM_Package
	 */
	function instantiate( $id, $value, $root = false ) {
		if ( ! $root ) {
			$root = $this;
		}
		if ( is_string( $root ) ) {
			$this->__filepath__ = $root;
			$root               = $this;
		}
		$this->__id__   = $id;
		$this->__root__ = $root;

		if ( is_string( $value ) && ! is_null( $this->__if_string__ ) ) {
			$this->{$this->__if_string__} = $value;
			$value                        = $this->_get_public_properties( $this );
		}

		if ( is_object( $this ) || is_object( $value ) || is_array( $value ) ) {

			$array = array_merge( $this->_get_public_properties( $this ), $this->_get_public_properties( $value ) );

			foreach ( $array as $property_name => $property_value ) {
				/**
				 * Go ahead and preset so that the passed values are available in the pre_filters.
				 */
				$this->$property_name = $property_value;
			}

			foreach ( $array as $property_name => $property_value ) {
				$array[ $property_name ] = $this->_try_hook( $this, "pre_filter_{$property_name}_value", $property_value, $property_name, $id );
			}
			$array = array_merge( $this->_get_public_properties( $this ), array_filter( (array) $array ) );
			$array = $this->_try_hook( $this, "pre_filter_values", $array, $id );
			foreach ( $array as $property_name => $property_value ) {
				if ( ! property_exists( $this, $property_name ) ) {
					$this->__unused__[ $property_name ] = $property_value;
				} else {
					if ( $this->_schema_says_instantiate( $property_name ) ) {
						$this->$property_name = $this->_instantiate( $property_name, $property_value );

					} else if ( $this->_schema_says_instantiate_array_of_objects( $property_name ) ) {
						$this->$property_name = $this->_instantiate_array_of_objects( $property_name, $property_value );
					} else {
						$this->$property_name = $property_value;
					}
					unset( $array[ $property_name ] );
					$this->$property_name = $this->_try_hook( $this, "filter_{$property_name}_value", $this->$property_name, $property_value );
					$this->_try_hook( $this, "set_{$property_name}_default", $this->$property_name, $property_value );
				}
			}

			$this->_try_hook( $this, "process_filtered_values", $id );

			if ( count( $array ) ) {
				$this->__unused__ = $array;
			}

			$this->__unused__ = $this->_try_hook( $this, "post_filter_unused_values", $this->__unused__, $id );

		}

		$this->_try_hook( $this, 'initialize', $value );

		if ( $this === $this->__root__ ) {
			// @TODO: Make it so this does not require calling parent::finalize() somehow.
			//$this->_set_defaults( $this, $id );
			$this->_finalize( $this );
			//$this->_try_after_hooks();
		}
	}

	/**
	 * @param object|array $item
	 * @param string $property_name
	 */
	protected function _finalize( $item, $property_name = '<root>' ) {
		$remaining = $properties = get_object_vars( $item );
		$times     = 0;
		while ( count( $remaining ) ) {
			if ( $times ++ == 3 ) {
				$message = <<<MESSAGE
Finalize ran 3 times and still has properties remaining to be finalized. This is
almost certainly a programming error. Please check your Typed_Config classes to
ensure you are eventually returning 'true' from your finalize() methods.
The remaining property(s) where %s for %s.
MESSAGE;
				$this->__logger__->error( sprintf( $message, implode( ', ', $remaining ), $property_name ) );
			}
			foreach ( $properties as $name => $value ) {
				if ( $this->_is_meta_property( $name ) ) {
					unset( $remaining[ $name ] );
					continue;
				}
				if ( is_object( $value ) ) {
					$this->_try_hook( $value, 'finalize' );
					if ( ! method_exists( $value, 'finalize' ) ) {
						unset( $remaining[ $name ] );
					}
				} else {
					if ( is_array( $value ) ) {
						foreach ( $value as $sub_name => $sub_value ) {
							if ( is_object( $sub_value ) ) {
								$this->_finalize( $sub_value, $sub_name );
							}
						}
					}
					unset( $remaining[ $name ] );
				}
			}
			if ( 0 == count( $remaining ) ) {
				$finalize = $this->_try_hook( $item, 'finalize' );
				if ( false === $finalize ) {
					$message = <<<MESSAGE
%s->finalize() should never return 'false' after the finalizers for all
contained properties and their properties have been resolved so it's almost certainly
a programming error. Please check your Typed_Config classes to ensure you are returning
'true' from the root object's finalize() method.
MESSAGE;
					$this->__logger__->error( sprintf( $message, $property_name ) );
				}
				break;
			}
		}
	}

	/**
	 * Strips all the meta values off, mostly so a print_r() or var_dump() is easier to read.
	 *
	 * @param bool $except
	 */
	function strip_meta( $except = false ) {
		foreach ( get_object_vars( $this ) as $property_name => $property_value ) {
			if ( $this->_is_meta_property( $property_name ) ) {
				if ( ! $except || ! preg_match( "#^({$except})$#", $property_name ) ) {
					unset( $this->$property_name );
				}
			} else if ( is_array( $property_value ) ) {
				foreach ( $property_value as $sub_name => $sub_value ) {
					if ( is_object( $sub_value ) ) {
						$this->_try_hook( $sub_value, 'strip_meta', $except );
					}
				}
			} else {
				if ( is_object( $property_value ) ) {
					$this->_try_hook( $property_value, 'strip_meta', $except );
				}
			}
		}
	}

	/**
	 * @param bool|array|Data $item
	 * @param bool|array|Data $parent
	 *
	 * @return array
	 */
	function get_hooks( $item = false, $parent = false ) {
		$hooks = array();
		if ( ! $item && ! is_array( $item ) ) {
			$item = $this;
		}
		if ( ! $parent ) {
			$parent = $item->__id__;
		}
		if ( is_array( $item ) ) {
			foreach ( $item as $name => $value ) {
				if ( isset( $value->__hooks__ ) ) {
					/**
					 * @var Data $value
					 */
					$hooks = array_merge( $hooks, $value->get_hooks( $value, "{$parent}[{$name}]" ) );
				}
			}
		} else {
			foreach ( get_object_vars( $item ) as $property_name => $property_value ) {
				if ( $this->_is_meta_property( $property_name ) ) {
					continue;
				} else if ( isset( $property_value->__hooks__ ) ) {
					/**
					 * @var Data $property_value
					 */
					$hooks = array_merge( $hooks, $property_value->get_hooks( $property_value, "{$parent}->{$property_name}" ) );
				} else if ( is_array( $property_value ) ) {
					$hooks = array_merge( $hooks, $item->get_hooks( $property_value, "{$parent}->{$property_name}" ) );
				}
			}
		}
		if ( isset( $this->__hooks__ ) ) {
			foreach ( $this->__hooks__ as $hook_name => $hook_ran ) {
				$hooks["{$parent}->{$hook_name}"] = $hook_ran ? 1 : 0;
			}
		}

		return $hooks;
	}

	/**
	 * @param Data|array $item
	 * @param bool|string $property_name
	 * @param int $depth
	 * @param bool|Data $parent
	 */
	protected function _set_defaults( $item, $property_name = false, $depth = 0, $parent = false ) {
		if ( ! $parent ) {
			$parent = $this;
		}
		$delegates = array();
		$omit      = array();
		if ( is_array( $item ) ) {
			/**
			 * If the $item is an array we can't run any defaults functions on it
			 * but we can run _setdefaults on it's child objects or arrays. So grab it.
			 */
			$array = $item;
		} else if ( is_object( $item ) ) {
			$array = get_object_vars( $item );
			foreach ( $array as $name => $value ) {
				if ( $this->_is_meta_property( $name ) ) {
					/**
					 * Be sure to omit any properties like __id__ and __root__.
					 */
					$omit[ $name ] = true;
					continue;
				}
				/**
				 * If the property needs a default and there is a "get_{$property_name}_default"
				 * the $item is an Typed_Config we can test it for each property to see
				 * if there is a set_property_defaults() method we can run.
				 */
				$this->_set_default_properties( $item, $name, $value );
			}
		}
		/**
		 * Now for (almost) all array elements or object properties
		 */
		foreach ( $array as $name => $value ) {
			if ( isset( $omit[ $name ] ) ) {
				continue;
			}

			/**
			 * if there is a set_foo_bar_default() for the array property $foo element 'bar'
			 */
			if ( is_array( $item ) ) {
				$value = $this->_set_default_properties( $parent, $name, $value, $property_name );
			}
			/**
			 * Now gather up all the object and array children of this array/object.
			 */
			if ( is_object( $value ) || is_array( $value ) ) {
				$delegates[ $name ] = $value;
			}
		}
		/**
		 */
		if ( is_array( $item ) ) {
			/**
			 * if there is a set_foo_defaults() for the array property $foo
			 */
			$this->_try_hook( $parent, "set_{$property_name}_defaults", $parent->$property_name );
		}

		if ( $depth == 100 ) {
			$this->__logger__->error( 'Reached recursive depth of 100. Need to fix the code to handle resurive data elements.' );
		}
		/**
		 * Finally, recursively call this method on the children
		 *
		 * @TODO: Fix recursively defined elements if this is ever an issue.
		 */
		foreach ( $delegates as $name => $value ) {
			if ( ! isset( $omit[ $name ] ) ) {
				$this->_set_defaults( $value, $name, $depth ++, is_array( $item ) ? $parent : $item );
			}
		}
		/**
		 * After we run the initial defaults methods, if any, let's
		 * set if there is a general set_defaults method we can run.
		 */
		if ( is_object( $item ) ) {
			$this->_try_hook( $item, 'set_defaults' );
		}

	}

	/**
	 * @param object $data
	 * @param string $name
	 * @param string $value
	 * @param bool|string $parent_name
	 *
	 * @return mixed|\Typed_Config\Data
	 */
	protected function _set_default_properties( $data, $name, $value, $parent_name = false ) {
		$selector = $parent_name ? "{$parent_name}_{$name}" : $name;
		if ( $value instanceof Needs_Default ) {
			$new_value = $this->_try_hook( $data, $method_name = "get_{$selector}_default" );
			if ( method_exists( $data, $method_name ) ) {
				if ( isset( $new_value->__id__ ) ) {
					$new_value->__id__ = $name;
				}
				$value = $this->_instantiate( $name, $new_value, $value->class_name );
				if ( $parent_name ) {
					$data->{$parent_name}[ $name ] = $value;
				} else {
					$data->$name = $value;
				}
			} else {
				$message = 'No %s() method available for property %s' . ( $parent_name ? "['%s']" : 'name %s.' );
				$this->get_logger()->error( sprintf( $message, $method_name, $parent_name, $name ) );
			}
			$data->__hooks__["set_{$selector}_default"] = false;
		} else if ( empty( $value ) ) {
			if ( $data->_schema_says_object( $name, $parent_name ) ) {
				$data->__hooks__["get_{$selector}_default"] = false;
			}
			$this->_try_hook( $data, "set_{$selector}_default" );
		} else {
			if ( $data->_schema_says_object( $name, $parent_name ) ) {
				$data->__hooks__["get_{$selector}_default"] = false;
			}
			$data->__hooks__["set_{$selector}_default"] = false;
		}

		return $value;
	}

	/**
	 * @param string $property_name
	 * @param bool|string $parent_name
	 *
	 * @return bool
	 */
	protected function _schema_says_object( $property_name, $parent_name = false ) {
		if ( $parent_name ) {
			$is_object = isset( $this->__schema__[ $parent_name ][ $property_name ] ) && is_string( $this->__schema__[ $parent_name ][ $property_name ] );
		} else {
			$is_object = isset( $this->__schema__[ $property_name ] ) && is_string( $this->__schema__[ $property_name ] );
		}

		return $is_object;
	}

	/**
	 * @param string $property_name
	 *
	 * @return bool
	 */
	protected function _schema_says_instantiate( $property_name ) {
		$property_type = isset( $this->__schema__[ $property_name ] ) ? $this->__schema__[ $property_name ] : false;

		return $property_type && is_string( $property_type ) && class_exists( $property_type );
	}

	/**
	 * @param string $property_name
	 *
	 * @return bool
	 */
	protected function _schema_says_instantiate_array_of_objects( $property_name ) {
		return isset( $this->__schema__[ $property_name ] ) && is_array( $this->__schema__[ $property_name ] );
	}

	/**
	 * @param string $property_name
	 *
	 * @return bool
	 */
	protected function _schema_says_array( $property_name ) {
		return is_array( $this->__schema__[ $property_name ] );
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @param bool $class_name
	 *
	 * @return \Typed_Config\Data
	 */
	protected function _instantiate( $name, $value, $class_name = false ) {

		if ( ! $class_name ) {
			$class_name = $this->__schema__[ $name ];
		}
		/**
		 * @var \Typed_Config\Data $data
		 */
		$data = new $class_name();
		$data->__parent__ = $this;
		$data->instantiate( $name, $value, $this->__root__ );

		return $data;
	}

	/**
	 * Instantiates an array of objects based on the schema
	 *
	 * @param string $property_name
	 * @param mixed $property_value
	 *
	 * @return array
	 */
	protected function _instantiate_array_of_objects( $property_name, $property_value ) {
		$schema_array   = $this->__schema__[ $property_name ];
		$property_value = (array) $property_value;
		if ( 1 == count( $schema_array ) && 0 == key( $schema_array ) && is_array( $property_value ) ) {
			/**
			 * This is a simple numerically indexed array
			 */
			foreach ( $property_value as $index => $value ) {
				$object = $this->_instantiate( "{$property_name}[{$index}]", $value, $schema_array[0] );
				$property_value[ $index ] = $object;
			}
			$array_of_objects = $property_value;
		} else {
			/**
			 * This is a keyed array
			 */
			$array_of_objects = array();
			foreach ( (array) $schema_array as $name => $value ) {
				if ( is_string( $value ) ) {
					$class_name = $value;
					if ( ! isset( $property_value[ $name ] ) ) {
						$array_of_objects[ $name ] = new Needs_Default( $name, $class_name );
					} else {
						$array_of_objects[ $name ] = $this->_instantiate( $name, $property_value[ $name ], $class_name );
					}
				} else if ( is_array( $value ) ) {
					$class_name  = $value[0];
					$sub_objects = array();
					foreach ( (array) $property_value[ $name ] as $sub_name => $sub_value ) {
						$sub_objects[ $sub_name ] = $this->_instantiate( $sub_name, $sub_value, $class_name );
					}
					$array_of_objects[ $name ] = $sub_objects;
				}
			}
		}

		return $array_of_objects;
	}

	/**
	 * Test to see if an object property name is a meta property.
	 *
	 * @param string $property_name
	 *
	 * @return int
	 */
	protected function _is_meta_property( $property_name ) {
		return preg_match( '#^__[a-zA-Z_0-9]+__$#', $property_name );
	}

	function __get( $property_name ) {
		$value = $this->_try_hook( $this, $method_name = "get_{$property_name}_value" );
		if ( is_null( $value ) ) {
			$value = $this->_try_hook( $this, 'get_value', $property_name, $value );
		} else {
			$this->__hooks__['get_value'] = false;
		}
		if ( is_null( $value ) ) {
			$backtrace  = debug_backtrace();
			$class_name = get_class( $this );
			$message    = "Undefined property: {$class_name}::\${$property_name} in {$backtrace[1]['file']} on line {$backtrace[1]['line']} reported";
			trigger_error( $message, E_USER_ERROR );
		}

		return $value;
	}

	function properties() {

		$properties = array();
		foreach( get_object_vars( $this ) as $property => $value ) {
			if ( preg_match( '#^__(.*)__$#', $property ) ) {
				continue;
			}
			$properties[ $property ] = $value;
		}

		return $properties;

	}

}

