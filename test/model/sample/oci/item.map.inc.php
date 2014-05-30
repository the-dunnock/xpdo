<?php
$xpdo_meta_map['Item']= array (
  'package' => 'sample',
  'version' => '1.1',
  'table' => 'items',
  'extends' => 'xPDOSimpleObject',
  'fields' => 
  array (
    'name' => '',
    'color' => 'green',
    'description' => NULL,
    'date_modified' => 'CURRENT_TIMESTAMP',
  ),
  'fieldMeta' => 
  array (
    'name' => 
    array (
      'dbtype' => 'varchar2',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => '',
      'index' => 'fk',
    ),
    'color' => 
    array (
      'dbtype' => 'varchar2',
      'precision' => '255',
      'phptype' => 'string',
      'null' => false,
      'default' => 'green',
      'index' => 'fk',
    ),
    'description' => 
    array (
      'dbtype' => 'varchar2',
      'precision' => '2000',
      'phptype' => 'string',
      'null' => true,
    ),
    'date_modified' => 
    array (
      'dbtype' => 'timestamp',
      'phptype' => 'timestamp',
      'null' => false,
      'default' => 'CURRENT_TIMESTAMP',
    ),
  ),
);
