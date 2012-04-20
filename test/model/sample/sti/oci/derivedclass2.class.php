<?php
require_once (dirname(dirname(__FILE__)) . '/derivedclass2.class.php');
class derivedClass2_oci extends derivedClass2 {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}