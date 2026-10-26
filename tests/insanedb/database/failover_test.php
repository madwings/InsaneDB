<?php

/**
 * Connection failover
 *
 * Runs against Mock_Database_Fake_Server, which can be told to refuse the
 * next connection attempts.
 */
class Failover_test extends CI_TestCase {

	/**
	 * @var Mock_Database_Fake_Server
	 */
	protected $server;

	// ------------------------------------------------------------------------

	/**
	 * Driver with a primary and two failover hosts, not yet connected
	 */
	protected function db(array $params = array())
	{
		$db = Mock_Database_Fake_Mysql::create(array_merge(array(
			'hostname' => 'primary',
			'username' => 'primary_user',
			'failover' => array(
				array('hostname' => 'backup-1', 'username' => 'backup_user'),
				array('hostname' => 'backup-2', 'username' => 'backup_user')
			)
		), $params), FALSE);

		$this->server = $db->fake_server;
		return $db;
	}

	// ------------------------------------------------------------------------

	protected function host_of_connection($index)
	{
		$this->assertArrayHasKey($index, $this->server->connections);
		return preg_match('/host=([^;]+)/', $this->server->connections[$index]->dsn, $match) ? $match[1] : NULL;
	}

	// ------------------------------------------------------------------------

	public function test_primary_is_used_when_available()
	{
		$db = $this->db();

		$db->initialize();

		$this->assertCount(1, $this->server->connections);
		$this->assertEquals('primary', $this->host_of_connection(0));
		$this->assertEquals('primary_user', $db->username);
	}

	// ------------------------------------------------------------------------

	public function test_first_failover_is_used_when_primary_is_down()
	{
		$db = $this->db();
		$this->server->fail_connects = 1;

		$db->initialize();

		$this->assertCount(1, $this->server->connections);
		$this->assertEquals('backup-1', $this->host_of_connection(0));
		$this->assertSame($this->server->connections[0], $db->conn_id);

		// The failover settings replaced the primary ones, the rest is inherited
		$this->assertEquals('backup-1', $db->hostname);
		$this->assertEquals('backup_user', $db->username);
		$this->assertEquals('mysql:host=backup-1;dbname=ci_test;charset=utf8mb4', $db->dsn);
	}

	// ------------------------------------------------------------------------

	public function test_failover_with_empty_dsn_is_rebuilt_too()
	{
		$db = $this->db(array(
			'failover' => array(
				array('dsn' => '', 'hostname' => 'backup-1', 'port' => 3307)
			)
		));
		$this->server->fail_connects = 1;

		$db->initialize();

		$this->assertEquals('mysql:host=backup-1;port=3307;dbname=ci_test;charset=utf8mb4', $db->dsn);
	}

	// ------------------------------------------------------------------------

	public function test_primary_dsn_is_not_reused_for_a_failover()
	{
		$db = Mock_Database_Fake_Mysql::create(array(
			'dsn' => 'mysql:host=primary;dbname=ci_test',
			'failover' => array(
				array('hostname' => 'backup-1')
			)
		), FALSE);
		$this->server = $db->fake_server;
		$this->server->fail_connects = 1;

		$db->initialize();

		$this->assertEquals('backup-1', $this->host_of_connection(0));
	}

	// ------------------------------------------------------------------------

	public function test_failovers_are_tried_in_order()
	{
		$db = $this->db();
		$this->server->fail_connects = 2;

		$db->initialize();

		$this->assertCount(1, $this->server->connections);
		$this->assertEquals('backup-2', $this->host_of_connection(0));
	}

	// ------------------------------------------------------------------------

	public function test_failover_with_its_own_dsn()
	{
		$db = $this->db(array(
			'failover' => array(
				array('dsn' => 'mysql:host=dsn-host;dbname=other', 'hostname' => 'ignored')
			)
		));
		$this->server->fail_connects = 1;

		$db->initialize();

		$this->assertEquals('dsn-host', $this->host_of_connection(0));
		$this->assertEquals('mysql:host=dsn-host;dbname=other;charset=utf8mb4', $db->dsn);
	}

	// ------------------------------------------------------------------------

	public function test_exception_when_every_host_is_down()
	{
		$db = $this->db();
		$this->server->fail_connects = 3;

		try
		{
			$db->initialize();
			$this->fail('Expected a RuntimeException');
		}
		catch (RuntimeException $e)
		{
			$this->assertEquals('Unable to connect to the database.', $e->getMessage());
		}

		$this->assertFalse($db->conn_id);
		$this->assertCount(0, $this->server->connections);

		// The last failover settings remain active, so a later attempt starts from them
		$this->assertEquals('backup-2', $db->hostname);
	}

	// ------------------------------------------------------------------------

	public function test_no_failover_configured()
	{
		$db = Mock_Database_Fake_Mysql::create(array('hostname' => 'primary'), FALSE);
		$db->fake_server->fail_connects = 1;

		$this->setExpectedException('RuntimeException', 'Unable to connect to the database.');
		$db->initialize();
	}

	// ------------------------------------------------------------------------

	public function test_query_connects_through_failover()
	{
		$db = $this->db();
		$this->server->fail_connects = 1;
		$this->server->succeed(array(array('id' => 1)));

		$query = $db->query('SELECT id FROM job');

		$this->assertInstanceOf('CI_DB_result', $query);
		$this->assertEquals('backup-1', $this->host_of_connection(0));
		$this->assertSame(0, $this->server->entry('SELECT id FROM job')['conn']);
	}

	// ------------------------------------------------------------------------

	public function test_reconnect_after_failover_tries_the_failover_host_first()
	{
		$db = $this->db();
		$this->server->fail_connects = 1;
		$db->initialize();

		$db->reconnect();

		$this->assertCount(2, $this->server->connections);
		$this->assertEquals('backup-1', $this->host_of_connection(1));
	}

	// ------------------------------------------------------------------------

	public function test_failover_in_read_write_mode()
	{
		$db = Mock_Database_Fake_Mysql::create(array(
			'read' => array('dsn' => '', 'hostname' => 'read-host'),
			'write' => array('dsn' => '', 'hostname' => 'write-host'),
			'failover' => array(
				array('hostname' => 'read-backup')
			)
		), FALSE);
		$this->server = $db->fake_server;
		$this->server->fail_connects = 1;

		$db->initialize();
		$this->assertEquals('read-backup', $this->host_of_connection(0));

		// Writes still open their own connection to the write host
		$db->query('INSERT INTO job (id) VALUES (1)');
		$this->assertEquals('write-host', $this->host_of_connection(1));
		$this->assertSame(1, $this->server->entry('INSERT INTO job (id) VALUES (1)')['conn']);

		// Reads keep using the failover connection
		$db->query('SELECT 1');
		$this->assertSame(0, $this->server->entry('SELECT 1')['conn']);
		$this->assertCount(2, $this->server->connections);
	}

}
