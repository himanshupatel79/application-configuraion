application-configuraion
========================
\bootstrap              => This is a base directory  for new application configuration changes
    \common
        \classes\       => This directory includes all common classes
			\Backbone_Base.php => Contain all methods to work
        \functions\     => This directory includes all common functions (i.e. all_new.php, mysql_v3.php).
			\common_functions.php => Contain all functions of all_new.php
			\db_functions.php => Contains all functions of mysql_V3.php	
        \config.ini     => This file contain all cosmic  configuration based on environment
        \database.ini   => This file contain all cosmic  database and it’s credentials configuration based on environment
        \contants.php   => This file contain all static data which is required throughout application and scope do not required to change and  independents of environment
        \config.php     => This is base configuration file to load all config data, loading CONSTANTS and GLOBALS and Other logic. 
        \globals.php    => This file contain all GLOBALS of all_new.php 		
    \index.php			=> Main index file to load new application configuration changes.

-> renamed functions.php to the common_functions.php and mysqldb.php to db_functions.php
