<?php

/**
 * PDO MySQL DSN construction
 *
 * The drivers are created without connecting, so only the constructor
 * (CI_DB_pdo_driver::__construct() and CI_DB_pdo_mysql_driver::_build_dsn())
 * is exercised.
 */
class Pdo_mysql_dsn_test extends CI_TestCase {

	protected function dsn(array $params)
	{
		return Mock_Database_Fake_Mysql::create($params, FALSE)->dsn;
	}

	// ------------------------------------------------------------------------

	public function test_built_from_settings()
	{
		$this->assertEquals(
			'mysql:host=db-host;dbname=ci_test;charset=utf8mb4',
			$this->dsn(array('hostname' => 'db-host'))
		);
	}

	// ------------------------------------------------------------------------

	public function test_port()
	{
		$this->assertEquals(
			'mysql:host=db-host;port=3307;dbname=ci_test;charset=utf8mb4',
			$this->dsn(array('hostname' => 'db-host', 'port' => 3307))
		);
	}

	// ------------------------------------------------------------------------

	public function test_empty_hostname_defaults_to_loopback()
	{
		$this->assertEquals(
			'mysql:host=127.0.0.1;dbname=ci_test;charset=utf8mb4',
			$this->dsn(array('hostname' => ''))
		);
	}

	// ------------------------------------------------------------------------

	public function test_without_database()
	{
		$this->assertEquals(
			'mysql:host=db-host;charset=utf8mb4',
			$this->dsn(array('hostname' => 'db-host', 'database' => ''))
		);
	}

	// ------------------------------------------------------------------------

	public function test_custom_charset()
	{
		$this->assertEquals(
			'mysql:host=db-host;dbname=ci_test;charset=latin1',
			$this->dsn(array('hostname' => 'db-host', 'char_set' => 'latin1'))
		);
	}

	// ------------------------------------------------------------------------

	public function test_without_charset()
	{
		$this->assertEquals(
			'mysql:host=db-host;dbname=ci_test',
			$this->dsn(array('hostname' => 'db-host', 'char_set' => ''))
		);
	}

	// ------------------------------------------------------------------------

	public function test_given_dsn_gets_the_charset_appended()
	{
		$this->assertEquals(
			'mysql:host=dsn-host;dbname=other;charset=utf8mb4',
			$this->dsn(array('dsn' => 'mysql:host=dsn-host;dbname=other', 'hostname' => 'ignored'))
		);
	}

	// ------------------------------------------------------------------------

	public function test_given_dsn_with_charset_is_kept()
	{
		foreach (array('mysql:host=dsn-host;charset=latin1', 'mysql:charset=latin1;host=dsn-host') as $dsn)
		{
			$this->assertSame($dsn, $this->dsn(array('dsn' => $dsn)), $dsn);
		}
	}

	// ------------------------------------------------------------------------

	public function test_given_dsn_without_configured_charset_is_kept()
	{
		$this->assertSame(
			'mysql:host=dsn-host;dbname=other',
			$this->dsn(array('dsn' => 'mysql:host=dsn-host;dbname=other', 'char_set' => ''))
		);
	}

	// ------------------------------------------------------------------------

	public function test_subdriver_is_detected_from_the_dsn()
	{
		$db = Mock_Database_Fake_Mysql::create(array('dsn' => 'mysql:host=dsn-host'), FALSE);

		$this->assertEquals('mysql', $db->subdriver);
	}

	// ------------------------------------------------------------------------

	public function test_invalid_dsn_is_replaced()
	{
		$this->assertEquals(
			'mysql:host=db-host;dbname=ci_test;charset=utf8mb4',
			$this->dsn(array('dsn' => 'not a dsn', 'hostname' => 'db-host'))
		);
	}

	// ------------------------------------------------------------------------

	public function test_read_write_connections_build_their_own_dsn()
	{
		$db = Mock_Database_Fake_Mysql::create(array(
			'read' => array('dsn' => '', 'hostname' => 'read-host', 'port' => 3307),
			'write' => array('dsn' => '', 'hostname' => 'write-host', 'database' => 'other')
		));

		$db->query('INSERT INTO job (id) VALUES (1)');

		$this->assertEquals('mysql:host=read-host;port=3307;dbname=ci_test;charset=utf8mb4', $db->fake_server->connections[0]->dsn);
		// The write settings replace the read ones, so the port is inherited
		$this->assertEquals('mysql:host=write-host;port=3307;dbname=other;charset=utf8mb4', $db->fake_server->connections[1]->dsn);
	}

}
