<?php
require_once (dirname(dirname(__FILE__)) . '/item.class.php');
class Item_oci extends Item {
    public function save($cacheFlag= null) {
        $saved = xPDOObject_oci::save($cacheFlag);
        if ($saved)
            $saved = parent::save($cacheFlag);
        return $saved;
    }
}