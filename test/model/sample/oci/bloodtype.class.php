<?php
require_once (dirname(dirname(__FILE__)) . '/bloodtype.class.php');
class BloodType_oci extends BloodType {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}