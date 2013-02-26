<?php

class Typed_Config_Loader {
  private static $_logger;
  function set_logger( $logger ) {
    return self::$_logger = $logger;
  }
  /**
   * Loads a JSON file into a Typed Object
   *
   * @param string $name - The "Type" of object, i.e. "package", "config", etc. Used to give the loaded object its identity
   * @param string $class_name - The base typed object.
   * @param string $json - Filepath to JSON file or JSON in string form.
   *
   * @return mixed
   */
  static function load( $name, $class_name, $json ) {
    if ( ! isset( self::$_logger ) )
      self::$_logger = new TCLP_Logger();

    if ( empty( $json ) )
      self::$_logger->error( "The {$name} file or JSON string {$json} is empty." );

    /**
     * See if it's a file
     */
    if ( is_file( $json ) ) {
      $json = file_get_contents( $json_filepath = $json );
      $json_object = json_decode( $json );
      if ( empty( $json_object ) )
        self::$_logger->error( "The {$name} file {$json_filepath} has invalid syntax." );
    } else {
      /**
       * If it's not a file maybe it's a JSON string. Let's check that.
       */
      $json_object = json_decode( $json );
      if ( empty( $json_object ) ) {
        if ( strlen( $json ) > 100 )
          $json = preg_replace( '#\s+#', ' ', substr( $json, 0, 100 ) . '...' );
        self::$_logger->error( "The JSON value provided for {$name} is not a valid JSON string: {$json}" );
      }
    }

    /**
     * @var Typed_Config $object
     */
    $object = new $class_name();
    $object->set_logger( self::$_logger );
    if ( ! empty( $json_filepath ) ) {
      $object->set_filepath( $json_filepath );
    }
    $object->instantiate( $name, $json_object, $object );
    return $object;

  }
  /**
   * Autoloads classes from the /classes subdirectory.
   *
   * Class WPPM_Foo_Bar_Baz should be found in class-foo-bar-baz.php
   *
   * @param string $class_name
   *
   * @throws Exception
   */
  static function class_autoloader( $class_name ) {
    $class_file = strtolower( str_replace( '_', '-', preg_replace( '#^TCLP_(.*)$#', '$1', $class_name ) ) );
    $class_file = dirname( __DIR__ ) . "/classes/class-{$class_file}.php";
    if ( file_exists( $class_file ) ) {
      require ( $class_file );
    }
  }
}
spl_autoload_register( array( 'Typed_Config_Loader', 'class_autoloader' ) );

