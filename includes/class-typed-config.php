<?php

abstract class Typed_Config {

  /**
   * @var string
   */
  protected $__schema__;

  /**
   * @var string
   */
  protected $__id__;

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
    /**
     * Derives the "schema" from the default values set in the properties.
     * This call clears the schema from the properties so that properties default to null or array().
     */
    //$this->__schema__ = $this->_get_and_clear_schema( $this );
    if ( ! is_null( $value ) ) {
      if ( is_object( $value ) )
        $value = (array) $value;

      if ( method_exists( $this, 'filter_loaded_values' ) )
        $value = $this->filter_loaded_values( $value, $id );

      if ( is_array( $value ) ) {
        $value = array_merge( get_object_vars( $this ), $value );
        foreach ( $value as $property_name => $property_value ) {
          if ( property_exists( $this, $property_name ) ) {
            if ( $this->_schema_says_instantiate( $property_name ) ) {
              $this->$property_name = $this->_instantiate( $property_name, $property_value );
            } else if ( $this->_schema_says_instantiate_array_of_objects( $property_name ) ) {
              $this->$property_name = $this->_instantiate_array_of_objects( $property_name, $property_value );
            } else {
              $this->$property_name = $property_value;
            }
            unset($value[$property_name]);
          }
          if ( method_exists( $this, $method_name = "filter_{$property_name}_value" ) )
            $this->$property_name = $this->$method_name( $this->$property_name, $property_value );
        }

        if ( count( $value ) )
          $this->__unused__ = $value;

        if ( method_exists( $this, "monitor_new_values" ) )
          $this->monitor_new_values( $this, $value );

      }
    }

    if ( method_exists( $this, 'initialize' ) )
      $this->initialize( $value, $id );

    if ( $this === $this->__root__ ) {
      // @TODO: Make it so this does not require calling parent::finalize() somehow.
      $this->_set_defaults( $this, $id );
      $this->finalize();
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
        if ( preg_match( '#^__[a-zA-Z_0-9]+__$#', $name ) ) {
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
        $new_value = $this->_get_property_default( $this, $method_name, $name, $value, $this->$property_name );
        $this->$property_name = $this->_array_element_assign( $this->$property_name, $name, $new_value );
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
  private function _array_element_assign( $array, $element_name, $value ) {
    /**
     * Having to pass the array because of what I think it a weird bug not allowing me to assign here.
     * @see: https://gist.github.com/mikeschinkel/5028467
     */
    $array[$element_name] = $value;
    return $array;
  }
  private function _get_property_default( $object, $method_name, $name, $value ) {
    $default = call_user_func( array( $object, $method_name ), $value );
    if ( is_a( $value, 'TCLP_Needs_Default' ) ) {
      $default = $this->_instantiate( $name, $default, $value->class_name );
    }
    return $default;
  }

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

  private function _instantiate( $name, $value, $class_name = false ) {
    if ( ! $class_name )
      $class_name = $this->__schema__[$property_name];
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

    return $array_of_objects;
  }

  function finalize() {
    foreach ( get_object_vars( $this ) as $name => $value ) {
      if ( preg_match( '#^(__root__|__schema__)$#', $name ) ) {
        continue;
      } else if ( is_object( $value ) ) {
        if ( method_exists( $value, 'finalize' ) ) {
          $value->finalize();
        }
      } else if ( is_array( $value ) ) {
        foreach ( $value as $sub_name => $sub_value ) {
          if ( is_object( $sub_value ) && method_exists( $sub_value, 'finalize' ) ) {
            $sub_value->finalize();
          }
        }
      }
    }
  }

}
