<?php

return array(

	// Typical Database configuration
	'pdo/mysql' => array(
		'dsn' => 'mysql:host=localhost;dbname=ci_test',
		'hostname' => 'localhost',
		'username' => 'travis',
		'password' => '',
		'database' => 'ci_test',
		'driver' => 'mysql'
	),

	// Database configuration with failover
	'pdo/mysql_failover' => array(
		'dsn' => '',
		'hostname' => 'localhost',
		'username' => 'not_travis',
		'password' => 'wrong password',
		'database' => 'not_ci_test',
		'driver' => 'mysql',
		'failover' => array(
			array(
				'dsn' => 'mysql:host=localhost;dbname=ci_test',
				'hostname' => 'localhost',
				'username' => 'travis',
				'password' => '',
				'database' => 'ci_test',
				'driver' => 'mysql'
			)
		)
	)
);