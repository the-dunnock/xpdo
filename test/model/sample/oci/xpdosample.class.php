<?php
require_once (dirname(dirname(__FILE__)) . '/xpdosample.class.php');
class xPDOSample_oci extends xPDOSample {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}