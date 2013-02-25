<?php
class TCLP_Logger {
  function notice( $log_text ) {
    fwrite( STDOUT, "\nNotice: {$log_text}\n" );
  }
  function warning( $log_text ) {
    fwrite( STDOUT, "\nWARNING: {$log_text}\n" );
  }
  function error( $log_text ) {
    fwrite( STDERR, "\nERROR: {$log_text}\n" );
    die(1);
  }
  function message( $log_text ) {
    echo "{$log_text}\n";
  }
}

