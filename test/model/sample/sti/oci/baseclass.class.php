<?php
require_once (dirname(dirname(__FILE__)) . '/baseclass.class.php');
class baseClass_oci extends baseClass {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}