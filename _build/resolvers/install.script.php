<?php

/**
 * MyComponent resolver script - runs on install.
 *
 * Copyright 2011 Your Name <you@yourdomain.com>
 * @author Your Name <you@yourdomain.com>
 * 1/1/11
 *
 * MyComponent is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * MyComponent is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * MyComponent; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package mycomponent
 */
/**
 * Description: Resolver script for MyComponent package
 * @package mycomponent
 * @subpackage build
 */

/* Example Resolver script */

/* The $modx object is not available here. In its place we
 * use $object->xpdo
 */

$modx =& $object->xpdo;

/* Remember that the files in the _build directory are not available
 * here and we don't know the IDs of any objects, so resources,
 * elements, and other objects must be retrieved by name with
 * $modx->getObject().
 */

/* Connecting plugins to the appropriate system events and
 * connecting TVs to their templates is done here.
 *
 * Be sure to set the name of the category in $category.
 *
 * You will have to hand-code the names of the elements and events
 * in the arrays below.
 */

$pluginEvents = array('OnBeforeUserFormSave','OnUserFormSave');
$plugins = array('MyPlugin1', 'MyPlugin2');
//$templates = array('myTemplate1','myTemplate2');
//$tvs = array('MyTv1','MyTv2');
$category = 'MyComponent';

$hasPlugins = true;
$hasTemplates = true;
$hasTemplateVariables = true;

 /* If the following variable is set to true, this script will set
  * the existing system settings below. I like these setting, which
  * improve the Manager speed and usability (IMO), but you should
  * generally avoid setting existing system settings for another
  * user unless absolutely necessary for your component. Note that
  *  the changes will remain even if the component is uninstalled
  */

 $hasExistingSettings = false;

/* These existing system settings will always be set during the install */
if ($hasExistingSettings) {
    $settings = array(
        'feed_modx_news_enabled'=> false,
        'feed_modx_security_enabled'=> false,
        'auto_check_pkg_updates' => false,
        'default_per_page' => '100',
        'automatic_alias' => true,
    );
}

/* set to true to connect property sets to elements */
$connectPropertySets = true;


$success = true;

$modx->log(xPDO::LOG_LEVEL_INFO,'Running PHP Resolver.');
switch($options[xPDOTransport::PACKAGE_ACTION]) {
    /* This code will execute during an install */
    case xPDOTransport::ACTION_INSTALL:
		// try $modx->addExtensionPackage(class, path) instead
        $currentExtension = $modx->getOption('extension_packages', $options);
        $modx->log(xPDO::LOG_LEVEL_INFO,'Setting extension_packages');

        if($currentExtension === NULL) {
            $setting = $modx->getObject('modSystemSetting','extension_packages');
            $setting->set('value', '[{"gdriver":{"path":"[[++core_path]]components/gdriver/model/"}}]');
            $setting->save();

            // add new
        } else {
            $currentValue = json_decode($currentExtension);
            if(!is_array($currentValue) || count($currentValue)){
                // chk empty
                $setting = $modx->getObject('modSystemSetting','extension_packages');
                $setting->set('value', '[{"gdriver":{"path":"[[++core_path]]components/gdriver/model/"}}]');
                $setting->save();
            } else {
                $currentValue[] =  (object) array(
                    'gdriver' => (object) array(
                        'path' => "[[++core_path]]components/gdriver/model/"
                    )
                );
                // chk empty
                $setting = $modx->getObject('modSystemSetting','extension_packages');
                $setting->set('value', json_encode($currentValue));
                $setting->save();

            }

            // check update

        }





        break;

    /* This code will execute during an upgrade */
    case xPDOTransport::ACTION_UPGRADE:

        /* put any upgrade tasks (if any) here such as removing
           obsolete files, settings, elements, resources, etc.
        */

        $success = true;
        break;

    /* This code will execute during an uninstall */
    case xPDOTransport::ACTION_UNINSTALL:
        $modx->log(xPDO::LOG_LEVEL_INFO,'Uninstalling . . .');
        $success = true;
        break;

}
$modx->log(xPDO::LOG_LEVEL_INFO,'Script resolver actions completed');
return $success;