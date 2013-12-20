<?php

class YaAuth
{
  protected $token;
  protected $error;
  protected $create_time;
  protected $ttl;
  protected $app_id;
  protected $conf;
  protected $logger;
  
  function __construct($conf,$logger)
  {
    $this->logger = $logger;
    $this->app_id = $conf->get('oauth_app_id');
    $this->clear();
    $this->conf = $conf;
  }

  function getToken()
  {
    if($this->checkToken())
      return $this->token;

    $url = $this->conf->get('oauth_token_url');
    $curl = lmbToolkit::instance()->getCurlRequest();
    
    $curl->setOpt(CURLOPT_HEADER,0);
    $curl->setOpt(CURLOPT_REFERER,$this->conf->get('oauth_referer_url'));
    $curl->setOpt(CURLOPT_URL,$url);
    
    $curl->setOpt(CURLOPT_CONNECTTIMEOUT,1);
    $curl->setOpt(CURLOPT_FRESH_CONNECT,1);
    $curl->setOpt(CURLOPT_RETURNTRANSFER,1);
    $curl->setOpt(CURLOPT_FORBID_REUSE,1);
    $curl->setOpt(CURLOPT_TIMEOUT,4);

    $curl->setOpt(CURLOPT_SSL_VERIFYPEER,false);
  
    $post = 'grant_type=password&client_id='.$this->conf->get('oauth_app_id').
            '&client_secret='.$this->conf->get('oauth_app_secret').
            '&username='.$this->conf->get('oauth_login').
            '&password='.$this->conf->get('oauth_password');

    $header = array(/*'Host: oauth.yandex.ru',*/
                    'Content-type: application/x-www-form-urlencoded',
                    'Content-Length: '.strlen($post)
                   );
    
    $curl->setOpt(CURLOPT_HTTPHEADER,$header);

    $json = $curl->open($post);

    if(!$json)
    {
      $this->error = $curl->getError();
      $this->logger->log('','ERROR', $this->error);
      return false;
    }

    $http_code = $curl->getRequestStatus();

    if(($http_code!='200') && ($http_code!='400'))
    {
      $this->error = "Request Status is ".$http_code;
      $this->logger->log('','ERROR', $this->error);
      return false;
    }
  
    $result = json_decode($json, true);

    if (isset($result['error']) && ($result['error'] != ''))
    {
      $this->error = $result['error'];
      $this->logger->log('','ERROR', $this->error);
      return false;
    }

    $this->token = $result['access_token'];
    $this->ttl = (int)$result['expires_in']; 
    $this->create_time = (int)time();
    return $this->token;
  }
 
  function clear()
  {
    $this->token = '';
    $this->error = '';
    $this->counter_id = '';
    $this->create_time = 0;
    $this->ttl = -1;
  }

  
  
  function checkToken()
  {
    if ($this->ttl <= 0) return false;
  
    if (time()>($this->ttl+$this->create_time))
    {
      $this->error = 'token_outdated';
      $this->logger->log('','ERROR', $this->error);
      return false;
    }
    return true;
  }
  
  function getError()
  {
    return $this->error;
  }
  
}
