<?php

/**
 * PDO connection options and connection failures
 *
 * Runs against Mock_Database_Fake_Server, where every connection remembers
 * the username and the PDO options it was opened with.
 */
class Pdo_connect_test extends CI_TestCase {

	/**
	 * @var Mock_Database_Fake_Server
	 */
	protected $server;

	// ------------------------------------------------------------------------

	protected function db(array $params = array(), $connect = TRUE)
	{
		$db = Mock_Database_Fake_Mysql::create($params, $connect);
		$this->server = $db->fake_server;
		return $db;
	}

	// ------------------------------------------------------------------------

	/**
	 * The init command option, as resolved by the driver
	 */
	protected function init_command_key()
	{
		return class_exists('Pdo\\Mysql') ? constant('Pdo\\Mysql::ATTR_INIT_COMMAND') : PDO::MYSQL_ATTR_INIT_COMMAND;
	}

	// ------------------------------------------------------------------------

	protected function options($index = 0)
	{
		$this->assertArrayHasKey($index, $this->server->connections);
		return $this->server->connections[$index]->options;
	}

	// ------------------------------------------------------------------------

	public function test_credentials_are_passed()
	{
		$this->db(array('username' => 'app_user'));

		$this->assertEquals('app_user', $this->server->connections[0]->username);
	}

	// ------------------------------------------------------------------------

	public function test_error_mode_defaults_to_silent()
	{
		$this->db();

		$options = $this->options();
		$this->assertSame(PDO::ERRMODE_SILENT, $options[PDO::ATTR_ERRMODE]);
	}

	// ------------------------------------------------------------------------

	public function test_configured_error_mode_is_kept()
	{
		$this->db(array('options' => array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)));

		$options = $this->options();
		$this->assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
	}

	// ------------------------------------------------------------------------

	public function test_other_options_are_passed_through()
	{
		$this->db(array('options' => array(PDO::ATTR_TIMEOUT => 5)));

		$options = $this->options();
		$this->assertSame(5, $options[PDO::ATTR_TIMEOUT]);
	}

	// ------------------------------------------------------------------------

	public function test_persistent_connection()
	{
		$this->db(array('pconnect' => TRUE));
		$this->assertTrue($this->options()[PDO::ATTR_PERSISTENT]);

		$this->db(array('pconnect' => FALSE));
		$this->assertArrayNotHasKey(PDO::ATTR_PERSISTENT, $this->options());
	}

	// ------------------------------------------------------------------------

	public function test_db_pconnect()
	{
		$db = $this->db(array(), FALSE);

		$db->conn_id = $db->db_pconnect();

		$this->assertTrue($this->options()[PDO::ATTR_PERSISTENT]);
	}

	// ------------------------------------------------------------------------

	public function test_no_strict_mode_setting_by_default()
	{
		$this->db();

		$this->assertArrayNotHasKey($this->init_command_key(), $this->options());
	}

	// ------------------------------------------------------------------------

	public function test_strict_mode_enabled()
	{
		$this->db(array('stricton' => TRUE));

		$this->assertEquals(
			'SET SESSION sql_mode = CONCAT(@@sql_mode, ",", "STRICT_ALL_TABLES")',
			$this->options()[$this->init_command_key()]
		);
	}

	// ------------------------------------------------------------------------

	public function test_strict_mode_disabled()
	{
		$this->db(array('stricton' => FALSE));

		$command = $this->options()[$this->init_command_key()];
		$this->assertStringStartsWith('SET SESSION sql_mode = REPLACE(', $command);
		$this->assertStringContainsString('"STRICT_ALL_TABLES", ""', $command);
		$this->assertStringContainsString('"STRICT_TRANS_TABLES", ""', $command);
	}

	// ------------------------------------------------------------------------

	public function test_strict_mode_is_appended_to_a_configured_init_command()
	{
		$this->db(array(
			'stricton' => TRUE,
			'options' => array($this->init_command_key() => 'SET NAMES utf8mb4')
		));

		$this->assertEquals(
			'SET NAMES utf8mb4, @@session.sql_mode = CONCAT(@@sql_mode, ",", "STRICT_ALL_TABLES")',
			$this->options()[$this->init_command_key()]
		);
	}

	// ------------------------------------------------------------------------

	public function test_strict_mode_setting_is_not_repeated_on_reconnect()
	{
		foreach (array(array(), array($this->init_command_key() => 'SET NAMES utf8mb4')) as $options)
		{
			$db = $this->db(array('stricton' => TRUE, 'options' => $options));
			$expected = $this->options(0);

			$db->reconnect();
			$db->reconnect();

			$this->assertCount(3, $this->server->connections);
			$this->assertSame($expected, $this->options(2));
		}
	}

	// ------------------------------------------------------------------------

	public function test_refused_connection_returns_false()
	{
		$db = $this->db(array(), FALSE);
		$this->server->fail_connects = 1;

		$this->assertFalse($db->db_connect());
	}

	// ------------------------------------------------------------------------

	public function test_refused_connection_is_an_error_with_db_debug()
	{
		$db = $this->db(array('db_debug' => TRUE), FALSE);
		$this->server->fail_connects = 1;

		$this->setExpectedException('RuntimeException', 'Connection refused');
		$db->initialize();
	}

	// ------------------------------------------------------------------------

	public function test_refused_connection_is_not_an_error_when_a_failover_exists()
	{
		$db = $this->db(array(
			'db_debug' => TRUE,
			'failover' => array(array('hostname' => 'backup', 'username' => 'backup_user'))
		), FALSE);
		$this->server->fail_connects = 1;

		$db->initialize();

		$this->assertCount(1, $this->server->connections);
		$this->assertEquals('backup_user', $this->server->connections[0]->username);
	}

}
