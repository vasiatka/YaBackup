<?php
$conf = array();

$conf['project'] = 'priject.ru'; //Удобно, когда совпадает с именем домена
$conf['dir'] ='/backup';//Дирректория с проектами на яндекс диске
$conf['folders'] = array(
                        'media' => '/home/user/project.ru/www/media',
                        'files' => '/home/user/project.ru/www/files',
                        ); //папки, которые надо сохранить
$conf['tmp_dir'] = LIMB_VAR_DIR . '/backup/'; //временная директория
$conf['db']      = array('host'=>'localhost',
                         'user'=>'root',
                         'password' => 'test',
                         'database' => 'adevelopnew',
                         'charset'  => 'utf8');//Подключение к базе
$conf['stored_backups_number'] = 4;//Количество хранимых копий
