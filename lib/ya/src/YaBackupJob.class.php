<?php
lmb_require('ya/src/YaBackup.class.php');

class YaBackupJob extends CronJob
{
  protected $conf;
  protected $conf_name = 'project';
  
  function __construct()
  {
    $this->conf = lmbToolkit::instance()->getConf($this->conf_name);
  }
  
  function run()
  {
    $backup = new YaBackup($this->conf);
    $backup->execute();
  }
  
}
