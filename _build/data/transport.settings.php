<?php
/** Array of system settings for Mycomponent package
 * @package mycomponent
 * @subpackage build
 */


/* This section is ONLY for new System Settings to be added to
 * The System Settings grid. If you include existing settings,
 * they will be removed on uninstall. Existing setting can be
 * set in a script resolver (see install.script.php).
 */
$settings = array();

/* The first three are new settings */
$settings['gdriver_ClientId']= $modx->newObject('modSystemSetting');
$settings['gdriver_ClientId']->fromArray(array (
    'key' => 'gdriver_ClientId',
    'value' => '',
    'namespace' => 'gdriver',
    'area' => 'web application',
), '', true, true);

$settings['gdriver_ClientSecret']= $modx->newObject('modSystemSetting');
$settings['gdriver_ClientSecret']->fromArray(array (
    'key' => 'gdriver_ClientSecret',
    'value' => '',
    'namespace' => 'gdriver',
    'area' => 'web application',
), '', true, true);
$settings['gdriver_RedirectUri']= $modx->newObject('modSystemSetting');
$settings['gdriver_RedirectUri']->fromArray(array (
    'key' => 'gdriver_RedirectUri',
    'value' => 'http://www.yoursite.com/assets/components/gdriver/auth.php',
    'namespace' => 'gdriver',
    'area' => 'web application',
), '', true, true);



$settings['gdriver_access_token']= $modx->newObject('modSystemSetting');
$settings['gdriver_access_token']->fromArray(array (
    'key' => 'gdriver_access_token',
    'value' => '{}',
    'namespace' => 'gdriver',
    'area' => 'user authentication',
), '', true, true);

return $settings;