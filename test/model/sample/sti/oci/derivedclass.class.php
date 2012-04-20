<?php
require_once (dirname(dirname(__FILE__)) . '/derivedclass.class.php');
class derivedClass_oci extends derivedClass {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}