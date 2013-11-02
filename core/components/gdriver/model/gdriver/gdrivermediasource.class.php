<?php
/**
 * @package modx
 * @subpackage sources
 */
require_once MODX_CORE_PATH . 'model/modx/sources/modmediasource.class.php';

class gdriverMediaSource extends modMediaSource implements modMediaSourceInterface {
    public $client;
    public $service;
    private $perm;

    public function __construct(xPDO & $xpdo) {
        parent::__construct($xpdo);

        $this->set('is_stream',true);
    }

    /**
     * Initializes
     * @return boolean
     */
    public function initialize() {
        parent::initialize();
        $properties = $this->getPropertyList();

        require_once MODX_CORE_PATH.'components/gdriver/classes/google-api-php-client/src/Google_Client.php';
        require_once MODX_CORE_PATH.'components/gdriver/classes/google-api-php-client/src/contrib/Google_DriveService.php';

        //@TODO: system Options

        $this->client = new Google_Client();
        // Get your credentials from the APIs Console
        $this->client->setClientId($this->xpdo->getOption('gdriver_ClientId'));
        $this->client->setClientSecret($this->xpdo->getOption('gdriver_ClientSecret'));
        $this->client->setRedirectUri($this->xpdo->getOption('gdriver_RedirectUri'));
        $this->client->setScopes(array('https://www.googleapis.com/auth/drive'));

        $this->service = new Google_DriveService($this->client);
        $accessToken = $this->xpdo->getOption('gdriver_access_token');


        try {
            $this->client->setAccessToken($accessToken);
        } catch (Exception $e) {
            $this->log(LOG_LEVEL_ERROR, "GDriver - Client Connect Exception: ".$e->getMessage());

            $authUrl = $this->client->createAuthUrl();

            $entry = $this->xpdo->getObject('modLexiconEntry',array(
                'namespace' => 'gdriver',
                'name' => 'setting_gdriver_access_token_desc',
            ));
            $entry->set('value', "Please visit:  <a href='$authUrl'>this Site</a> and Enter the Code here ");
            $entry->save();
            $this->log(LOG_LEVEL_INFO, "GDriver - Please visit:  <a href='$authUrl'>this Site</a> and Enter the Code here ");

            $this->xpdo->cacheManager-> refresh(array( 'system_settings' => array() ));

            if($_GET['code']) {
                $authCode = $_GET['code'];
            } else {
                $this->log(LOG_LEVEL_INFO, "GDriver - no Code.. ending GDriver");
                return false;
            }

            // Exchange authorization code for access token
            $accessToken = $this->client->authenticate($authCode);

            $Setting = $this->xpdo->getObject('modSystemSetting', 'gdriver_access_token');
            $Setting->set('value', $accessToken);
            $Setting->save();

            $entry = $this->xpdo->getObject('modLexiconEntry',array(
                'namespace' => 'gdriver',
                'name' => 'setting_gdriver_access_token_desc',
            ));
            $entry->set('value', "to get an new token, remove this value");
            $entry->save();

            $this->log(LOG_LEVEL_INFO, "GDriver - code was fine");

            $this->xpdo->cacheManager-> refresh(array( 'system_settings' => array() ));

        }
        return true;
    }

    /**
     * Get the name of this source type
     * @return string
     */
    public function getTypeName() {
        return 'GDriver';
    }
    /**
     * Get the description of this source type
     * @return string
     */
    public function getTypeDescription() {
        return 'Google Drive Connector';
    }

    public function getAllFolderNames(){
        $result = array();
        $pageToken = NULL;

        do {
            try {
                $parameters = array();
                $parameters['q'] 	  = "mimeType='application/vnd.google-apps.folder'";
                $parameters['fields'] = "items(id,title),nextLink";

                if ($pageToken) {
                    $parameters['pageToken'] = $pageToken;
                }
                $files = $this->service->files->listFiles($parameters);
                $result = array_merge($result, $files['items']);
                /* @TODO: oop using
                if($files) {
                $result = array_merge($result, $files->getItems());
                $pageToken = $files->getNextPageToken();
                }
                 */
            } catch (Exception $e) {
                print "An error occurred: " . $e->getMessage();
                $pageToken = NULL;
            }
        } while ($pageToken);

        $folders = array();
        foreach($result as $fold)
            $folders[$fold['id']] = $fold['title'];

        return $folders;
    }
    /**
     * @param string $path
     * @return array
     */
    public function getContainerList($path) {
        header('Content-type: application/json');
        $properties = $this->getPropertyList();
        $hideFiles = !empty($properties['hideFiles']) && $properties['hideFiles'] != 'false' ? true : false;

        $folders = $this->getAllFolderNames();
        if($path == '/') $path = 'root';
        $pageToken = NULL;

        $parameters = array();
//        $parameters['maxResults'] = $limit;
        if ($pageToken) {
            $parameters['pageToken'] = $pageToken;
        }
        $directories = array();
        $children = $this->service->children->listChildren($path, $parameters);
        foreach($children['items'] as $fold){
            if(!isset($folders[$fold['id']])) continue;
            $cls = array('folder');
            $directories[] = array(
                'id' => $fold['id'],
                'path' => $fold['id'],
                'pathRelative' => $fold['id'],

                'text' => $folders[$fold['id']],
                'cls' => "folder pchmod pcreate premove pupdate pupload pcreate",// implode(' ',$cls),
                'type' => 'dir',
                'leaf' => false,
                'perms' => '0444',
                'menu' => $this->getListContextMenu('', true, array()),
            );
        }
        if (!$hideFiles && $this->hasPermission('file_list')) {
            $files = $this->getObjectsInContainer($path);
            $directories = array_merge($directories, $files);
        }
        return $directories;
    }
    /**
     * Get the context menu for when viewing the source as a tree
     *
     * @param string $file
     * @param boolean $isDir
     * @param array $fileArray
     * @return array
     */
    public function getListContextMenu($file,$isDir,array $fileArray) {
        $menu = array();
        if (!$isDir) { /* files */
            /*
            $menu[] = array(
                'text' => $this->xpdo->lexicon('file_edit'),
                'handler' => 'this.editFile',
            );
            */
            $menu[] = array(
                'text' => $this->xpdo->lexicon('rename'),
                'handler' => 'this.renameFile',
            );

            $menu[] = array(
                'text' => $this->xpdo->lexicon('file_download'),
                'handler' => 'this.downloadFile',
            );
            if (!empty($menu)) $menu[] = '-';
            $menu[] = array(
                'text' => $this->xpdo->lexicon('file_remove'),
                'handler' => 'this.removeFile',
            );
        } else { /* directories */
            $menu[] = array(
                'text' => $this->xpdo->lexicon('file_folder_create_here'),
                'handler' => 'this.createDirectory',
            );
            $menu[] = array(
                'text' => $this->xpdo->lexicon('rename'),
                'handler' => 'this.renameDirectory',
            );
            $menu[] = array(
                'text' => $this->xpdo->lexicon('directory_refresh'),
                'handler' => 'this.refreshActiveNode',
            );
            $menu[] = '-';
            $menu[] = array(
                'text' => $this->xpdo->lexicon('upload_files'),
                'handler' => 'this.uploadFiles',
            );
            $menu[] = '-';
            $menu[] = array(
                'text' => $this->xpdo->lexicon('file_folder_remove'),
                'handler' => 'this.removeDirectory',
            );
        }
        return array('items' => $menu);
    }

    /**
     * Get all files in the directory and prepare thumbnail views
     *
     * @param string $path
     * @return array
     */
    public function getObjectsInContainer($path) {

        $hideFiles = !empty($properties['hideFiles']) && $properties['hideFiles'] != 'false' ? true : false;

        $result = array();
        $pageToken = NULL;
        if($path == '/') $path = 'root';
        do {
            try {
                $parameters = array();
                $parameters['q'] = "'$path' in parents";
                if ($pageToken) {
                    $parameters['pageToken'] = $pageToken;
                }
                $files = $this->service->files->listFiles($parameters);

                $result = array_merge($result, $files['items']);
                $pageToken = $files['nextPageToken'];
            } catch (Exception $e) {
                print "An error occurred: " . $e->getMessage();
                $pageToken = NULL;
            }
        } while ($pageToken);

        $files = array();
        foreach($result as $file) {
            if($file['mimeType'] == 'application/vnd.google-apps.folder') continue;
            $this->perm = $file['userPermission'];
            $cls = array('premove','pupdate');
            $cls[] = 'icon-file';
            $cls[] = 'icon-'.$file['fileExtension'];

            $fileName 		= $file['title'];
            $filenameThumb 	= $file['thumbnailLink'];

            $files[] = array(
                'id' 			=> $file['id'],
                'name' 			=> $file['title'],
                'cls' 			=> implode(' ', $cls),
                'image' 		=> 'img',
                'image_width' 	=> $file['imageMediaMetadata']['width'],
                'image_height' 	=> $file['imageMediaMetadata']['height'],
                'thumb' 		=> $filenameThumb,
                'thumb_width' 	=> 220,
                'thumb_height' 	=> 165,
                'text' 			=> $fileName,
                'url'           => $file['webContentLink'],
                'url'           => $this->xpdo->getOption('site_url').'assets/components/gdriver/file.php?id='.$file['id'],
                'relativeUrl' 	  => $this->xpdo->getOption('site_url').'assets/components/gdriver/file.php?id='.$file['id'],
                'fullRelativeUrl' => $this->xpdo->getOption('site_url').'assets/components/gdriver/file.php?id='.$file['id'],

                'qtip'			=> "<img src=\"{$filenameThumb}\" alt=\"{$fileName}\" style='max-width:500px;max-height:400px;'/>",
                'ext'           => $file['fileExtension'],

                'path' => MODX_BASE_PATH.'assets/components/gdrive/?file='.$file['id'],

                'pathRelative' => $file['id'],

                'pathname' => "PATH",
                'lastmod' => $file['modifiedDate'],
                'disabled' => false,
                'perms' => '0644',
                'leaf' => true,
                'size' => $file['fileSize'],
                'menu' => $this->getListContextMenu('', false, array()),
                'type' => "file",

#                'url' => MODX_BASE_URL .'assets/components/gdriver/file.php?id='.$file['id'],
                'urlAbsolute' => 'urlabs',

            );

        }

        return $files;

    }

    /**
     * Create a Container
     *
     * @param string $name
     * @param string $parentContainer
     * @return boolean
     */
    public function createContainer($name,$parentContainer) {
        try {
            $uploadfile = new Google_DriveFile();
            $uploadfile->setTitle($name);
            $uploadfile->setDescription('created with GDriver on '.date('Y-m-d H:i:s'));
            $uploadfile->setMimeType('application/vnd.google-apps.folder');

            $parent = new Google_ParentReference();
            $parent->setId($parentContainer);

            $uploadfile->setParents(array($parent));
            $this->service->files->insert($uploadfile);

        } catch (Exception $e) {
            $this->addError('file','could not create directory');
            return false;
        }
        $this->xpdo->logManagerAction('directory_create','',$name);
        return true;
    }

    /**
     * Remove an empty folder
     *
     * @param $path
     * @return boolean
     */
    public function removeContainer($path) {
        try {
            $this->service->files->delete($path);
            /* log manager action */
            $this->xpdo->logManagerAction('file_remove','',$path);
            return true;
        } catch (Exception $e) {
            $this->addError('file',$this->xpdo->lexicon('file_folder_err_ns').': '.$path);
            return false;
        }
    }


    /**
     * Delete a file
     *
     * @param string $objectPath
     * @return boolean
     */
    public function removeObject($objectPath) {
        try {
            $this->service->files->delete($objectPath);
            /* log manager action */
            $this->xpdo->logManagerAction('file_remove','',$objectPath);
            return true;
        } catch (Exception $e) {
            $this->addError('file',$this->xpdo->lexicon('file_folder_err_ns').': '.$objectPath);
            return false;
        }
    }

    public function updateObject($objectPath,$content) {
        var_dump($objectPath, $content);
        return false;
        $file = new Google_DriveFile($objectPath);
        $this->service->files->update($objectPath, $file, array(
            'data' => $content,
        ));
    }
    /**
     * @param string $oldPath
     * @param string $newName
     * @return bool
     */
    public function renameContainer($oldPath,$newName) {
        $file = new Google_DriveFile($oldPath);
        $file->setTitle($newName);
        $this->service->files->update($oldPath, $file);
        return true;
    }
    /**
     * Rename/move a file
     *
     * @param string $oldPath
     * @param string $newName
     * @return bool
     */
    public function renameObject($oldPath,$newName) {
        $file = new Google_DriveFile($oldPath);
        $file->setTitle($newName);
        $this->service->files->update($oldPath, $file);
        return true;
    }
    function resolvePathToId($path, $parent = 'root') {
        $path = explode('/', $path);
        array_reverse($path);


        foreach($path as $dirname) if($dirname != '') break;

        $parameters = array();
        $parameters['q'] = "title = '{$dirname}' AND mimeType = 'application/vnd.google-apps.folder'";

        $directories = array();
        $children = $this->service->files->listFiles($parameters);

        if(count($children['items']) == 0) return false;
        if(count($children['items']) == 1) return $children['items'][0]['id'];

        var_dump($children);
        die();

        foreach($children['items'] as $fold){
            if(!isset($folders[$fold['id']])) continue;
            $cls = array('folder');
            $directories[] = array(
                'id' => $fold['id'],
                'text' => $folders[$fold['id']],
                'cls' => "folder pchmod pcreate premove pupdate pupload pcreate",// implode(' ',$cls),
                'type' => 'dir',
                'leaf' => false,
                'perms' => '0444',
                'menu' => $this->getListContextMenu('', true, array()),
            );
        }
    }
    /**
     * Upload files
     *
     * @param string $container
     * @param array $objects
     * @return bool
     */
    public function uploadObjectsToContainer($container,array $objects = array()) {
        if ($container == '/' || $container == '.') $container = 'root';
        $parentContainerID = $this->resolvePathToId($container);

        $allowedFileTypes = explode(',',$this->xpdo->getOption('upload_files',null,''));
        $allowedFileTypes = array_merge(explode(',',$this->xpdo->getOption('upload_images')),explode(',',$this->xpdo->getOption('upload_media')),explode(',',$this->xpdo->getOption('upload_flash')),$allowedFileTypes);
        $allowedFileTypes = array_unique($allowedFileTypes);
        $maxFileSize = $this->xpdo->getOption('upload_maxsize',null,1048576);

        /* loop through each file and upload */
        foreach ($objects as $file) {
            if ($file['error'] != 0) continue;
            if (empty($file['name'])) continue;
            $ext = @pathinfo($file['name'],PATHINFO_EXTENSION);
            $ext = strtolower($ext);

            if (empty($ext) || !in_array($ext,$allowedFileTypes)) {
                $this->addError('path',$this->xpdo->lexicon('file_err_ext_not_allowed',array(
                    'ext' => $ext,
                )));
                continue;
            }
            $size = @filesize($file['tmp_name']);
            if ($size > $maxFileSize) {
                $this->addError('path',$this->xpdo->lexicon('file_err_too_large',array(
                    'size' => $size,
                    'allowed' => $maxFileSize,
                )));
                continue;
            }
            // UPLOAD
            $uploadfile = new Google_DriveFile();
            $uploadfile->setTitle($file['name']);
            $uploadfile->setDescription('uploaded with GDriver on '.date('Y-m-d H:i:s'));
            $uploadfile->setMimeType($file['type']);

            $parent = new Google_ParentReference();
            $parent->setId($parentContainerID);

            $uploadfile->setParents(array($parent));
            $uploaded = $this->service->files->insert($uploadfile, array(
                'data' => file_get_contents($file['tmp_name']),
            ));

            if (!$uploaded) {
                $this->addError('path',$this->xpdo->lexicon('file_err_upload'));
            }
        }

        /* invoke event */
        $this->xpdo->invokeEvent('OnFileManagerUpload',array(
            'files' => &$objects,
            'directory' => $container,
            'source' => &$this,
        ));

        $this->xpdo->logManagerAction('file_upload','',$container);

        return true;
    }



    /**
     * Move a file or folder to a specific location
     *
     * @param string $from The location to move from
     * @param string $to The location to move to
     * @param string $point
     * @return boolean
     */
    public function moveObject($from,$to,$point = 'append') {
        try {
            switch($point) {
                case 'below':
                case 'above':
                    // set $to to new value
                    $parents = $this->service->parents->listParents($to);
                    $parent = array_pop($parents['items']);
                    $to = $parent['id'];

                case 'append':
                    // chg parentTo This
                    $parent = new Google_ParentReference();
                    $parent->setId($to);

                    $file = new Google_DriveFile($from);
#                    $file = $this->service->files->get();

                    $file->setParents(array($parent));
                    $this->service->files->update($from, $file);


                    break;
                default:
                    return false;
            }
        } catch(Exception $e) {
            $this->log(LOG_LEVEL_ERROR, "GDriver - File-Move-Error: (from: $from, to: $to, point: $point) ".$e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @return array
     */
    public function getDefaultProperties() {
        return array(
            'imageExtensions' => array(
                'name' => 'imageExtensions',
                'desc' => 'imageExtensions_desc',
                'type' => 'textfield',
                'value' => 'jpg,jpeg,png,gif',
                'lexicon' => 'core:source',
            ),
        );
    }

    /**
     * Get the base URL for this source. Only applicable to sources that are streams.
     *
     * @param string $object An optional object to find the base url of
     * @return string
     */
    public function getBaseUrl($object = '') {
        $properties = $this->getPropertyList();
        return $properties['url'];
    }

    /**
     * Get the absolute URL for a specified object. Only applicable to sources that are streams.
     *
     * @param string $object
     * @return string
     */
    public function getObjectFromCache($fileId = '') {
        $cacheOptions = array(
            xPDO::OPT_CACHE_KEY => 'gdriver',
            xPDO::OPT_CACHE_HANDLER => 'cache.xPDOAPCCache',
        );

        $cache = $this->xpdo->cacheManager->get($fileId, $cacheOptions);
        if($cache) {
            header('Location: /core/cache/gdriver/'.$cache['originalFilename']);

            die();
            header("Content-Type: ".$cache['content-type']);
            header('Content-Disposition: inline; filename="'.$cache['originalFilename'].'"');
            header('Content-Length: '.$cache['content-length']);
            readfile($this->xpdo->cacheManager->getCachePath().'/gdriver/'.$cache['originalFilename']);
            die();
        }

        try {
            $file = $this->service->files->get($fileId);
            if (!isset($file['downloadUrl'])) {
                $this->xpdo->error->message = $this->xpdo->lexicon('file_not_found');
                return false;
            }

            $request = new Google_HttpRequest($file['downloadUrl'], 'GET', null, null);
            $httpRequest = $this->client->getIo()->authenticatedRequest($request);
            if ($httpRequest->getResponseHttpCode() == 200) {

                $header                     = $request->getResponseHeaders();
                $header['originalFilename'] = $file['originalFilename'];
//                $header['content']          = $httpRequest->getResponseBody();
                $this->xpdo->cacheManager->set($fileId, $header , 0, $cacheOptions);
                $this->xpdo->cacheManager->writeFile(
                    $this->xpdo->cacheManager->getCachePath().'/gdriver/'.$header['originalFilename'],
                    $httpRequest->getResponseBody()
                );

                header("Content-Type: ".$header['content-type']);
                header('Content-Disposition: inline; filename="'.$header['originalFilename'].'"');
                header('Content-Length: '.$header['content-length']);

                echo $httpRequest->getResponseBody();
                exit;
            } else {
                // An error occurred.
                return false;
            }
        } catch (Exception $e) {
            echo $e->getMessage();

            die();
            $this->xpdo->error->message = $this->xpdo->lexicon('file_not_found');
            return false;
        }
    }


    /**
     * Get the contents of a specified file
     *
     * @param string $objectPath
     * @return array
     */
    public function getObjectContents($fileId) {
        $properties = $this->getPropertyList();
        $imageExtensions = $this->getOption('imageExtensions',$properties,'jpg,jpeg,png,gif');

        try {
            $file = $this->service->files->get($fileId);
            if (!isset($file['downloadUrl'])) {
                $this->xpdo->error->message = $this->xpdo->lexicon('file_not_found');
                return false;
            }

            $request = new Google_HttpRequest($file['downloadUrl'], 'GET', null, null);
            $httpRequest = $this->client->getIo()->authenticatedRequest($request);
            if ($httpRequest->getResponseHttpCode() == 200) {

                $header = $request->getResponseHeaders();
                if($_GET['a'] == 40) {
                    $fa = array(
                        'name' => $file['title'],
                        'basename' => $file['parents'][0]['id'],
                        'path' => $file['id'],
                        'size' => $header['content-length'],
                        'last_accessed' => '',
                        'last_modified' => $file['modifiedDate'],
                        'content' => $httpRequest->getResponseBody(),
                        'image' => in_array($file['fileExtension'],$imageExtensions) ? true : false,
                        'is_writable' => true,
                        'is_readable' => true,
                    );
                    return $fa;
                } else {


                }
                header("Content-Type: ".$header['content-type']);
                header('Content-Disposition: attachment; filename="'.$file['originalFilename'].'"');
                header('Content-Length: '.$header['content-length']);

                echo $httpRequest->getResponseBody();
                exit;
            } else {
                // An error occurred.
                return false;
            }
        } catch (Exception $e) {
            echo $e->getMessage();

            die();
            $this->xpdo->error->message = $this->xpdo->lexicon('file_not_found');
            return false;
        }
    }


    /**
     * Prepare a src parameter to be rendered with phpThumb
     *
     * @param string $src
     * @return string
     */
    public function prepareSrcForThumb($src) {
        $properties = $this->getPropertyList();
        if (strpos($src,$properties['url']) === false) {
            $src = $properties['url'].ltrim($src,'/');
        }
        return $src;
    }


    /**
     * Get the absolute URL for a specified object. Only applicable to sources that are streams.
     *
     * @param string $object
     * @return string
     */
    public function getObjectUrl($object = '') {
        $properties = $this->getPropertyList();
        return $properties['url'].$object;
    }
}
return 'GDriverMediaSource';