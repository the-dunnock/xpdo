<?php
require_once (dirname(dirname(__FILE__)) . '/phone.class.php');
class Phone_oci extends Phone {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}