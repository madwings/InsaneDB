<?php

/**
 * Read/Write connections mode
 *
 * Runs against Mock_Database_Fake_Server, where every connection remembers
 * the DSN (and so the host) it was opened with.
 */
class Read_write_test extends CI_TestCase {

	/**
	 * @var Mock_Database_Fake_Server
	 */
	protected $server;

	// ------------------------------------------------------------------------

	protected function db(array $params = array())
	{
		$db = Mock_Database_Fake_Mysql::create(array_merge(array(
			'read' => array('dsn' => '', 'hostname' => 'read-host'),
			'write' => array('dsn' => '', 'hostname' => 'write-host')
		), $params));

		$this->server = $db->fake_server;
		return $db;
	}

	// ------------------------------------------------------------------------

	/**
	 * Host of the connection the given query was executed on
	 */
	protected function host_of($sql)
	{
		$entry = $this->server->entry($sql);
		$this->assertNotNull($entry, 'Query was not executed: '.$sql);

		return preg_match('/host=([^;]+)/', $entry['dsn'], $match) ? $match[1] : NULL;
	}

	// ------------------------------------------------------------------------

	public function test_initialize_connects_to_read_host()
	{
		$this->db();

		$this->assertCount(1, $this->server->connections);
		$this->assertStringContainsString('host=read-host', $this->server->connections[0]->dsn);
		$this->assertStringContainsString('dbname=ci_test', $this->server->connections[0]->dsn);
	}

	// ------------------------------------------------------------------------

	public function test_shipped_config_without_top_level_dsn()
	{
		$deprecations = array();
		set_error_handler(function ($errno, $errstr) use (&$deprecations) {
			$deprecations[] = $errstr;
			return TRUE;
		}, E_DEPRECATED);

		try
		{
			$db = new Mock_Database_Fake_Mysql(array(
				'read' => array('dsn' => '', 'hostname' => 'read-host'),
				'write' => array('dsn' => '', 'hostname' => 'write-host'),
				'database' => 'ci_test',
				'fake_server' => new Mock_Database_Fake_Server()
			));
		}
		finally
		{
			restore_error_handler();
		}

		$this->assertEquals(array(), $deprecations);
		$db->initialize();
		$this->assertStringContainsString('host=read-host', $db->fake_server->connections[0]->dsn);
	}

	// ------------------------------------------------------------------------

	public function test_reads_and_writes_are_routed()
	{
		$db = $this->db();

		$db->query('SELECT 1');
		$db->query('INSERT INTO job (id) VALUES (1)');
		$db->query('SELECT 2');
		$db->query('UPDATE job SET id = 2');

		$this->assertEquals('read-host', $this->host_of('SELECT 1'));
		$this->assertEquals('write-host', $this->host_of('INSERT INTO job (id) VALUES (1)'));
		$this->assertEquals('read-host', $this->host_of('SELECT 2'));
		$this->assertEquals('write-host', $this->host_of('UPDATE job SET id = 2'));

		// Each connection is opened only once
		$this->assertCount(2, $this->server->connections);
	}

	// ------------------------------------------------------------------------

	public function test_conn_force_write_until_cleared()
	{
		$db = $this->db();

		$db->conn_force('write', FALSE);
		$db->query('SELECT 1');
		$db->query('SELECT 2');

		$db->conn_force_clear();
		$db->query('SELECT 3');

		$this->assertEquals('write-host', $this->host_of('SELECT 1'));
		$this->assertEquals('write-host', $this->host_of('SELECT 2'));
		$this->assertEquals('read-host', $this->host_of('SELECT 3'));
	}

	// ------------------------------------------------------------------------

	public function test_conn_force_read_until_cleared()
	{
		$db = $this->db();

		$db->conn_force('read', FALSE);
		$db->query('INSERT INTO job (id) VALUES (1)');

		$db->conn_force_clear();
		$db->query('INSERT INTO job (id) VALUES (2)');

		$this->assertEquals('read-host', $this->host_of('INSERT INTO job (id) VALUES (1)'));
		$this->assertEquals('write-host', $this->host_of('INSERT INTO job (id) VALUES (2)'));
	}

	// ------------------------------------------------------------------------

	public function test_conn_force_for_next_query_only()
	{
		$db = $this->db();

		$db->conn_force('write');
		$db->query('SELECT 1');
		$db->query('SELECT 2');

		$this->assertEquals('write-host', $this->host_of('SELECT 1'));
		$this->assertEquals('read-host', $this->host_of('SELECT 2'));
	}

	// ------------------------------------------------------------------------

	public function test_read_delay_sends_reads_to_write_connection()
	{
		$db = $this->db(array('read_delay' => 60));

		$db->query('SELECT 1');
		$db->query('INSERT INTO job (id) VALUES (1)');
		$db->query('SELECT 2');

		$this->assertEquals('read-host', $this->host_of('SELECT 1'));
		$this->assertEquals('write-host', $this->host_of('SELECT 2'));

		// Forcing the read connection overrides the delay
		$db->conn_force('read', FALSE);
		$db->query('SELECT 3');
		$this->assertEquals('read-host', $this->host_of('SELECT 3'));
	}

	// ------------------------------------------------------------------------

	public function test_no_read_delay_by_default()
	{
		$db = $this->db();

		$db->query('INSERT INTO job (id) VALUES (1)');
		$db->query('SELECT 1');

		$this->assertEquals('read-host', $this->host_of('SELECT 1'));
	}

	// ------------------------------------------------------------------------

	public function test_transaction_runs_on_write_connection()
	{
		$db = $this->db();

		$this->assertTrue($db->trans_start());
		$db->query('SELECT 1');
		$db->query('INSERT INTO job (id) VALUES (1)');
		$this->assertTrue($db->trans_complete());

		$db->query('SELECT 2');

		$this->assertEquals('write-host', $this->host_of('BEGIN'));
		$this->assertEquals('write-host', $this->host_of('SELECT 1'));
		$this->assertEquals('write-host', $this->host_of('COMMIT'));

		// The force is released after the transaction
		$this->assertEquals('read-host', $this->host_of('SELECT 2'));
	}

	// ------------------------------------------------------------------------

	public function test_nested_transactions_release_force_after_outermost()
	{
		$db = $this->db();

		$db->trans_start();
		$db->trans_start();
		$db->query('INSERT INTO job (id) VALUES (1)');
		$db->trans_complete();

		$db->query('SELECT 1');

		$db->trans_complete();
		$db->query('SELECT 2');

		$this->assertEquals('write-host', $this->host_of('SELECT 1'));
		$this->assertEquals('read-host', $this->host_of('SELECT 2'));
		$this->assertEquals(array('BEGIN', 'COMMIT'), array_values(array_intersect($this->server->queries(TRUE), array('BEGIN', 'COMMIT', 'ROLLBACK'))));
	}

	// ------------------------------------------------------------------------

	public function test_transaction_restores_previous_force()
	{
		$db = $this->db();

		$db->conn_force('read', FALSE);
		$db->trans_start();
		$db->query('INSERT INTO job (id) VALUES (1)');
		$db->trans_complete();

		$db->query('INSERT INTO job (id) VALUES (2)');

		$this->assertEquals('write-host', $this->host_of('INSERT INTO job (id) VALUES (1)'));
		$this->assertEquals('read-host', $this->host_of('INSERT INTO job (id) VALUES (2)'));
	}

	// ------------------------------------------------------------------------

	public function test_failed_transaction_rolls_back_on_write_connection()
	{
		$db = $this->db();

		$db->trans_start();
		$this->server->fail('23000', 1062);
		$db->query('INSERT INTO job (id) VALUES (1)');
		$this->assertFalse($db->trans_complete());

		$db->query('SELECT 1');

		$this->assertEquals('write-host', $this->host_of('ROLLBACK'));
		$this->assertEquals('read-host', $this->host_of('SELECT 1'));
	}

	// ------------------------------------------------------------------------

	public function test_failed_begin_restores_force()
	{
		$db = $this->db();
		$this->server->fail_begin = TRUE;

		$this->assertFalse($db->trans_begin());
		$this->assertFalse($db->trans_active());

		$db->query('SELECT 1');
		$this->assertEquals('read-host', $this->host_of('SELECT 1'));
	}

	// ------------------------------------------------------------------------

	public function test_failed_rollback_after_lost_connection_restores_force()
	{
		$db = $this->db();
		$this->server->rollback_outcome = 'throw';

		$db->trans_start();
		$this->server->fail('HY000', 2013);
		$this->assertFalse($db->query('INSERT INTO job (id) VALUES (1)'));
		$this->assertFalse($db->trans_complete());
		$this->assertFalse($db->trans_active());

		$db->query('SELECT 1');
		$this->assertEquals('read-host', $this->host_of('SELECT 1'));
	}

	// ------------------------------------------------------------------------

	public function test_close_single_connection()
	{
		$db = $this->db();

		$db->query('SELECT 1');
		$db->query('INSERT INTO job (id) VALUES (1)');
		$this->assertCount(2, $this->server->connections);

		$db->close('read');
		$this->assertFalse($db->conn_id_read);
		$this->assertNotEmpty($db->conn_id_write);

		$db->query('INSERT INTO job (id) VALUES (2)');
		$this->assertCount(2, $this->server->connections);

		$db->query('SELECT 2');
		$this->assertCount(3, $this->server->connections);
		$this->assertEquals('read-host', $this->host_of('SELECT 2'));
	}

	// ------------------------------------------------------------------------

	public function test_close_all_connections()
	{
		$db = $this->db();

		$db->query('SELECT 1');
		$db->query('INSERT INTO job (id) VALUES (1)');

		$db->close();
		$this->assertFalse($db->conn_id_read);
		$this->assertFalse($db->conn_id_write);
		$this->assertFalse($db->conn_id);

		$db->query('INSERT INTO job (id) VALUES (2)');
		$db->query('SELECT 2');

		$this->assertCount(4, $this->server->connections);
		$this->assertEquals('write-host', $this->host_of('INSERT INTO job (id) VALUES (2)'));
		$this->assertEquals('read-host', $this->host_of('SELECT 2'));
	}

	// ------------------------------------------------------------------------

	public function test_lost_write_connection_reconnects_to_write_host()
	{
		$db = $this->db();

		$db->query('SELECT 1');
		$this->server->fail('HY000', 2006);
		$this->assertNotFalse($db->query('INSERT INTO job (id) VALUES (1)'));

		$log = $this->server->log;
		$last = end($log);
		$this->assertEquals('INSERT INTO job (id) VALUES (1)', $last['sql']);
		$this->assertStringContainsString('host=write-host', $last['dsn']);
	}

}
