<?php
require_once (dirname(dirname(__FILE__)) . '/relclassmany.class.php');
class relClassMany_oci extends relClassMany {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}