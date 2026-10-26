<?php

/**
 * Error handling with db_debug enabled
 *
 * Runs against Mock_Database_Fake_Server. The fake drivers' display_error()
 * throws a RuntimeException instead of printing the error and exiting.
 */
class Db_debug_test extends CI_TestCase {

	/**
	 * @var Mock_Database_Fake_Server
	 */
	protected $server;

	// ------------------------------------------------------------------------

	protected function db(array $params = array())
	{
		$db = Mock_Database_Fake_Mysql::create(array_merge(array('db_debug' => TRUE), $params));
		$this->server = $db->fake_server;
		return $db;
	}

	// ------------------------------------------------------------------------

	/**
	 * Run the callback and return the RuntimeException it throws
	 */
	protected function expect_db_error($callback)
	{
		try
		{
			$callback();
		}
		catch (RuntimeException $e)
		{
			return $e;
		}

		$this->fail('Expected a RuntimeException');
	}

	// ------------------------------------------------------------------------

	public function test_failed_query_throws_with_error_details()
	{
		$db = $this->db();
		$this->server->fail('42000', 1064);

		$e = $this->expect_db_error(function () use ($db) {
			$db->query('SELEC 1');
		});

		$this->assertStringContainsString('Error Number: 42000/1064', $e->getMessage());
		$this->assertStringContainsString('Fake error 42000/1064', $e->getMessage());
		$this->assertStringContainsString('SELEC 1', $e->getMessage());
	}

	// ------------------------------------------------------------------------

	public function test_failed_query_returns_false_when_disabled()
	{
		$db = $this->db(array('db_debug' => FALSE));
		$this->server->fail('42000', 1064);

		$this->assertFalse($db->query('SELEC 1'));
	}

	// ------------------------------------------------------------------------

	public function test_empty_query_throws()
	{
		$db = $this->db();

		$e = $this->expect_db_error(function () use ($db) {
			$db->query('');
		});

		$this->assertStringContainsString('db_invalid_query', $e->getMessage());
		$this->assertCount(0, $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_empty_query_returns_false_when_disabled()
	{
		$db = $this->db(array('db_debug' => FALSE));

		$this->assertFalse($db->query(''));
	}

	// ------------------------------------------------------------------------

	public function test_failed_query_rolls_back_nested_transactions_before_throwing()
	{
		$db = $this->db();

		$this->assertTrue($db->trans_start());
		$this->assertTrue($db->trans_start());
		$this->server->fail('23000', 1062);

		$this->expect_db_error(function () use ($db) {
			$db->query('INSERT INTO job (id) VALUES (1)');
		});

		// Both levels were unwound and the outermost one rolled back on the server
		$this->assertFalse($db->trans_active());
		$this->assertFalse($db->trans_status());
		$this->assertEquals(
			array('BEGIN', 'INSERT INTO job (id) VALUES (1)', 'ROLLBACK'),
			$this->server->queries(TRUE)
		);

		// A new transaction can be started afterwards
		$this->assertTrue($db->trans_start());
		$this->assertTrue($db->trans_complete());
		$this->assertEquals(array('BEGIN', 'COMMIT'), array_slice($this->server->queries(TRUE), 3));
	}

	// ------------------------------------------------------------------------

	public function test_non_strict_mode_resets_the_status_for_the_next_group()
	{
		$db = $this->db();
		$db->trans_strict(FALSE);

		$db->trans_start();
		$this->server->fail('23000', 1062);

		$this->expect_db_error(function () use ($db) {
			$db->query('INSERT INTO job (id) VALUES (1)');
		});

		$this->assertFalse($db->trans_active());
		$this->assertTrue($db->trans_status());
	}

	// ------------------------------------------------------------------------

	public function test_failed_rollback_does_not_loop_forever()
	{
		$db = $this->db();

		$db->trans_start();
		$this->server->fail('23000', 1062);
		$this->server->rollback_outcome = 'fail';

		$e = $this->expect_db_error(function () use ($db) {
			$db->query('INSERT INTO job (id) VALUES (1)');
		});

		// The error is still reported, but the transaction could not be unwound
		$this->assertStringContainsString('Error Number: 23000/1062', $e->getMessage());
		$this->assertTrue($db->trans_active());
		$this->assertEquals(array('ROLLBACK'), array_slice($this->server->queries(TRUE), 2));
	}

	// ------------------------------------------------------------------------

	public function test_lost_connection_inside_transaction_unwinds_locally()
	{
		$db = $this->db();

		$db->trans_start();
		$this->server->fail('HY000', 2013);

		$e = $this->expect_db_error(function () use ($db) {
			$db->query('UPDATE job SET name = 1');
		});

		$this->assertStringContainsString('Error Number: HY000/2013', $e->getMessage());
		$this->assertFalse($db->trans_active());

		// The server already discarded the transaction, so no ROLLBACK is sent
		$this->assertEquals(array('BEGIN', 'UPDATE job SET name = 1'), $this->server->queries(TRUE));

		// The next query runs on a new connection
		$this->server->succeed(array(array('id' => 1)));
		$this->assertNotFalse($db->query('SELECT 1'));
		$this->assertCount(2, $this->server->connections);
		$this->assertSame(1, $this->server->entry('SELECT 1')['conn']);
	}

	// ------------------------------------------------------------------------

	public function test_retryable_error_is_retried_before_throwing()
	{
		$db = $this->db(array('query_retries' => 1));
		$this->server->fail('HY000', 1205);
		$this->server->fail('HY000', 1205);

		$e = $this->expect_db_error(function () use ($db) {
			$db->query('UPDATE job SET name = 1');
		});

		$this->assertStringContainsString('Error Number: HY000/1205', $e->getMessage());
		$this->assertCount(2, $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_retried_query_that_succeeds_does_not_throw()
	{
		$db = $this->db(array('query_retries' => 1));
		$this->server->fail('HY000', 1213);
		$this->server->succeed();

		$this->assertTrue($db->query('UPDATE job SET name = 1'));
		$this->assertCount(2, $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_unsupported_function_throws()
	{
		$db = $this->db();

		$e = $this->expect_db_error(function () use ($db) {
			$db->call_function('missing_function');
		});

		$this->assertStringContainsString('db_unsupported_function', $e->getMessage());
	}

}
