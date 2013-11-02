<?php
/**
 * Created by JetBrains PhpStorm.
 * User: webmaster
 * Date: 24.11.12
 * Time: 13:04
 * To change this template use File | Settings | File Templates.
 */
$basePath = ( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) );

require_once $basePath . '/config.core.php';

/*
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';
*/
define('MODX_API_MODE', true);
require_once $basePath.'/index.php';

ini_set('display_errors', true);
error_reporting(E_ALL);


#$gdriver = $modx->getObject('sources.modMediaSource', array('class_key' => 'gdriverMediaSource'));
$gdriver = $modx->newObject('gdriverMediaSource');
# var_dump($gdriver);
$gdriver->initialize();
$gdriver->getObjectFromCache($_GET['id']);