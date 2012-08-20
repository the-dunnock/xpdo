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
 * The oci implementation of the xPDOManager class.
 *
 * @package xpdo
 * @subpackage om.oci
 */

/**
 * Include the parent {@link xPDOManager} class.
 */
require_once (dirname(dirname(__FILE__)) . '/xpdomanager.class.php');

/**
 * Provides oci data source management for an xPDO instance.
 *
 * These are utility functions that only need to be loaded under special
 * circumstances, such as creating tables, adding indexes, altering table
 * structures, etc.  xPDOManager class implementations are specific to a
 * database driver and this instance is implemented for oci.
 *
 * @package xpdo
 * @subpackage om.oci
 */
class xPDOManager_oci extends xPDOManager {
    public function createSourceContainer($dsnArray = null, $username= null, $password= null, $containerOptions= array ()) {
        $return = false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $this->xpdo->log(xPDO::LOG_LEVEL_WARN, 'Oracle does not support source container creation');
            if ($dsnArray === null) $dsnArray = xPDO::parseDSN($this->xpdo->getOption('dsn'));
            if (is_array($dsnArray)) {
                try {
                    $db = end(explode("/", $dsnArray['dbname']));
                    $dbStmt = $this->xpdo->query("select ora_database_name from dual");
                    $dbs = $dbStmt->fetch(PDO::FETCH_ASSOC);
                    $dbStmt->closeCursor();
                    if (strtolower($dbs['ORA_DATABASE_NAME']) == strtolower($db))
                        $return = true;
                } catch (Exception $e) {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error creating source container: " . $e->getMessage());
                }
            }
        }
        return $return;
    }

    public function removeSourceContainer($dsnArray = null, $username= null, $password= null) {
        $return = false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $this->xpdo->log(xPDO::LOG_LEVEL_WARN, 'Oracle does not support source container removal');
            if ($dsnArray === null) $dsnArray = xPDO::parseDSN($this->xpdo->getOption('dsn'));
            if (is_array($dsnArray)) {
                try {
                    $db = end(explode("/", $dsnArray['dbname']));
                    $dbStmt = $this->xpdo->query("select ora_database_name from dual");
                    $dbs = $dbStmt->fetch(PDO::FETCH_ASSOC);
                    $dbStmt->closeCursor();
                    if (strtolower($dbs['ORA_DATABASE_NAME']) == strtolower($db))
                        $return = true;
                } catch (Exception $e) {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error removing source container: " . $e->getMessage());
                }
            }
        }
        return $return;
    }

    public function removeObjectContainer($className) {
        $removed= false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $instance= $this->xpdo->newObject($className);
            if ($instance) {
                $sql= 'DROP TABLE ' . $this->xpdo->getTableName($className);
                $removed= $this->xpdo->exec($sql);
                if ($removed === false && $this->xpdo->errorCode() !== '' && $this->xpdo->errorCode() !== PDO::ERR_NONE) {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, 'Could not drop table ' . $className . "\nSQL: {$sql}\nERROR: " . print_r($this->xpdo->pdo->errorInfo(), true));
                } else {
                    $removed= true;
                    $this->xpdo->log(xPDO::LOG_LEVEL_INFO, 'Dropped table ' . $className . "\nSQL: {$sql}\n");

                    $sql = "SELECT SEQUENCE_NAME FROM user_sequences WHERE SEQUENCE_NAME LIKE '{$this->xpdo->literal($this->xpdo->getTableName($className))}_%'";
                    $seqStmt = $this->xpdo->query($sql);
                    
                    $sequences = $seqStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach($sequences as $sequence) {
                        $sql = "DROP SEQUENCE \"{$sequence['SEQUENCE_NAME']}\"";
                        if ($this->xpdo->exec($sql) !== false)
                            $this->xpdo->log(xPDO::LOG_LEVEL_DEBUG, "Dropped sequence: \"{$sequence['SEQUENCE_NAME']}\" \nSQL: {$sql}\n");
                    }
                }
            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $removed;
    }

    public function createObjectContainer($className) {
        $created= false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $instance= $this->xpdo->newObject($className);
            if ($instance) {
                $tableName= $this->xpdo->getTableName($className);
                $existsStmt = $this->xpdo->query("SELECT COUNT(*) FROM {$tableName}");
                if ($existsStmt && $existsStmt->fetchAll()) {
                    return true;
                }
                $tableMeta= $this->xpdo->getTableMeta($className);
                $sql= 'CREATE TABLE ' . $tableName . ' (';
                $fieldMeta = $this->xpdo->getFieldMeta($className, true);
                $nativeGen = false;
                $columns = array();
                while (list($key, $meta)= each($fieldMeta)) {
                    $columns[] = $this->getColumnDef($className, $key, $meta);
                    if (array_key_exists('generated', $meta) && $meta['generated'] == 'native') {
                        $nativeGen = true;
                        $autoInc[] = array(
                            'className' => $className,
                            'column' => $key);
                    }
                    if (array_key_exists('attributes', $meta) && (preg_match("/ON UPDATE ([\w]+)/i", $meta['attributes'], $matches)) && in_array(strtoupper($matches[1]), array_merge($this->xpdo->driver->_currentTimestamps, $this->xpdo->driver->_currentDates))) {
                        $updateTriggers[] = array(
                            'column' => $key,
                            'className' => $className,
                            'dataType' => $matches[1]
                        );
                        unset($matches);
                    }
                    
                }
                $sql .= implode(', ', $columns);
                $indexes = $this->xpdo->getIndexMeta($className);
                $createIndices = array();
                $tableConstraints = array();
                if (!empty ($indexes)) {
                    foreach ($indexes as $indexkey => $indexdef) {
                        $indexType = ($indexdef['primary'] ? 'PRIMARY KEY' : ($indexdef['unique'] ? 'UNIQUE' : 'INDEX'));
                        $indexset = $this->getIndexDef($className, $indexkey, $indexdef);
                        $idxName = $this->xpdo->escape($this->xpdo->literal($tableName) . "_" . $name);
                        switch ($indexType) {
                            case 'INDEX':
                                $createIndices[$indexkey] = "CREATE INDEX {$idxName} ON {$tableName} ({$indexset})";
                                break;
                            case 'PRIMARY KEY':
                            case 'UNIQUE':
                            default:
                                $tableConstraints[]= "CONSTRAINT {$idxName} {$indexType} ({$indexset})";
                                break;
                        }
                    }
                }
                if (!empty($tableConstraints)) {
                    $sql .= ', ' . implode(', ', $tableConstraints);
                }
                $sql .= ")";
                $created= $this->xpdo->exec($sql);
                if ($created === false && $this->xpdo->errorCode() !== '' && $this->xpdo->errorCode() !== PDO::ERR_NONE) {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, 'Could not create table ' . $tableName . "\nSQL: {$sql}\nERROR: " . print_r($this->xpdo->errorInfo(), true));
                } else {
                    $created= true;
                    $this->xpdo->log(xPDO::LOG_LEVEL_INFO, 'Created table ' . $tableName . "\nSQL: {$sql}\n");
                }
                if ($created === true && !empty($createIndices)) {
                    foreach ($createIndices as $createIndexKey => $createIndex) {
                        $indexCreated = $this->xpdo->exec($createIndex);
                        if ($indexCreated === false && $this->xpdo->errorCode() !== '' && $this->xpdo->errorCode() !== PDO::ERR_NONE) {
                            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not create index {$createIndexKey}: {$createIndex} " . print_r($this->xpdo->errorInfo(), true));
                        } else {
                            $this->xpdo->log(xPDO::LOG_LEVEL_INFO, "Created index {$createIndexKey} on {$tableName}: {$createIndex}");
                        }
                    }
                }
                if ($created === true && $nativeGen === true && is_array($autoInc)) { // Sequence to mimic auto_increment
                    foreach($autoInc as $ai) {
                        $this->addSequenceTrigger($this->xpdo->getTableClass($ai['className']), $ai['column']);
                    }
                    if(!empty($updateTriggers)) {
                        foreach($updateTriggers as $trigger) {
                            $this->addUpdateTrigger($trigger['className'], $trigger['column'], $trigger['dataType']);
                        }
                    }
                }

            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $created;
    }

    public function alterObjectContainer($className, array $options = array()) {
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            // TODO: Implement alterObjectContainer() method.
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
    }

    public function addConstraint($class, $name, array $options = array()) {
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            // TODO: Implement addConstraint() method.
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
    }

    public function addField($class, $name, array $options = array()) {
        $result = false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->xpdo->loadClass($class);
            if ($className) {
                $meta = $this->xpdo->getFieldMeta($className, true);
                if (is_array($meta) && array_key_exists($name, $meta)) {
                    $colDef = $this->getColumnDef($className, $name, $meta[$name]);
                    if (!empty($colDef)) {
                        $sql = "ALTER TABLE {$this->xpdo->getTableName($className)} ADD {$colDef}";
                        if ($this->xpdo->exec($sql) !== false) {
                            $result = true;
                            if (array_key_exists('attributes', $meta) && (preg_match("/ON UPDATE ([\w]+)/i", $meta['attributes'], $matches)) && in_array(strtoupper($matches[1]), array_merge($this->xpdo->_currentTimestamps, $this->xpdo->_currentDates))) {
                                $this->addUpdateTrigger($className, $key, $matches[1]);
                                unset($matches);
                            }
                        } else {
                            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error adding field {$class}->{$name}: " . print_r($this->xpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                        }
                    } else {
                        $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error adding field {$class}->{$name}: Could not get column definition");
                    }
                } else {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error adding field {$class}->{$name}: No metadata defined");
                }
            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function addIndex($class, $name, array $options = array()) {
        $result = false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->xpdo->loadClass($class);
            if ($className) {
                $tableName= $this->xpdo->getTableName($className);
                $tableName= $this->xpdo->getTableName($className);
                $idxName = $this->xpdo->escape($this->xpdo->literal($tableName) . "_" . $name);
                $meta = $this->xpdo->getIndexMeta($className);
                if (is_array($meta) && array_key_exists($name, $meta)) {
                    $idxDef = $this->getIndexDef($className, $name, $meta[$name]);
                    if (!empty($idxDef)) {
                        $indexType = ($meta[$name]['primary'] ? 'PRIMARY KEY' : ($meta[$name]['unique'] ? 'UNIQUE' : 'INDEX'));
                        switch ($indexType) {
                            case 'PRIMARY KEY':
                            case 'UNIQUE':
                                $sql = "ALTER TABLE {$tableName} ADD CONSTRAINT {$idxName} {$indexType} ({$idxDef})";
                                break;
                            default:
                                $sql = "CREATE {$indexType}  {$idxName} ON {$tableName} ({$idxDef})";
                                break;
                        }
                        if ($this->xpdo->exec($sql) !== false) {
                            $result = true;
                        } else {
                            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error adding index {$name} to {$class}: " . print_r($this->xpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                        }
                    } else {
                        $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error adding index {$name} to {$class}: Could not get index definition");
                    }
                } else {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error adding index {$name} to {$class}: No metadata defined");
                }
            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }
    
    private function addSequenceTrigger($className, $column, $start = 1, $increment = 1, $cache = 20) {
        $created = false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $tableName = $this->xpdo->getTableName($className);
            $seqName = "{$this->xpdo->literal($tableName)}_{$column}_seq";
            $sql = "CREATE SEQUENCE {$this->xpdo->escape($seqName)} MINVALUE {$start} START WITH {$start} INCREMENT BY {$increment}";
            if (!is_int($cache) && $cache == "NOCACHE") { 
                $sql .= " NOCACHE";
            } else {
                $sql .= " CACHE {$cache}";
            }
            if ($this->xpdo->exec($sql) !== false) {
                $this->xpdo->log(xPDO::LOG_LEVEL_DEBUG, "Sequence {$seqName} created on {$tableName};\n SQL: " . $sql . "\n");
                $trigName = "{$this->xpdo->literal($tableName)}_{$column}_trig";
                $sql = "CREATE OR REPLACE TRIGGER {$this->xpdo->escape($trigName)}
BEFORE INSERT ON {$tableName}
FOR EACH ROW
BEGIN
    SELECT {$this->xpdo->escape($seqName)}.nextval INTO :new.{$this->xpdo->escape($column)} FROM dual;
END;";
                if ($this->xpdo->exec($sql) !== false) {
                    $this->xpdo->log(xPDO::LOG_LEVEL_DEBUG, "Trigger {$trigName} created on {$tableName};\n SQL: " . $sql . "\n");
                    $result = true;
                } else {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Unable to create trigger for {$tableName}: " . print_r($this->xpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                    $this->xpdo->exec("DROP SEQUENCE {$this->xpdo->escape($seqName)}");
                    $this->xpdo->log(xPDO::LOG_LEVEL_INFO, "Sequence {$seqName} dropped due to failed trigger ($trigName) creation.");
                    $result = false;
                }
            } else {
                $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Unable to create sequence for {$tableName}: " . print_r($this->xpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                $result = false;
            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }
    
    private function addUpdateTrigger($className, $column, $dataType) {
        $tableName = $this->xpdo->getTableName($className);
        $sql = "CREATE OR REPLACE TRIGGER {{$this->xpdo->literal($tableName)}.'_'.$column.'_'.update)}
BEFORE UPDATE ON {$tableName }
FOR EACH ROW
BEGIN
    IF NOT UPDATING (:new.{$this->xpdo->escape($column)}) THEN
        :new.{$this->xpdo->escape($column)} := {$dataType};
    END IF;
END;";
        if($this->xpdo->exec($sql) !== false) {
            $this->xpdo->log(xPDO::LOG_LEVEL_DEBUG, "ON UPDATE trigger created on {$tableName } for column {$column} with data type {$dataType}");
            $result = true;
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Unable to create ON UPDATE trigger for {$tableName}: " . print_r($this->xpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
            $result = false;
        }
        return $result;
    }
    
    public function alterField($class, $name, array $options = array()) {
        $result = false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->xpdo->loadClass($class);
            if ($className) {
                $meta = $this->xpdo->getFieldMeta($className, true);
                if (is_array($meta) && array_key_exists($name, $meta)) {
                    $colDef = $this->getColumnDef($className, $name, $meta[$name]);
                    if (!empty($colDef)) {
                        $sql = "ALTER TABLE {$this->xpdo->getTableName($className)} MODIFY ({$colDef})";
                        if ($this->xpdo->exec($sql) !== false) {
                            $result = true;
                        } else {
                            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error altering field {$class}->{$name}: " . print_r($this->xpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                        }
                    } else {
                        $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error altering field {$class}->{$name}: Could not get column definition");
                    }
                } else {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error altering field {$class}->{$name}: No metadata defined");
                }
            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function removeConstraint($class, $name, array $options = array()) {
        $result = false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->xpdo->loadClass($class);
            if ($className) {
                $tableName= $this->xpdo->getTableName($className);
                $tableName= $this->xpdo->getTableName($className);
                $idxName = $this->xpdo->escape($this->xpdo->literal($tableName) . "_" . $name);
                $sql = "ALTER TABLE {$tableName} DROP CONSTRAINT {$idxName}";
                $result = $this->xpdo->exec($sql);
                if ($result !== false || (!$result && $this->xpdo->errorCode() === '00000')) {
                    $result = true;
                } else {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error removing field {$class}->{$name}: " . print_r($this->xpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                }
            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function removeField($class, $name, array $options = array()) {
        $result = false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->xpdo->loadClass($class);
            if ($className) {
                $sql = "ALTER TABLE {$this->xpdo->getTableName($className)} DROP COLUMN {$this->xpdo->escape($name)}";
                $result = $this->xpdo->exec($sql);
                if ($result !== false || (!$result && $this->xpdo->errorCode() === '00000')) {
                    $result = true;
                } else {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error removing field {$class}->{$name}: " . print_r($this->xpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                }
            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function removeIndex($class, $name, array $options = array()) {
        $result = false;
        if ($this->xpdo->getConnection(array(xPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->xpdo->loadClass($class);
            if ($className) {
                $tableName= $this->xpdo->getTableName($className);
                $idxName = $this->xpdo->escape($this->xpdo->literal($tableName) . "_" . $name);
                $indexType = (isset($options['type']) && in_array($options['type'], array('PRIMARY KEY', 'UNIQUE', 'INDEX', 'FULLTEXT')) ? $options['type'] : 'INDEX');
                switch ($indexType) {
                    case 'PRIMARY KEY':
                    case 'UNIQUE':
                        $sql = "ALTER TABLE {$tableName} DROP CONSTRAINT {$idxName}";
                        break;
                    default:
                        $sql = "DROP INDEX {$idxName} ON {$this->xpdo->getTableName($className)}";
                        break;
                }
                $result = $this->xpdo->exec($sql);
                if ($result !== false || (!$result && $this->xpdo->errorCode() === '00000')) {
                    $result = true;
                } else {
                    $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Error removing index {$name} from {$class}: " . print_r($this->xpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                }
            }
        } else {
            $this->xpdo->log(xPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    protected function getColumnDef($class, $name, $meta, array $options = array()) {
        $pk= $this->xpdo->getPK($class);
        $pktype= $this->xpdo->getPKType($class);
        $dbtype= strtoupper($meta['dbtype']);
        $precision= (isset ($meta['precision']) && !in_array($this->xpdo->driver->getPHPType($dbtype), array('integer', 'bit', 'date', 'datetime', 'time'))) ? '(' . $meta['precision'] . ')' : '';

        $notNull= !isset ($meta['null']) ? false : ($meta['null'] === 'false' || empty($meta['null']));
        $null= $notNull ? ' NOT NULL' : ' NULL';
        $extra = '';
        if (empty ($extra) && isset ($meta['extra'])) {
            $extra= ' ' . $meta['extra'];
        }
        $default= '';
        if (array_key_exists('default', $meta)) {
            $defaultVal= $meta['default'];
            if ($defaultVal === null || strtoupper($defaultVal) === 'NULL' || in_array($this->xpdo->driver->getPHPType($dbtype), array('integer', 'float', 'bit')) || (in_array($this->xpdo->driver->getPHPType($dbtype), array('datetime', 'date', 'timestamp', 'time')) && in_array($defaultVal, array_merge($this->xpdo->driver->_currentTimestamps, $this->xpdo->driver->_currentDates, $this->xpdo->driver->_currentTimes)))) {
                $default= ' DEFAULT ' . $defaultVal;
            } else {
                $default= ' DEFAULT \'' . $defaultVal . '\'';
            }
        }
        $result = $this->xpdo->escape($name) . ' ' . $dbtype . $precision . $default . $null . $extra;
        return $result;
    }

    protected function getIndexDef($class, $name, $meta, array $options = array()) {
        $result = '';
        $index = isset($meta['columns']) ? $meta['columns'] : null;
        if (is_array($index)) {
            $indexset= array ();
            foreach ($index as $indexmember => $indexmemberdetails) {
                $indexMemberDetails = $this->xpdo->escape($indexmember);
                $indexset[]= $indexMemberDetails;
            }
            $result= implode(',', $indexset);
        }
        return $result;
    }  
}
