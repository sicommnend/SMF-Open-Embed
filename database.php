<?php
if (!defined('SMF'))
	require_once('SSI.php');

$smcFunc['db_create_table']('{db_prefix}og_cache',
	array(
		array(
			'name' => 'url',
			'type' => 'varchar',
			'size' => 255,
			'null' => false,
		),
		array(
			'name' => 'vars',
			'type' => 'text',
			'null' => false,
		),
		array(
			'name' => 'req_date',
			'type' => 'int',
			'size' => 10,
			'null' => false,
			'default' => 0,
		),
	),
	array(
		array(
			'name' => 'url',
			'type' => 'index',
			'columns' => array('url'),
		),
	), 'update'
);
?>
