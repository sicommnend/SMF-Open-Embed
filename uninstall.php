<?php
if (!defined('SMF'))
	require_once('SSI.php');

remove_integration_function('integrate_pre_include','$sourcedir/og.php');
remove_integration_function('integrate_bbc_codes','og_bbc');
remove_integration_function('integrate_modify_modifications','og_admin');
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}scheduled_tasks WHERE task = "og_prune"');
?>
