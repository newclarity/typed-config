<?php

abstract class Typed_Config {

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
   * @var bool|Typed_Config
   */
  protected $__root__;

  /**
   * @var array
   */
  protected $__unused__;

  /**
   * @var object
   */
  protected $__logger__;

  /**
   * Function to allow the loader to set the __filepath__ property
   *
   * @param TCLP_Logger
   */
  function set_logger( $logger ) {
    $this->__logger__ = $logger;
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
   * @param string $id
   * @param bool|object|mixed $value Either a JSON object or a scalar
   * @param bool|Typed_Config $root
   * @param WPPM_Package
   */
  function instantiate( $id, $value, $root = false ) {
    if ( ! $root )
      $root = $this;
    if ( is_string( $root ) ) {
      $this->__filepath__ = $root;
      $root               = $this;
    }
    $this->__id__   = $id;
    $this->__root__ = $root;

    if ( ! is_null( $value ) ) {
      if ( is_object( $value ) )
        $value = (array) $value;

      if ( method_exists( $this, 'filter_loaded_values' ) )
        $value = $this->filter_loaded_values( $value, $id );

      if ( is_string( $value ) && ! is_null( $this->__if_string__ ) ) {
        $this->{$this->__if_string__} = $value;
        if ( method_exists( $this, $method_name = "filter_{$this->__if_string__}_value" ) )
          $this->{$this->__if_string__} = $this->$method_name( $this->{$this->__if_string__}, $value );
      }

      if ( is_array( $value ) ) {
        $array = array_merge( get_object_vars( $this ), $value );
        foreach ( $array as $property_name => $property_value ) {
          if ( $this->_is_meta_property( $property_name ) ) {
            unset( $array[$property_name] );
            continue;
          }
          if ( property_exists( $this, $property_name ) ) {
            if ( $this->_schema_says_instantiate( $property_name ) ) {
              $this->$property_name = $this->_instantiate( $property_name, $property_value );
            } else if ( $this->_schema_says_instantiate_array_of_objects( $property_name ) ) {
              $this->$property_name = $this->_instantiate_array_of_objects( $property_name, $property_value );
            } else {
              $this->$property_name = $property_value;
            }
            unset( $array[$property_name] );
          }
          if ( method_exists( $this, $method_name = "filter_{$property_name}_value" ) )
            $this->$property_name = $this->$method_name( $this->$property_name, $property_value );
        }

        if ( count( $array ) )
          $this->__unused__ = $array;

        if ( method_exists( $this, "monitor_new_values" ) )
          $this->monitor_new_values( $this, $value );

      }
    }

    if ( method_exists( $this, 'initialize' ) )
      $this->initialize( $value, $id );

    if ( $this === $this->__root__ ) {
      // @TODO: Make it so this does not require calling parent::finalize() somehow.
      $this->_set_defaults( $this, $id );
      $this->_finalize( $this, $id );
    }
  }

  /**
   * @param object|array $item
   * @param string $property_name
   */
  private function _finalize( $item, $property_name ) {
    $remaining = $properties = get_object_vars( $item );
    $times = 0;
    while ( count( $remaining ) ) {
      if ( $times++ == 3 ) {
        $message =<<<MESSAGE
Finalize ran 3 times and still has properties remaining to be finalized. This is
almost certainly a programming error. Please check your Typed_Config classes to
ensure you are eventually returning 'true' from your finalize() methods.
The remaining property(s) where %s for %s.
MESSAGE;
        $this->__logger__->error( sprintf( $message, implode( ', ', $remaining ), $property_name ) );
      }
      foreach( $properties as $name => $value ) {
        if ( $this->_is_meta_property( $name ) ) {
          unset( $remaining[$name] );
          continue;
        }
        if ( is_object( $value ) ) {
          if ( ! method_exists( $value, 'finalize' ) || $value->finalize() ) {
            unset( $remaining[$name] );
          }
        } else {
          if ( is_array( $value ) ) {
            foreach( $value as $sub_name => $sub_value )
              if ( is_object( $sub_value ) )
                $this->_finalize( $sub_value, $sub_name );
          }
          unset( $remaining[$name] );
        }
      }
      if ( 0 == count( $remaining ) && method_exists( $item, 'finalize' ) ) {
        if ( ! $item->finalize() ) {
          $message =<<<MESSAGE
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
   */
  function strip_meta( $except = false ) {
    foreach( get_object_vars( $this ) as $property_name => $property_value ) {
      if ( $this->_is_meta_property( $property_name ) ) {
        if ( ! $except || ! preg_match( "#^({$except})$#", $property_name ) ) {
          unset( $this->$property_name );
        }
      } else if ( is_array( $property_value ) ) {
        foreach( $property_value as $sub_name => $sub_value )
          if ( method_exists( $sub_value, 'strip_meta' ) ) {
            /**
             * @var Typed_Config $sub_value
             */
            $sub_value->strip_meta( $except );
          }
      } else if ( method_exists( $property_value, 'strip_meta' ) ) {
        /**
         * @var Typed_Config $property_value
         */
        $property_value->strip_meta( $except );
      }
    }
  }

  /**
   * @param Typed_Config|array $item
   * @param bool|string $property_name
   * @param int $depth
   */
  private function _set_defaults( $item, $property_name = false, $depth = 0 ) {
    $delegates = array();
    $omit = array();
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
          $omit[$name] = true;
          continue;
        }
        /**
         * If the $item is an Typed_Config we can test it for each property to see
         * if there is a set_property_defaults() method we can run.
         */
        if ( method_exists( $item, $method_name = "get_{$name}_default" ) ) {
          $item->$name = $this->_get_property_default( $item, $method_name, $name, $value );
        }
      }
      /**
       * After we run the initial defaults methods, if any, let's
       * set if there is a general set_defaults method we can run.
       */
      if ( method_exists( $item, $method_name = "set_defaults" ) ) {
        call_user_func( array( $item, $method_name ) );
      }
    }
    /**
     * Now for (almost) all array elements or object properties
     */
    foreach ( $array as $name => $value ) {
      if ( isset( $omit[$name] ) )
        continue;

      /**
       * if there is a get_foo_bar_default() for the array property $foo element 'bar'
       */
      if ( method_exists( $this, $method_name = "get_{$property_name}_{$name}_default" ) ) {
        $this->{$property_name}[$name] = $this->_get_property_default( $this, $method_name, $name, $value, $this->$property_name );
      }
      /**
       * Now gather up all the object and array children of this array/object.
       */
      if ( is_object( $value ) || is_array( $value ) ) {
        $delegates[$name] = $value;
      }
    }
    /**
     */
    if ( is_array( $item ) ) {
      /**
       * if there is a get_foo_defaults() for the array property $foo
       */
      if ( method_exists( $this, $method_name = "get_{$property_name}_defaults" ) ) {
        $this->$property_name = call_user_func( array( $this, $method_name ), $this->$property_name, $property_name );
      }
    }

    if ( $depth == 100 ) {
      $this->__logger__->error( 'Reached recursive depth of 100. Need to fix the code to handle resurive data elements.' );
    }
    /**
     * Finally, recursively call this method on the children
     * @TODO: Fix recursively defined elements if this is every an issue.
     */
    foreach( $delegates as $name => $value ) {
      if ( ! isset( $omit[$name] ) )
        $this->_set_defaults($value, $name);
    }
  }

  /**
   * @param object $object
   * @param string $method_name
   * @param string $name
   * @param string $value
   *
   * @return mixed|Typed_Config
   */
  private function _get_property_default( $object, $method_name, $name, $value ) {
    $default = call_user_func( array( $object, $method_name ), $value );
    if ( is_a( $value, 'TCLP_Needs_Default' ) ) {
      $default = $this->_instantiate( $name, $default, $value->class_name );
    }
    return $default;
  }

  /**
   * @param string $property_name
   *
   * @return bool
   */
  private function _schema_says_instantiate( $property_name ) {
    $property_type = isset($this->__schema__[$property_name]) ? $this->__schema__[$property_name] : false;

    return $property_type && is_string( $property_type ) && class_exists( $property_type );
  }

  /**
   * @param string $property_name
   *
   * @return bool
   */
  private function _schema_says_instantiate_array_of_objects( $property_name ) {
    return isset($this->__schema__[$property_name]) && is_array( $this->__schema__[$property_name] );
  }

  /**
   * @param string $property_name
   *
   * @return bool
   */
  private function _schema_says_array( $property_name ) {
    return is_array( $this->__schema__[$property_name] );
  }

  /**
   * @param string $name
   * @param mixed $value
   * @param bool $class_name
   *
   * @return Typed_Config
   */
  private function _instantiate( $name, $value, $class_name = false ) {
    if ( ! $class_name ) {
      $class_name = $this->__schema__[$name];
    }
    /**
     * @var Typed_Config $object
     */
    $object = new $class_name();
    $object->instantiate( $name, $value, $this->__root__ );

    return $object;
  }

  /**
   * Instantiates an array of objects based on the schema
   *
   * @param string $property_name
   * @param mixed $property_value
   *
   * @return array
   */
  private function _instantiate_array_of_objects( $property_name, $property_value ) {
    $schema_array     = $this->__schema__[$property_name];
    $property_value   = (array) $property_value;
    if ( 1 == count( $schema_array ) && 0 == key( $schema_array ) && is_array( $property_value ) ) {
      /**
       * This is a simple numerically indexed array
       */
      foreach ( $property_value as $index => $value ) {
        $property_value[$index] = $this->_instantiate( "{$property_name}[{$index}]", $value, $schema_array[0] );
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
          if ( ! isset($property_value[$name]) ) {
            $array_of_objects[$name] = new TCLP_Needs_Default( $name, $class_name );
          } else {
            $array_of_objects[$name] = $this->_instantiate( $name, $property_value[$name], $class_name );
          }
        } else if ( is_array( $value ) ) {
          $class_name  = $value[0];
          $sub_objects = array();
          foreach ( (array) $property_value[$name] as $sub_name => $sub_value ) {
            $sub_objects[$sub_name] = $this->_instantiate( $sub_name, $sub_value, $class_name );
          }
          $array_of_objects[$name] = $sub_objects;
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
  private function _is_meta_property( $property_name ) {
    return preg_match( '#^__[a-zA-Z_0-9]+__$#', $property_name );
  }
}
