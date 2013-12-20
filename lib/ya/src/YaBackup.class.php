<?
lmb_require('ya/src/YaDisk.class.php');
lmb_require('ya/src/YaAuth.class.php');

class YaBackup
{
  protected $disk;
  protected $db;
  protected $logger;
  protected $backup_number;  

  function __construct($backupconfig)
  {
    $config = lmbToolkit::instance()->getConf('yandex');
    $this->logger = YaLogger::instance();
        
    $auth = new YaAuth($config,$this->logger);
    $token = $auth->getToken();
    if($token == '') throw Exception('Не могу получить токен');
    $this->disk = new YaDisk($token,$config,$this->logger);

    $this->db = $backupconfig->get('db');
    $this->folders = $backupconfig->get('folders');
    $this->tmp_dir = $backupconfig->get('tmp_dir');
    $this->project = $backupconfig->get('project');
    $this->backup_number = $backupconfig->get('stored_backups_number');
    $this->server_dir = $backupconfig->get('dir');
    
    $time = time();
    $this->archive = date("Y-m-d",$time).'-'.$time;
  }

  function execute()
  {
    $this->logger->log("Начат бекап проекта ".$this->project,"START_PROJECT");
    $this->_clean();
    $this->logger->log("Удаление старых копий");
    $this->_deleteOld();
    $this->logger->log("Создание дампа базы");
    $this->_makeDump();
    $this->logger->log("Копирование необходимых файлов"); 
    $this->_copyFolders();
    $this->logger->log("Создание архива"); 
    $this->_createArchive();
    $this->logger->log("Копирование на Яндекс.Диск");
    $this->_upload();
    $this->logger->log("Удаление временных файлов"); 
    $this->_clean();
    $this->logger->log("Бекап проекта ".$this->project." завершен", "END_PROJECT");
  }

  protected function _clean()
  { 
    lmbFs::rm($this->getProjectDir());
  }

  protected function _deleteOld()
  {
    $list = $this->disk->ls($this->server_dir.'/'.$this->project);
    $paths=array();
    $n=0;
    foreach($list as $item)
    {
      //Имена архивов имеют вид Y-m-d-timestamp.tar.gz. В качестве ключа массива используем timestamp.
      $parts = explode('-',basename(rtrim($item['href'],'/')));
      if(isset($parts[3]) && ($item['type']=='f'))
      { 
        $tm = explode('.',$parts[3]);
        $paths[(integer)$tm[0]] = $item['href'];
        $n++;
      }
    }
    ksort($paths);//сортируем массив по ключам от меньшего к большему
    for($i=$n;$i>$this->backup_number-1;$i--)
    {
      $item = array_shift($paths);
      $this->logger->log("Удаление ".$item);
      $this->disk->rm($item); 
    }    
  }

  protected function _upload()
  {
    $archive = $this->archive.'.tar.gz';
    
    //создаем дирректории на яндекс диске 
    $this->logger->log("Создаем папки на Яндекс.Диске"); 
    $this->disk->mkdir($this->server_dir);
    $res = $this->disk->mkdir($this->server_dir.'/'.$this->project);
    //Копируем архив    
    $this->logger->log("Копируем архив на Яндекс.Диск"); 
    $this->disk->upload($this->getProjectDir().'/'.$archive,$this->server_dir.'/'.$this->project.'/'.$archive);
    
    if($res) 
      $this->logger->log("Копирование на Яндекс.Диск завершено успешно"); 
    else
      $this->logger->log("Копирование на Яндекс.Диск завершено завершено с ошибкой"); 
  }

  protected function getProjectDir()
  {
    return $this->tmp_dir.'/'.$this->project;
  }

  protected function _copyFolders()
  {
    lmbFs:: mkdir($this->getProjectDir() . '/folders');

    $folders = $this->folders;

    foreach($folders as $key => $value)
    {
      lmbFs:: mkdir($this->getProjectDir() . '/folders/' . $key);
      lmbFs:: cp($value, $this->getProjectDir() . '/folders/' . $key);
    }
  }

  protected function _createArchive()
  {
    $archive = $this->archive;
    $dir = $this->getProjectDir();
    //переписать через system
    `cd $dir && find . -type f -exec tar rvf "$archive.tar" '{}' \;`;  
    `cd $dir && gzip $archive.tar`;
  }  

  protected function _makeDump()
  {
    $host = $this->db['host'];
    $user = $this->db['user'];
    $password = $this->db['password'];
    $database = $this->db['database'];
    $charset = $this->db['charset'];

    lmbFs:: mkdir($this->getProjectDir() . '/base');
    $sql_schema = $this->getProjectDir() . '/base/schema.mysql';
    $sql_data = $this->getProjectDir() . '/base/data.mysql';
    
    //создаем дамп
    $this->mysql_dump_schema($host, $user, $password, $database, $charset, $sql_schema);
    $this->mysql_dump_data($host, $user, $password, $database, $charset, $sql_data);
  }
  
  //Следующие методы лучше вынести в отдельный файл
  protected function mysql_dump_schema($host, $user, $password, $database, $charset, $file, $tables = array())
  {
    $password = ($password)? '-p' . $password : '';
    $cmd = "mysqldump -u$user $password -h$host " .
           "-d --default-character-set=$charset " .
           "--quote-names --allow-keywords --add-drop-table " .
           "--set-charset --result-file=$file " .
           "$database " . implode('', $tables);

    
    $this->logger->log("Начинаем создавать дамп базы в '$file' file...");

    system($cmd, $ret);

    if(!$ret)
      $this->logger->log("Дамп базы создан (" . filesize($file) . " bytes)");
    else
      $this->logger->log("Ошибка создания дампа базы");;
  }

  protected function mysql_dump_data($host, $user, $password, $database, $charset, $file, $tables = array())
  {
    $password = ($password)? '-p' . $password : '';
    $cmd = "mysqldump -u$user $password -h$host " .
           "-t --default-character-set=$charset " .
           "--add-drop-table --create-options --quick " .
           "--allow-keywords --max_allowed_packet=16M --quote-names " .
           "--complete-insert --set-charset --result-file=$file " .
           "$database " . implode('', $tables);


    $this->logger->log("Начинаем создавать дамп данных в '$file' file...");

    system($cmd, $ret);

    if(!$ret)
      $this->logger->log("Дамп данных создан! (" . filesize($file) . " bytes)");
    else
     $this->logger->log("Ошибка создания дампа базы");;
  }
  
}
