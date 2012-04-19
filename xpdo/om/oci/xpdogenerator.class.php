<?php
/*
 * Copyright 2010-2012 by MODX, LLC.
 *
 * This file is part of xPDO.
 *
 * xPDO is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * xPDO is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * xPDO; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 */

/**
 * oci classes for generating xPDOObject classes and maps from an xPDO schema.
 *
 * @package xpdo
 * @subpackage om.oci
 */

/**
 * Include the parent {@link xPDOGenerator} class.
 */
include_once (dirname(dirname(__FILE__)) . '/xpdogenerator.class.php');

/**
 * An extension for generating {@link xPDOObject} class and map files for oci.
 *
 * A oci-specific extension to an {@link xPDOManager} instance that can
 * generate class stub and meta-data map files from a provided XML schema of a
 * database structure.
 *
 * @package xpdo
 * @subpackage om.oci
 */
class xPDOGenerator_oci extends xPDOGenerator {
    public function compile($path = '') {
        return false;
    }

    public function getIndex($index) {
        return '';
    }

    /**
     * Write an xPDO XML Schema from your database.
     *
     * @param string $schemaFile The name (including path) of the schemaFile you
     * want to write.
     * @param string $package Name of the package to generate the classes in.
     * @param string $baseClass The class which all classes in the package will
     * extend; by default this is set to {@link xPDOObject} and any
     * auto_increment fields with the column name 'id' will extend {@link
     * xPDOSimpleObject} automatically.
     * @param string $tablePrefix The table prefix for the current connection,
     * which will be removed from all of the generated class and table names.
     * Specify a prefix when creating a new {@link xPDO} instance to recreate
     * the tables with the same prefix, but still use the generic class names.
     * @param boolean $restrictPrefix Only reverse-engineer tables that have the
     * specified tablePrefix; if tablePrefix is empty, this is ignored.
     * @return boolean True on success, false on failure.
     */
     public function getDefault($value) {
        $return= '';
        $value = trim($value, "' ");
        if ($value !== null) {
            $return= ' default="'.$value.'"';
        }
        return $return;
    }
    public function writeSchema($schemaFile, $package= '', $baseClass= '', $tablePrefix= '', $restrictPrefix= false) {
        if (empty ($package))
            $package= $this->manager->xpdo->package;
        if (empty ($baseClass))
            $baseClass= 'xPDOObject';
        if (empty ($tablePrefix))
            $tablePrefix= $this->manager->xpdo->config[xPDO::OPT_TABLE_PREFIX];
        $schemaVersion = xPDO::SCHEMA_VERSION;
        $xmlContent = array();
        $xmlContent[] = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
        $xmlContent[] = "<model package=\"{$package}\" baseClass=\"{$baseClass}\" platform=\"oci\" version=\"{$schemaVersion}\">";
        //read list of tables
        $tableLike= ($tablePrefix && $restrictPrefix);
        if ($tableLike) {
            $tablesStmt= $this->manager->xpdo->query("SELECT * FROM user_tables WHERE table_name LIKE '{$tablePrefix}%' ORDER BY table_name");
            $tmpSmt = "SELECT * FROM user_tables WHERE table_name LIKE '{$tablePrefix}%' ORDER BY table_name";
        } else {
            $tablesStmt= $this->manager->xpdo->query("SELECT * FROM user_tables ORDER BY table_name");
            $tmpSmt = "SELECT * FROM user_tables ORDER BY table_name";
        }
        echo "<br>" . $tmpSmt . "</br>";
        $tables= $tablesStmt->fetchAll(PDO::FETCH_NUM);
        if ($this->manager->xpdo->getDebug() === true) $this->manager->xpdo->log(xPDO::LOG_LEVEL_DEBUG, print_r($tables, true));
        foreach ($tables as $table) {
            $xmlObject= array();
            $xmlFields= array();
            $xmlIndices= array();
            if (!$tableName= $this->getTableName($table[0], $tablePrefix, $restrictPrefix)) {
                continue;
            }
            $class= $this->getClassName($tableName);
            $extends= $baseClass;
            $fieldsStmt= $this->manager->xpdo->query("SELECT * FROM user_tab_cols WHERE table_name = '{$table[0]}'");
            $fields= $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($this->manager->xpdo->getDebug() === true) $this->manager->xpdo->log(xPDO::LOG_LEVEL_DEBUG, "Fields for table {$table[0]}: " . print_r($fields, true));
            $cid = 0;
            foreach ($fields as $field) {
                $column_name = '';
                $data_type = '';
                $nullable = 0;
                $data_default = null;
                $precision = " precision=\"%s\"";
                
                extract($field, EXTR_OVERWRITE);
                $Field= $COLUMN_NAME;
                $DataType = preg_replace('/\(\d\)$/i', '', $DATA_TYPE);
                $PhpType= $this->manager->xpdo->driver->getPhpType($DataType);
                $Null= ' null="' . (($NULLABLE == 'Y') ? 'true' : 'false') . '"';
                $Default= $this->getDefault($DATA_DEFAULT);
                
                // TODO: Needs refining
                if (!is_null($DATA_PRECISION) && !is_null($DATA_SCALE)) {
                    $precision = sprintf($precision, $DATA_PRECISION . "," . $DATA_SCALE);
                } else if (is_null($DATA_PRECISION) && !is_null($DATA_SCALE)) {
                    if ($PhpType == 'timestamp') {
                        $precision = sprintf($precision, $DATA_SCALE);
                    } else if ($PhpType == 'float') {
                        $precision = sprintf($precision, '*,'.$DATA_SCALE);
                    }
                } else if (!is_null($DATA_PRECISION)) {
                    $precision = sprintf($precision, $DATA_PRECISION);
                } else if ($PhpType == 'string') {
                    $precision = sprintf($precision, $DATA_LENGTH);
                } else {
                    $precision = sprintf($precision, '');
                } 
                if ($baseClass === 'xPDOObject' && $Field === 'id') {
                    $extends= 'xPDOSimpleObject';
                    continue;
                }
                $xmlFields[]= "\t\t<field key=\"{$Field}\" dbtype=\"{$DataType}\" phptype=\"{$PhpType}\"{$Null}{$Default}{$precision}/>";
                $cid++;
            }
            $indicesStmt= $this->manager->xpdo->query("SELECT * FROM user_indexes WHERE TABLE_NAME = '{$table[0]}'");
            $indices= $indicesStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($this->manager->xpdo->getDebug() === true) $this->manager->xpdo->log(xPDO::LOG_LEVEL_DEBUG, "Indices for table {$table[0]}: " . print_r($indices, true));
            foreach ($indices as $index) {
                $primary = preg_match('/_PRIMARY$/i', $index['INDEX_NAME']) ? 'true' : 'false';
                $unique = !empty($index['UNIQUENESS']) ? 'true' : 'false';
                $indexName = stristr($index['INDEX_NAME'], $class . "_") ? str_ireplace($class . '_', '', $index['INDEX_NAME']) : $index['INDEX_NAME'];

                $xmlIndices[]= "\t\t<index alias=\"{$indexName}\" name=\"{$index['INDEX_NAME']}\" primary=\"{$primary}\" unique=\"{$unique}\" type=\"{$index['INDEX_TYPE']}\">";
                $columnsStmt = $this->manager->xpdo->query("SELECT * FROM user_ind_columns WHERE index_name = '{$index['INDEX_NAME']}'");

                $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($this->manager->xpdo->getDebug() === true) $this->manager->xpdo->log(xPDO::LOG_LEVEL_DEBUG, "Columns of index {$index['INDEX_NAME']}: " . print_r($columns, true));
                foreach ($columns as $column) {
                    $xmlIndices[]= "\t\t\t<column key=\"{$column['COLUMN_NAME']}\" collation=\"{$column['DESCEND']}\"  />";
                }
                $xmlIndices[]= "\t\t</index>";
            }
            $xmlObject[] = "\t<object class=\"{$class}\" table=\"{$tableName}\" extends=\"{$extends}\">";
            $xmlObject[] = implode("\n", $xmlFields);
            if (!empty($xmlIndices)) {
                $xmlObject[] = '';
                $xmlObject[] = implode("\n", $xmlIndices);
            }
            $xmlObject[] = "\t</object>";
            $xmlContent[] = implode("\n", $xmlObject);
        }
        $xmlContent[] = "</model>";
        if ($this->manager->xpdo->getDebug() === true) {
           $this->manager->xpdo->log(xPDO::LOG_LEVEL_DEBUG, implode("\n", $xmlContent));
        }
        $file= fopen($schemaFile, 'wb');
        $written= fwrite($file, implode("\n", $xmlContent));
        fclose($file);
        return true;
    }
    
    public function getClassPlatformTemplate($platform) {
        if ($this->platformTemplate) return $this->platformTemplate;

        $template= <<<'EOD'
<?php
require_once (dirname(dirname(__FILE__)) . '/[+class-lowercase+].class.php');
class [+class+]_oci extends [+class+] {
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
EOD;
        return $template;
    }
}
