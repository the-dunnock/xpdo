<?php
require_once (dirname(dirname(__FILE__)) . '/person.class.php');
class Person_oci extends Person {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}