<?php
set_time_limit(0);
require_once(dirname(__FILE__) . '/../setup.php');
lmb_require('limb/core/src/lmbBacktrace.class.php');
lmb_require('limb/fs/src/lmbFs.class.php');
lmb_require('ya/src/YaLogger.class.php');
/** Превед багу */
new lmbBacktrace;

function write_error_in_log($errno, $errstr, $errfile, $errline)
{
  global $logger;
  $back_trace = new lmbBacktrace(10, 10);
  $error_str = " error: $errstr\nfile: $errfile\nline: $errline\nbacktrace:".$back_trace->toString();
  $logger->log($error_str,"ERROR",$errno);
}

set_error_handler('write_error_in_log');
error_reporting(E_ALL);
ini_set('display_errors', true);

if($argc < 2)
  die('Usage: php cron_runner.php cron_job_file_path(starting from include_file_path)' . PHP_EOL);

$cron_job_file_path = $argv[1];
$logger = YaLogger::instance();

$lock_dir = LIMB_VAR_DIR . '/cron_job_lock/';
if(!file_exists($lock_dir))
  lmbFs :: mkdir($lock_dir, 0777);

$name = array_shift(explode('.', basename($cron_job_file_path)));
$lock_file = $lock_dir . $name;
if(!file_exists($lock_file))
{
  file_put_contents($lock_file, '');
  chmod($lock_file, 0777);
}

$fp = fopen($lock_file, 'w');

if(!flock($fp, LOCK_EX + LOCK_NB))
{
  $logger->logConflict();
  return;
}

flock($fp, LOCK_EX + LOCK_NB);

  try {
    lmb_require($cron_job_file_path);
    $job  = new $name;

    if(!in_array('-ld', $argv))
      $logger->log('',"START");

    ob_start();
      echo $name . ' started' . PHP_EOL;
      $result = $job->run();
      $output = ob_get_contents();
    ob_end_clean();

    if(!in_array('-ld', $argv))
      $logger->log($output,"END",$result);
  }
  catch (lmbException $e)
  {
    $logger->logException($e->getNiceTraceAsString());
    throw $e;
  }

flock($fp, LOCK_UN);
fclose($fp);

if(in_array('-v', $argv))
{
  echo $output;
  var_dump($logger->getRecords());
}
