<?php
require_once (dirname(dirname(__FILE__)) . '/personphone.class.php');
class PersonPhone_oci extends PersonPhone {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}