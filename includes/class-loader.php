<?php

namespace Typed_Config;

/**
 * Class \Typed_Config\Loader
 */
class Loader {

	/**
	 * @var Logger
	 */
	private static $_logger;

	/**
	 * @param $logger
	 *
	 * @return Logger
	 */
	static function set_logger( $logger ) {
		return self::$_logger = $logger;
	}

	/**
	 * Loads a JSON file into a Typed Object
	 *
	 * @param string $name The "Type" of object, i.e. "package", "config", etc. Used to give the loaded object its identity
	 * @param string|object $class_name The base typed object.
	 * @param string $json Filepath to JSON file or JSON in string form.
	 *
	 * @return Data
	 */
	static function load( $name, $class_name, $json ) {

		if ( ! isset( self::$_logger ) ) {
			self::$_logger = new Logger();
		}

		if ( empty( $json ) ) {
			self::$_logger->error( "The {$name} file or JSON string {$json} is empty." );
		}

		/**
		 * See if it's a file
		 */
		if ( is_file( $json ) ) {
			$json        = file_get_contents( $json_filepath = $json );
			$json_object = json_decode( $json );
			if ( empty( $json_object ) ) {
				self::$_logger->error( "The {$name} file {$json_filepath} has invalid syntax." );
			}
		} else {
			/**
			 * If it's not a file maybe it's a JSON string. Let's check that.
			 */
			$json_object = json_decode( $json );
			if ( empty( $json_object ) ) {
				if ( strlen( $json ) > 100 ) {
					$json = preg_replace( '#\s+#', ' ', substr( $json, 0, 100 ) . '...' );
				}
				self::$_logger->error( "The JSON value provided for {$name} is not a valid JSON string: {$json}" );
			}
		}

		/**
		 * @var \Typed_Config\Data $data
		 */
		$data = is_string( $class_name ) ? new $class_name() : $class_name;

		$data->set_logger( self::$_logger );

		if ( ! empty( $json_filepath ) ) {

			$data->set_filepath( $json_filepath );

		}

		$data->instantiate( $name, $json_object );

		return $data;

	}

}

