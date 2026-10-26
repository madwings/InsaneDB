<?php

/**
 * CI_DB_driver::error() on the PDO drivers
 *
 * Runs against Mock_Database_Fake_Server.
 */
class Error_test extends CI_TestCase {

	/**
	 * @var Mock_Database_Fake_Server
	 */
	protected $server;

	// ------------------------------------------------------------------------

	protected function db(array $params = array())
	{
		$db = Mock_Database_Fake_Mysql::create($params);
		$this->server = $db->fake_server;
		return $db;
	}

	// ------------------------------------------------------------------------

	public function test_no_error_on_fresh_connection()
	{
		$db = $this->db();

		$this->assertEquals(array('code' => '00000', 'message' => ''), $db->error());
	}

	// ------------------------------------------------------------------------

	public function test_error_of_failed_query()
	{
		$db = $this->db();
		$this->server->fail('42S02', 1146);

		$this->assertFalse($db->query('SELECT * FROM missing'));
		$this->assertEquals(array('code' => '42S02/1146', 'message' => 'Fake error 42S02/1146'), $db->error());
	}

	// ------------------------------------------------------------------------

	public function test_error_is_cleared_by_a_successful_query()
	{
		$db = $this->db();
		$this->server->fail('42S02', 1146);
		$this->server->succeed();

		$db->query('SELECT * FROM missing');
		$db->query('SELECT 1');

		$this->assertEquals(array('code' => '00000', 'message' => ''), $db->error());
	}

	// ------------------------------------------------------------------------

	public function test_error_without_a_connection()
	{
		$db = $this->db();
		$db->close();

		$this->assertFalse($db->conn_id);
		$this->assertEquals(array('code' => '00000', 'message' => ''), $db->error());
	}

	// ------------------------------------------------------------------------

	public function test_error_before_connecting()
	{
		$db = Mock_Database_Fake_Mysql::create(array(), FALSE);

		$this->assertEquals(array('code' => '00000', 'message' => ''), $db->error());
	}

	// ------------------------------------------------------------------------

	public function test_error_after_failed_reconnect()
	{
		$db = $this->db(array('query_retries' => 1));
		$this->server->fail('HY000', 2006);
		$this->server->fail_connects = 5;

		try
		{
			$db->query('SELECT 1');
			$this->fail('Expected a RuntimeException');
		}
		catch (RuntimeException $e)
		{
			$this->assertEquals('Unable to connect to the database.', $e->getMessage());
		}

		// The lost connection was closed before reconnecting, so there is nothing to read the error from
		$this->assertEquals(array('code' => '00000', 'message' => ''), $db->error());

		// Once the server is back, the next query reconnects
		$this->server->fail_connects = 0;
		$this->server->succeed(array(array('id' => 1)));
		$this->assertNotFalse($db->query('SELECT 1'));
		$this->assertCount(2, $this->server->connections);
	}

}
