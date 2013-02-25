<?php

include( dirname( __DIR__ ) . '/typed-config.php' );
include( __DIR__ . '/class-example-package.php' );
include( __DIR__ . '/class-example-repository.php' );

$json_filepath = realpath( __DIR__ . '/example-1.json' );
$package = Typed_Config_Loader::load( 'package', 'Typed_Config_Example_Package', $json_filepath );
print_r( $package );

