<?php

class gdriverFileGetController extends modExtraManagerController {
    public function process(array $scriptProperties = array()) {}
    public function getPageTitle() {
        return 'My Test CMP';
    }
    public function getTemplateFile() {
        return 'welcome.tpl';
    }
}
