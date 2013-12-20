<?php

set_include_path(
  dirname(__FILE__) . '/' . PATH_SEPARATOR .
  dirname(__FILE__) . '/lib/' . PATH_SEPARATOR
);

@define('PROJECT_DIR', dirname(__FILE__));
@define('LIMB_VAR_DIR', PROJECT_DIR . '/var');

if(file_exists(dirname(__FILE__) . '/setup.override.php'))
  require_once(dirname(__FILE__) . '/setup.override.php');

require_once('common.inc.php');
