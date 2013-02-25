<?php
/**
 * @property WPPM_Package $ROOT
 */
class Typed_Config_Example_Repository extends Typed_Config {

  var $name;
  var $type;
  var $url;
  var $domain;
  var $version;
  var $account;

  function initialize( $value, $id ) {

    if ( is_string( $value ) )
      $this->url = $value;

    if ( is_null( $this->version ) )
      $this->version = '*';

    $this->__id__ = preg_replace( '#^https?://(.*)#', '$1', $this->url );
    $parts = explode( '/', $this->__id__ );

    $this->domain = $parts[0];

    switch ( $this->domain ) {
      case 'bitbucket.org':
      case 'github.com':
        $this->account = isset( $parts[1] ) ? $parts[1] : null;
        $this->name = isset( $parts[2] ) ? $parts[2] : null;
        break;
      case 'wordpress.org':
      case 'plugins.svn.wordpress.org':
      case 'themes.svn.wordpress.org':
        $this->name = array_pop( $parts );
        break;
    }

    switch ( $this->domain ) {
      case 'github.com':
        $this->type = 'git';
        break;
      case 'bitbucket.org':
        $this->type = is_null( $this->type ) ? 'hg' : $this->type;
        $this->url = preg_replace( '#^http://(.*)$#', 'https://$1', $this->url );
        break;
      case 'wordpress.org':
      case 'plugins.svn.wordpress.org':
      case 'themes.svn.wordpress.org':
        $this->type = 'svn';
        break;
    }

  }

}

