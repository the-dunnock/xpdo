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
 * The oci implementation of the xPDODriver class.
 *
 * @package xpdo
 * @subpackage om.oci
 */

/**
 * Include the parent {@link xPDODriver} class.
 */
require_once (dirname(dirname(__FILE__)) . '/xpdodriver.class.php');

/**
 * Provides oracle driver abstraction for an xPDO instance.
 *
 * This is baseline metadata and methods used throughout the framework.  xPDODriver 
 * class implementations are specific to a PDO driver and this instance is 
 * implemented for oracle.
 *
 * @package xpdo
 * @subpackage om.oracle
 */
class xPDODriver_oci extends xPDODriver {
    public $quoteChar = "'";
    public $escapeOpenChar = '"';
    public $escapeCloseChar = '"';
    public $_currentTimestamps= array (
        'SYSTIMESTAMP',
        'CURRENT_TIMESTAMP',
        'LOCALTIMESTAMP'
    );
    public $_currentDates= array (
        'SYSDATE',
        'CURRENT_DATE'
    );
    public $_currentTimes= array (
    );

    /**
     * Get a mysql xPDODriver instance.
     *
     * @param xPDO &$xpdo A reference to a specific xPDO instance.
     */
    function __construct(xPDO &$xpdo) {
        parent :: __construct($xpdo);
        $this->dbtypes['integer'] = array('/^INT/i', '/^NUMERIC$/i', '/^DECIMAL$/i'); //The NUMERIC and DECIMAL datatypes can specify only fixed-point numbers. For those datatypes, the scale (s) defaults to 0
        $this->dbtypes['float'] = array('/^FLOAT$/i', '/^NUMBER$/i', '/^DOUBLE PRECISION$/i', '/^REAL$/i');
        $this->dbtypes['string'] = array('/CHAR$/i', '/CHAR2$/i', '/^LONG$/i', '/CLOB$/i');
        $this->dbtypes['timestamp']= array('/^TIMESTAMP$/i');
        $this->dbtypes['date']= array('/^DATE$/i');
        $this->dbtypes['binary'] = array('/^BLOB$/i', '/^BINARY_FLOAT$/i', '/^BINARY_DOUBLE$/i');
        $this->dbtypes['bit'] = array('/RAW$/i');
    }
    
    public function lastInsertId($className = null, $column = null) {
        $return = false;
        if ($className) {
            if (!$column) {
                $column = $this->xpdo->getPK($className);
            }
            $className = $this->xpdo->getTableClass($className);
            $seqName = "{$className}_{$column}_seq";
            $sql = "SELECT \"{$seqName}\".CURRVAL FROM dual";
            $seqStmt = $this->xpdo->query($sql);
            if ($sequences = $seqStmt->fetchAll(PDO::FETCH_COLUMN)) {
                $sequence = reset($sequences);
                $sequence = intval($sequence);
                $return = $sequence;    
            }

            
        }
        return $return;
    }
}
