<?php
$xpdo_meta_map['xPDOSample']= array (
  'package' => 'sample',
  'version' => '1.1',
  'table' => 'xpdosample',
  'extends' => 'xPDOSimpleObject',
  'fields' => 
  array (
    'parent' => 0,
    'unique_varchar' => NULL,
    'varchar' => NULL,
    'text' => NULL,
    'timestamp' => 'CURRENT_TIMESTAMP',
    'unix_timestamp' => 0,
    'date_time' => NULL,
    'date' => NULL,
    'enum' => NULL,
    'password' => NULL,
    'integer' => NULL,
    'float' => 1.0123,
    'boolean' => NULL,
  ),
  'fieldMeta' => 
  array (
    'parent' => 
    array (
      'dbtype' => 'INTEGER',
      'phptype' => 'integer',
      'null' => false,
      'default' => 0,
    ),
    'unique_varchar2' => 
    array (
      'dbtype' => 'varchar2',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'index' => 'unique',
    ),
    'varchar2' => 
    array (
      'dbtype' => 'varchar2',
      'precision' => '100',
      'phptype' => 'string',
      'null' => false,
    ),
    'text' => 
    array (
      'dbtype' => 'varchar2',
      'precision' => '2000',
      'phptype' => 'string',
      'null' => true,
    ),
    'timestamp' => 
    array (
      'dbtype' => 'timestamp',
      'phptype' => 'timestamp',
      'null' => false,
      'default' => 'CURRENT_TIMESTAMP',
    ),
    'unix_timestamp' => 
    array (
      'dbtype' => 'INTEGER',
      'phptype' => 'integer',
      'null' => false,
      'default' => 0,
    ),
    'date_time' => 
    array (
      'dbtype' => 'varchar2',
      'precision' => '100',
      'phptype' => 'datetime',
      'null' => true,
    ),
    'date' => 
    array (
      'dbtype' => 'date',
      'phptype' => 'date',
      'null' => true,
    ),
    'enum' => 
    array (
      'dbtype' => 'varchar2',
      'precision' => '1',
      'phptype' => 'string',
      'null' => false,
      'attributes' => 'CHECK ("enum" IN(\'\',\'T\',\'F\'))',
    ),
    'password' => 
    array (
      'dbtype' => 'varchar2',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
    ),
    'integer' => 
    array (
      'dbtype' => 'INTEGER',
      'phptype' => 'integer',
      'null' => false,
    ),
    'float' => 
    array (
      'dbtype' => 'number',
      'precision' => '10,5',
      'phptype' => 'float',
      'null' => false,
      'default' => 1.0123,
    ),
    'boolean' => 
    array (
      'dbtype' => 'INTEGER',
      'phptype' => 'boolean',
      'null' => false,
    ),
  ),
  'indexes' => 
  array (
    'unique_varchar2' => 
    array (
      'alias' => 'unique_varchar2',
      'primary' => false,
      'unique' => true,
      'type' => 'BTREE',
      'columns' => 
      array (
        'unique_varchar2' => 
        array (
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
  ),
);
