<?php

class YaLogger
{
  protected $log_file;
  protected $session_log;

  static function instance()
  {
    static $instance;
    if(isset($instance))
      return $instance;
    $instance = new YaLogger(LIMB_VAR_DIR.'/ya_log.txt');
    return $instance;  
  }

  function __construct($log_file)
  {
    $this->log_file = $log_file;
    $this->session_log = array();
  }
  
  protected function _makeRecord($status, $info = '')
  { 
    $record  = array(
      'time'    => date("Y-m-d G:i:s"),
      'status'  => $status,
      'info'    => $info,
    );
    
    $this->session_log[] = $record;
    $s = implode(' ',$record)."\n";
    
    file_put_contents ($this->log_file,$s,FILE_APPEND);
  }

  function getRecords()
  {
    return $this->session_log;
  }

  function log($output = '', $status='MSG' ,$error = null)
  {

    if(null === $error)
      $this->_makeRecord($status,$output);
    else
    {
      if(!is_string($error))
        $error = var_export($error, true);

      if($output)
        $error .= PHP_EOL . $output;

      $this->_makeRecord("ERROR", $error);
    }
  }

  function logConflict($output = '')
  {
    $this->_makeRecord("CONFLICT",$output);
  }

  function logException($info)
  {
    $this->_makeRecord("EXCEPTION", $info);
  }
}

