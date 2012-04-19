<?php
require_once (dirname(dirname(__FILE__)) . '/item.class.php');
class Item_oci extends Item {
    public function __construct(xPDO & $xpdo) {
        parent::__construct($xpdo);
    }
    
    public function save($cacheFlag= null) {
        if ($this->isLazy()) {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, 'Attempt to save lazy object: ' . print_r($this->toArray('', true), 1));
            return false;
        }
        $result= true;
        $sql= '';
        $pk= $this->getPrimaryKey();
        $pkn= $this->getPK();
        $pkGenerated= false;
        if ($this->isNew()) {
            $this->setDirty();
        }
        if ($this->getOption(xPDO::OPT_VALIDATE_ON_SAVE)) {
            if (!$this->validate()) {
                return false;
            }
        }
        if (!$this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get connection for writing data", '', __METHOD__, __FILE__, __LINE__);
            return false;
        }
        $this->_saveRelatedObjects();
        if (!empty ($this->_dirty)) {
            $cols= array ();
            $bindings= array ();
            $updateSql= array ();
            $placeholders = array();
            foreach (array_keys($this->_dirty) as $_k) {
                if (!array_key_exists($_k, $this->_fieldMeta)) {
                    continue;
                }
                if (isset($this->_fieldMeta[$_k]['generated'])) {
                    if (!$this->_new || !isset($this->_fields[$_k]) || empty($this->_fields[$_k])) {
                        $pkGenerated= true;
                        continue;
                    }
                }
                if ($this->_fieldMeta[$_k]['phptype'] === 'password') {
                    $this->_fields[$_k]= $this->encode($this->_fields[$_k], 'password');
                }
                $fieldType= PDO::PARAM_STR;
                $fieldValue= $this->_fields[$_k];
                if (in_array($this->_fieldMeta[$_k]['phptype'], array ('datetime', 'timestamp')) && !empty($this->_fieldMeta[$_k]['attributes']) && $this->_fieldMeta[$_k]['attributes'] == 'ON UPDATE CURRENT_TIMESTAMP') {
                    $this->_fields[$_k]= strftime('%Y-%m-%d %H:%M:%S');
                    continue;
                }
                elseif ($fieldValue === null || $fieldValue === 'NULL') {
                    if ($this->_new) continue;
                    $fieldType= PDO::PARAM_NULL;
                    $fieldValue= null;
                }
                elseif (in_array($this->_fieldMeta[$_k]['phptype'], array ('timestamp', 'datetime')) && in_array($fieldValue, $this->xpdo->driver->_currentTimestamps, true)) {
                    $this->_fields[$_k]= strftime('%Y-%m-%d %H:%M:%S');
                    continue;
                }
                elseif (in_array($this->_fieldMeta[$_k]['phptype'], array ('date')) && in_array($fieldValue, $this->xpdo->driver->_currentDates, true)) {
                    $this->_fields[$_k]= strftime('%Y-%m-%d');
                    continue;
                }
                elseif ($this->_fieldMeta[$_k]['phptype'] == 'timestamp' && preg_match('/int/i', $this->_fieldMeta[$_k]['dbtype'])) {
                    $fieldType= PDO::PARAM_INT;
                }
                elseif (!in_array($this->_fieldMeta[$_k]['phptype'], array ('string','password','datetime','timestamp','date','time','array','json'))) {
                    $fieldType= PDO::PARAM_INT;
                }
                if ($this->_fieldMeta[$_k]['dbtype'] == 'timestamp') {
                    $placeholders[$_k] = "to_date(:\"{$_k}\", 'yyyy-mm-dd HH24:MI:SS')";
                } else if ($this->_fieldMeta[$_k]['dbtype'] == 'date'){
                    $placeholders[$_k] = "to_date(:\"{$_k}\", 'yyyy-mm-dd')";
                } else {
                    $placeholders[$_k] = ":\"{$_k}\"";
                }
                if ($this->_new) {
                    $cols[$_k]= $this->xpdo->escape($_k);
                    $bindings[":\"{$_k}\""]['value']= $fieldValue;
                    $bindings[":\"{$_k}\""]['type']= $fieldType;
                } else {
                    $bindings[":\"{$_k}\""]['value']= $fieldValue;
                    $bindings[":\"{$_k}\""]['type']= $fieldType;
                    
                    $updateSql[]= $this->xpdo->escape($_k) . " = $placeholders[$_k]";
                }
            }
            if ($this->_new) {
                $sql= "INSERT INTO {$this->_table} (" . implode(', ', array_values($cols)) . ") VALUES (" . implode(', ', $placeholders) . ")";
            } else {
                if ($pk && $pkn) {
                    if (is_array($pkn)) {
                        $iteration= 0;
                        $where= '';
                        foreach ($pkn as $k => $v) {
                            $vt= PDO::PARAM_INT;
                            if ($this->_fieldMeta[$k]['phptype'] == 'string') {
                                $vt= PDO::PARAM_STR;
                            }
                            if ($iteration) {
                                $where .= " AND ";
                            }
                            $where .= $this->xpdo->escape($k) . " = :\"{$k}\"";
                            $bindings[":\"{$k}\""]['value']= $this->_fields[$k];
                            $bindings[":\"{$k}\""]['type']= $vt;
                            $iteration++;
                        }
                    } else {
                        $pkn= $this->getPK();
                        $pkt= PDO::PARAM_INT;
                        if ($this->_fieldMeta[$pkn]['phptype'] == 'string') {
                            $pkt= PDO::PARAM_STR;
                        }
                        $bindings[":\"{$pkn}\""]['value']= $pk;
                        $bindings[":\"{$pkn}\""]['type']= $pkt;
                        $where= $this->xpdo->escape($pkn) . ' = :"' . $pkn . '"';
                    }
                    if (!empty ($updateSql)) {
                        $sql= "UPDATE {$this->_table} SET " . implode(',', $updateSql) . " WHERE {$where}";
                    }
                }
            }
            if (!empty ($sql) && $criteria= new xPDOCriteria($this->xpdo, $sql)) {
                if ($criteria->prepare()) {
                    if (!empty ($bindings)) {
                        $criteria->bind($bindings, true, false);
                    }
                    if ($this->xpdo->getDebug() === true) $this->xpdo->log(xPDO::LOG_LEVEL_DEBUG, "Executing SQL:\n{$sql}\nwith bindings:\n" . print_r($bindings, true));
                    if (!$result= $criteria->stmt->execute()) {
                        $errorInfo= $criteria->stmt->errorInfo();
                        $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error " . $criteria->stmt->errorCode() . " executing statement:\n" . $criteria->toSQL() . "\n" . print_r($errorInfo, true));
                        if (($errorInfo[1] == '1146' || $errorInfo[1] == '1') && $this->getOption(xPDO::OPT_AUTO_CREATE_TABLES)) {
                            if ($this->xpdo->getManager() && $this->xpdo->manager->createObjectContainer($this->_class) === true) {
                                if (!$result= $criteria->stmt->execute()) {
                                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error " . $criteria->stmt->errorCode() . " executing statement:\n{$sql}\n");
                                }
                            } else {
                                $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error " . $this->xpdo->errorCode() . " attempting to create object container for class {$this->_class}:\n" . print_r($this->xpdo->errorInfo(), true));
                            }
                        }
                    }
                } else {
                    $result= false;
                }
                if ($result) {
                    if ($pkn && !$pk) {
                        if ($pkGenerated) {
                            $this->_fields[$this->getPK()]= $this->xpdo->lastInsertId($this->_class, $this->getPK());
                        }
                        $pk= $this->getPrimaryKey();
                    }
                    if ($pk || !$this->getPK()) {
                        $this->_dirty= array();
                        $this->_validated= array();
                        $this->_new= false;
                    }
                    $callback = $this->getOption(xPDO::OPT_CALLBACK_ON_SAVE);
                    if ($callback && is_callable($callback)) {
                        call_user_func($callback, array('className' => $this->_class, 'criteria' => $criteria, 'object' => $this));
                    }
                    if ($this->xpdo->_cacheEnabled && $pk && ($cacheFlag || ($cacheFlag === null && $this->_cacheFlag))) {
                        $cacheKey= $this->xpdo->newQuery($this->_class, $pk, $cacheFlag);
                        if (is_bool($cacheFlag)) {
                            $expires= 0;
                        } else {
                            $expires= intval($cacheFlag);
                        }
                        $this->xpdo->toCache($cacheKey, $this, $expires, array('modified' => true));
                    }
                }
            }
        }
        $this->_saveRelatedObjects();
        if ($result) {
            $this->_dirty= array ();
            $this->_validated= array ();
        }
        parent::save($cacheFlag);
        return $result;
    }  
}