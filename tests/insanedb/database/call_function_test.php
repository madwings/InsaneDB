<?php

/**
 * call_function() platform agnostic wrapper
 */
class Call_function_test extends CI_TestCase {

	public function test_pdo_prefix()
	{
		$db = Mock_Database_Fake_Mysql::create(array('db_debug' => FALSE));

		foreach (array('drivers', 'pdo_drivers', 'PDO_drivers') as $function)
		{
			$this->assertSame(pdo_drivers(), $db->call_function($function), $function);
		}
	}

	// ------------------------------------------------------------------------

	public function test_driver_prefix_is_case_insensitive()
	{
		if ( ! function_exists('mysqli_get_client_info'))
		{
			$this->markTestSkipped('mysqli extension is not available');
		}

		$db = Mock_Database_Fake_Mysql::create(array('db_debug' => FALSE));
		$db->dbdriver = 'mysqli';

		foreach (array('get_client_info', 'mysqli_get_client_info', 'MYSQLI_get_client_info') as $function)
		{
			$this->assertSame(mysqli_get_client_info(), $db->call_function($function), $function);
		}
	}

	// ------------------------------------------------------------------------

	public function test_unsupported_function()
	{
		$db = Mock_Database_Fake_Mysql::create(array('db_debug' => FALSE));

		$this->assertFalse($db->call_function('missing_function'));
		$this->assertFalse($db->call_function('pdomissing'));
	}

}
