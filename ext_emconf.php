<?php

########################################################################
# Extension Manager/Repository config file for ext "commerce".
#
# Auto generated 03-10-2011 02:10
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Commerce',
	'description' => 'TYPO3 commerce shopping system',
	'category' => 'module',
	'shy' => 0,
	'version' => '0.12.7',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'TYPO3_version' => '4.2.0-0.0.0',
	'PHP_version' => '5.2.0-0.0.0',
	'module' => 'mod_main,mod_category,mod_access,mod_perftest,mod_orders,mod_systemdata,mod_statistic',
	'state' => 'beta',
	'uploadfolder' => 1,
	'createDirs' => 'uploads/tx_commerce/rte',
	'modify_tables' => 'tt_address,fe_users',
	'clearcacheonload' => 1,
	'lockType' => 'L',
	'author' => 'Ingo Schmitt,Volker Graubaum,Thomas Hempel',
	'author_email' => 'team@typo3-commerce.org',
	'author_company' => 'Marketing Factory Consulting GmbH,e-netconsulting KG,n@work Internet Informationssysteme GmbH',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
);

?>