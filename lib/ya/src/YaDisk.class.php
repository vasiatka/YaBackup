<?php
lmb_require("limb/net/src/lmbUri.class.php");

class YaDisk
{ 
  protected $auth;
  protected $config;
  protected $error;
  protected $token;
  protected $logger;
  protected $url;
  
  function __construct($token,$config,$logger)
  {
    $this->auth = $auth;
    $this->config = $config; 
    $this->token = $token;
    $this->logger = $logger;
  } 

  function getCurl($server_dst)
  {
    $curl = lmbToolkit::instance()->getCurlRequest();
    $curl->setOpt(CURLOPT_SSL_VERIFYPEER,false);
    $curl->setOpt(CURLOPT_PORT,$this->config->get('disk_port'));
    $curl->setOpt(CURLOPT_CONNECTTIMEOUT,2);
    $curl->setOpt(CURLOPT_RETURNTRANSFER,1);
    $curl->setOpt(CURLOPT_HEADER, 0);
    $curl->setOpt(CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
    $uri = new lmbUri($this->config->get('disk_server_url'));
    $uri = $uri->setPath($server_dst)->toString();
    $curl->setOpt(CURLOPT_URL,$uri);
    $header = array('Accept: */*',
                    "Authorization: OAuth {$this->token}"
                   );
    $curl->setOpt(CURLOPT_HTTPHEADER,$header);
    return $curl;
  }

  function getResult($curl, $codes = array())
  {
    if($curl->getError())
    {
      $this->error = $curl->getError();
      echo $this->error;
      $this->logger->log('','ERROR', $this->error);
      return false;
    } 
    else
    {
      if (!in_array($curl->getRequestStatus(),$codes))
      {
        $this->error = 'Response http error:'.$curl->getRequestStatus();
        $this->logger->log('','ERROR', $this->error);
        return false;
      }
      else
      {
        return true;
      }
    }
  }

  function mkdir($server_dst)
  {
    $curl = $this->getCurl($server_dst);
    $curl->setOpt(CURLOPT_CUSTOMREQUEST,"MKCOL");
    $response = $curl->open();
    return $this->getResult($curl, array(201,405));//405 РєРѕРґ РєРѕР·РІСЂР°С‰Р°РµС‚СЃСЏ РµСЃР»Рё РїР°РїРєР° СѓР¶Рµ РµСЃС‚СЊ РЅР° СЃРµСЂРІРµСЂРµ
  }

  function upload($local_src,$server_dst)
  {
    $local_file = fopen($local_src,"r");
    $curl = $this->getCurl($server_dst);
    //$curl->setOpt(CURLOPT_CUSTOMREQUEST,"PUT");
    $curl->setOpt(CURLOPT_PUT, 1);
    $curl->setOpt(CURLOPT_INFILE,$local_file);
    $curl->setOpt(CURLOPT_INFILESIZE, filesize($local_src));
    $header = array('Accept: */*',
                    "Authorization: OAuth {$this->token}",
                    'Expect: '
                   );
    $curl->setOpt(CURLOPT_HTTPHEADER,$header);
    $response = $curl->open();
    fclose($local_file);
    return $this->getResult($curl, array(200,201,204));    
  }

  function download($server_src,$local_dst)
  {
    $local_file = fopen($local_dst,"w");
    $curl = $this->getCurl($server_src);
    $curl->setOpt(CURLOPT_HTTPGET, 1);
    $curl->setOpt(CURLOPT_HEADER, 0);
    $curl->setOpt(CURLOPT_FILE,$local_file);
    $response = $curl->open();
    fclose($local_file);
    return $this->getResult($curl, array(200));    
  }

  function rm($server_src)
  {
    $curl = $this->getCurl($server_src);
    $curl->setOpt(CURLOPT_CUSTOMREQUEST,"DELETE");
    $response = $curl->open();
    return $this->getResult($curl, array(200));    
  }  
  
  function ls($server_src)
  {
    $curl = $this->getCurl($server_src);
    $curl->setOpt(CURLOPT_CUSTOMREQUEST,"PROPFIND");
    $header = array('Accept: */*',
                    "Authorization: OAuth {$this->token}",
                    'Depth: 1',
                   );
    $curl->setOpt(CURLOPT_HTTPHEADER,$header);
    $response = $curl->open();
    if($this->getResult($curl, array(207)))
    {
      $xml = simplexml_load_string($response,"SimpleXMLElement" ,0,"d",true);
      $list = array();
      foreach($xml as $item)
      {
        if(isset($item->propstat->prop->resourcetype->collection))
          $type = 'd';
        else
          $type = 'f';
        $list[]=array('href'=>(string)$item->href,'type'=>$type);
      }
      return $list; 
    }
    return false;    
  }

  //Ugly. 
  function exists($server_src)
  { 
    $path = dirname($server_src);
    $list = $this->ls($path);
    if($list === false)
    {
      $this->error = 'Не могу получить список файлов';
      $this->logger->log('','ERROR', $this->error);
      return false;
    }
    foreach($list as $item)
      if(rtrim($item['href'],'/')==rtrim($server_src,'/'))
        return true;
    return false;
  }

  //Ugly.
  function is_file($server_src)
  { 
    $path = dirname($server_src);
    $list = $this->ls($path);
    if($list === false)
    {
      $this->error = 'Не могу получить список файлов';
      $this->logger->log('','ERROR', $this->error);
      return false;
    }
    foreach($list as $item)
      if( (rtrim($item['href'],'/')==rtrim($server_src,'/') ) && ($item['type']=='f') )
        return true;
    return false;
  }

  //Ugly. 
  function is_dir($server_src)
  { 
    $path = dirname($server_src);
    $list = $this->ls($path);
    if($list === false)
    {
      $this->error = 'Не могу получить список файлов';
      $this->logger->log('','ERROR', $this->error);
      return false;
    }
    foreach($list as $item)
      if( (rtrim($item['href'],'/')==rtrim($server_src,'/') ) && ($item['type']=='d') )
        return true;
    return false;
  }
}
