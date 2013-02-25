<?php

class Typed_Config_Example_Package extends Typed_Config {

  protected $__schema__ = array(
    'delete_files' => array(),
    'repositories' => array(
        'source'   => 'Typed_Config_Example_Repository',
        'publish'  => 'Typed_Config_Example_Repository',
        'embedded' => array( 'Typed_Config_Example_Repository' ),
      ),
  );

  var $type;
  var $name;
  var $slug;
  var $main_file;
  var $version = '*';
  var $repositories = array();
  var $delete_files = array();
  var $_readme;
  var $_header;

  function initialize( $args, $id ) {
    if ( is_null( $this->type ) )
      $this->type = 'plugin';

    if ( ! isset( $this->slug ) ) {
      $this->slug = strtolower( str_replace( ' ', '-', $this->name ) );
    }

    if ( ! isset( $this->main_file ) ) {
      $this->main_file = "{$this->slug}.php";
    }

  }

//  function default_value( $property_name, $element_name = false ) {
//    $value = null;
//    if ( 'repositories' == $property_name && 'publish' == $element_name ) {
//      $value = "http://{$this->type}s.svn.wordpress.org/{$this->slug}";
//    }
//    return $value;
//  }

  function finalize() {
    if ( ! $this->repositories['publish'] ) {
      $repo = new WPPM_Repository();
      $repo->initialize( "http://{$this->type}s.svn.wordpress.org/{$this->slug}", 'publish' );
      $this->repositories['publish'] = $repo;
    }
    parent::finalize();

  }

}


