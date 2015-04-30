<?php

class DB_driver_test extends CI_TestCase {

	public function test_initialize()
	{
		$config = Mock_Database_DB::config(DB_DRIVER);
		sscanf(DB_DRIVER, '%[^/]/', $driver_name);
		$driver = $this->{$driver_name}($config[DB_DRIVER]);

		$this->assertTrue($driver->initialize());
	}

	protected function pdo($config)
	{
		return new Mock_Database_Drivers_PDO($config);
	}
}
