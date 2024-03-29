<?php

return array(

	// Typical Database configuration
	'pdo/pgsql' => array(
		'dsn' => 'pgsql:host=localhost;port=5432;dbname=ci_test;',
		'hostname' => 'localhost',
		'username' => 'postgres',
		'password' => 'postgres',
		'database' => 'ci_test',
		'driver' => 'pgsql'
	),

	// Database configuration with failover
	'pdo/pgsql_failover' => array(
		'dsn' => '',
		'hostname' => 'localhost',
		'username' => 'not_travis',
		'password' => 'wrong password',
		'database' => 'not_ci_test',
		'driver' => 'pgsql',
		'failover' => array(
			array(
				'dsn' => 'pgsql:host=localhost;port=5432;dbname=ci_test;',
				'hostname' => 'localhost',
				'username' => 'postgres',
				'password' => 'postgres',
				'database' => 'ci_test',
				'driver' => 'pgsql'
			)
		)
	)
);