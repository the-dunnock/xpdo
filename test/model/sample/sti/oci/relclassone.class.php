<?php
require_once (dirname(dirname(__FILE__)) . '/relclassone.class.php');
class relClassOne_oci extends relClassOne {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}