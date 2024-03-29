<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| DATABASE CONNECTIVITY SETTINGS
| -------------------------------------------------------------------
| This file will contain the settings needed to access your database.
|
| For complete instructions please consult the 'Database Connection'
| page of the User Guide.
|
| -------------------------------------------------------------------
| EXPLANATION OF VARIABLES
| -------------------------------------------------------------------
|
|	['dsn']      The full DSN string describe a connection to the database.
|	['hostname'] The hostname of your database server.
|	['username'] The username used to connect to the database
|	['password'] The password used to connect to the database
|	['database'] The name of the database you want to connect to
|	['read'] 	 Database credentials for read connection in read/write mode
|	['write'] 	 Database credentials for write connection in read/write mode
|	['driver'] 	 The database driver. e.g.: mysql.
|	['dbprefix'] You can add an optional prefix, which will be added
|				 to the table name when using the  Query Builder class
|	['pconnect'] TRUE/FALSE - Whether to use a persistent connection
|	['db_debug'] TRUE/FALSE - Whether database errors should be displayed.
|	['cache_on'] TRUE/FALSE - Enables/disables query caching
|	['cachedir'] The path to the folder where cache files should be stored
|	['char_set'] The character set used in communicating with the database
|	['dbcollat'] The character collation used in communicating with the database
|	['swap_pre'] A default table prefix that should be swapped with the dbprefix
|	['options']  array - Pass additional PDO options to the driver.
|	['stricton'] TRUE/FALSE - forces 'Strict Mode' connections
|							- good for ensuring strict SQL while developing
|	['failover'] array - A array with 0 or more data for connections if the main should fail.
|	['save_queries'] TRUE/FALSE - Whether to "save" all executed queries.
| 				NOTE: Disabling this will also effectively disable both
| 				$this->db->last_query() and profiling of DB queries.
| 				When you run a query, with this setting set to TRUE (default),
| 				CodeIgniter will store the SQL statement for debugging purposes.
| 				However, this may cause high memory usage, especially if you run
| 				a lot of SQL queries ... disable this to avoid that problem.
|	['query_retries'] How many time to retry the query. In case the query fails with a retryable error.
|	['read_delay'] How many seconds after the last write query to delay reads from read connection.
|				   In the delay period all read queries will go to the write connection. Only used
|				   in read/write mode.
|
| The $active_group variable lets you choose which connection group to
| make active.  By default there is only one group (the 'default' group).
*/
$active_group = 'default';
$db['default'] = array(
	'dsn'	=> '',
	'hostname'  => 'localhost',
	'username'  => '',
	'password'  => '',
	'database'  => '',
	'driver' 	=> 'mysql',
	'dbprefix'  => '',
	'pconnect'  => FALSE,
	'db_debug'  => (ENVIRONMENT !== 'production'),
	'cache_on'  => FALSE,
	'cachedir'  => '',
	'char_set'  => 'utf8mb4',
	'dbcollat'  => 'utf8mb4_bin',
	'swap_pre'  => '',
	'options'   => array(),
	'stricton'  => FALSE,
	'failover'  => array(),
	'save_queries'	=> FALSE,
	'query_retries'	=> 2
);

$db['read_write'] = array(
	'read' 	=> array(
		'dsn'	=> '',
		'hostname' => 'localhost'
	),
	'write' => array(
		'dsn'	=> '',
		'hostname' => 'localhost'
	),
	'username' 	=> '',
	'password' 	=> '',
	'database'  => '',
	'driver' 	=> 'mysql',
	'dbprefix'  => '',
	'pconnect'  => FALSE,
	'db_debug'  => (ENVIRONMENT !== 'production'),
	'cache_on'  => FALSE,
	'cachedir'  => '',
	'char_set'  => 'utf8mb4',
	'dbcollat'  => 'utf8mb4_bin',
	'swap_pre'  => '',
	'options'   => array(),
	'stricton'  => FALSE,
	'failover'  => array(),
	'save_queries'	=> FALSE,
	'query_retries'	=> 2,
	'read_delay'	=> 0
);
