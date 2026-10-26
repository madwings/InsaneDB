<?php

/**
 * Query retry and automatic reconnect
 *
 * Runs against Mock_Database_Fake_Server, so the outcome of each query
 * (including connection losses) can be scripted.
 */
class Query_retry_test extends CI_TestCase {

	/**
	 * @var Mock_Database_Fake_Server
	 */
	protected $server;

	// ------------------------------------------------------------------------

	protected function mysql(array $params = array())
	{
		$db = Mock_Database_Fake_Mysql::create($params);
		$this->server = $db->fake_server;
		return $db;
	}

	// ------------------------------------------------------------------------

	protected function pgsql(array $params = array())
	{
		$db = Mock_Database_Fake_Pgsql::create($params);
		$this->server = $db->fake_server;
		return $db;
	}

	// ------------------------------------------------------------------------

	protected function is_retry_safe($sql)
	{
		$db = Mock_Database_Fake_Mysql::create(array(), FALSE);
		$method = new ReflectionMethod($db, '_is_retry_safe');
		return $method->invoke($db, $sql);
	}

	// ------------------------------------------------------------------------

	public function test_retry_safety_accepts_read_statements()
	{
		$sqls = array(
			'SELECT 1',
			'/* comment */ (SELECT 1)',
			'WITH recent_jobs AS (SELECT id FROM job) SELECT id FROM recent_jobs'
		);

		foreach ($sqls as $sql)
		{
			$this->assertTrue($this->is_retry_safe($sql), $sql);
		}
	}

	// ------------------------------------------------------------------------

	public function test_retry_safety_rejects_statements_that_may_write()
	{
		$sqls = array(
			'WITH inactive_users AS (SELECT id FROM users) UPDATE users JOIN inactive_users USING (id) SET disabled = 1',
			'WITH expired_orders AS (SELECT id FROM orders) DELETE orders FROM orders JOIN expired_orders USING (id)',
			'CALL archive_old_orders(90)',
			'DO RELEASE_LOCK(\'maintenance\')',
			'SELECT \'UPDATE\' AS note',
			'SELECT * FROM events WHERE message = \'DELETE\''
		);

		foreach ($sqls as $sql)
		{
			$this->assertFalse($this->is_retry_safe($sql), $sql);
		}
	}

	// ------------------------------------------------------------------------

	public function test_successful_query_runs_once()
	{
		$db = $this->mysql();
		$this->server->succeed(array(array('id' => 1)));

		$query = $db->query('SELECT 1');

		$this->assertInstanceOf('CI_DB_result', $query);
		$this->assertEquals(array(array('id' => 1)), $query->result_array());
		$this->assertCount(1, $this->server->queries());
		$this->assertCount(1, $this->server->connections);
	}

	// ------------------------------------------------------------------------

	public function test_non_retryable_error_is_not_retried()
	{
		$db = $this->mysql();
		$this->server->fail('42000', 1064);

		$this->assertFalse($db->query('SELEC 1'));
		$this->assertCount(1, $this->server->queries());

		$error = $db->error();
		$this->assertEquals('42000/1064', $error['code']);
	}

	// ------------------------------------------------------------------------

	public function test_mysql_server_gone_retries_on_new_connection()
	{
		foreach (array('SELECT 1', 'UPDATE job SET name = 1') as $sql)
		{
			$db = $this->mysql();
			$this->server->fail('HY000', 2006);

			$this->assertNotFalse($db->query($sql), $sql);
			$this->assertEquals(array($sql, $sql), $this->server->queries(), $sql);

			// The query was never sent, so even writes are retried on a fresh connection
			$this->assertCount(2, $this->server->connections, $sql);
			$this->assertSame(1, $this->server->log[1]['conn'], $sql);
		}
	}

	// ------------------------------------------------------------------------

	public function test_mysql_interaction_timeout_retries_on_new_connection()
	{
		$db = $this->mysql();
		$this->server->fail('HY000', 4031);

		$this->assertNotFalse($db->query('INSERT INTO job (id) VALUES (1)'));
		$this->assertCount(2, $this->server->queries());
		$this->assertCount(2, $this->server->connections);
	}

	// ------------------------------------------------------------------------

	public function test_mysql_lost_connection_retries_reads()
	{
		foreach (array(2013, 2055, 1053, 1927) as $code)
		{
			$db = $this->mysql();
			$this->server->fail('HY000', $code);

			$this->assertNotFalse($db->query('SELECT 1'), 'Error code '.$code);
			$this->assertCount(2, $this->server->queries(), 'Error code '.$code);
			$this->assertCount(2, $this->server->connections, 'Error code '.$code);
		}
	}

	// ------------------------------------------------------------------------

	public function test_mysql_lost_connection_does_not_retry_writes()
	{
		$db = $this->mysql();
		$this->server->fail('HY000', 2013);

		// The write may have been executed already, so it must not run again
		$this->assertFalse($db->query('INSERT INTO job (id) VALUES (1)'));
		$this->assertCount(1, $this->server->queries());

		// The connection is kept until the next query, so the error can still be read
		$this->assertSame($this->server->connections[0], $db->conn_id);
		$error = $db->error();
		$this->assertEquals('HY000/2013', $error['code']);
		$this->assertTrue($db->fake_get('_reconnect'));

		// The next query reconnects first
		$this->assertNotFalse($db->query('SELECT 1'));
		$this->assertCount(2, $this->server->connections);
		$this->assertSame(1, $this->server->entry('SELECT 1')['conn']);
		$this->assertFalse($db->fake_get('_reconnect'));
	}

	// ------------------------------------------------------------------------

	public function test_mysql_lock_wait_timeout_retries_on_same_connection()
	{
		$db = $this->mysql();
		$this->server->fail('HY000', 1205);

		$this->assertNotFalse($db->query('UPDATE job SET name = 1'));
		$this->assertCount(2, $this->server->queries());
		$this->assertCount(1, $this->server->connections);
	}

	// ------------------------------------------------------------------------

	public function test_mysql_deadlock_retries_on_same_connection()
	{
		$db = $this->mysql();
		$this->server->fail('40001', 1213);

		$this->assertNotFalse($db->query('UPDATE job SET name = 1'));
		$this->assertCount(2, $this->server->queries());
		$this->assertCount(1, $this->server->connections);
	}

	// ------------------------------------------------------------------------

	public function test_retries_are_limited_by_query_retries()
	{
		$db = $this->mysql(array('query_retries' => 2));
		$this->server->fail('HY000', 2006)->fail('HY000', 2006)->fail('HY000', 2006)->succeed();

		$this->assertFalse($db->query('SELECT 1'));
		$this->assertCount(3, $this->server->queries());

		// The last attempt doesn't close the connection, so its error is still available
		$error = $db->error();
		$this->assertEquals('HY000/2006', $error['code']);
	}

	// ------------------------------------------------------------------------

	public function test_query_retries_zero_disables_retries()
	{
		$db = $this->mysql(array('query_retries' => 0));
		$this->server->fail('HY000', 2006);

		$this->assertFalse($db->query('SELECT 1'));
		$this->assertCount(1, $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_negative_query_retries_still_runs_the_query()
	{
		$db = $this->mysql(array('query_retries' => -1));
		$this->server->succeed(array(array('id' => 1)));

		$this->assertInstanceOf('CI_DB_result', $db->query('SELECT 1'));
		$this->assertCount(1, $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_no_retry_inside_transaction()
	{
		$db = $this->mysql();

		$this->assertTrue($db->trans_begin());
		$this->server->fail('HY000', 2006);

		// Re-running the statement would execute it outside of the lost transaction
		$this->assertFalse($db->query('UPDATE job SET name = 1'));
		$this->assertCount(1, $this->server->queries());
		$this->assertFalse($db->trans_status());

		$db->trans_rollback();
		$this->assertFalse($db->trans_active());
	}

	// ------------------------------------------------------------------------

	public function test_failed_rollback_after_lost_connection_unwinds_transaction()
	{
		foreach (array('fail', 'throw') as $outcome)
		{
			$db = $this->mysql();

			$this->assertTrue($db->trans_begin(), $outcome);
			$this->assertTrue($db->trans_begin(), $outcome);
			$this->server->fail('HY000', 2013);
			$this->server->rollback_outcome = $outcome;

			$this->assertFalse($db->query('UPDATE job SET name = 1'), $outcome);
			$this->assertFalse($db->trans_complete(), $outcome);
			$this->assertFalse($db->trans_complete(), $outcome);
			$this->assertFalse($db->trans_active(), $outcome);
			$this->assertTrue($db->fake_get('_reconnect'), $outcome);

			// A new transaction is not nested and runs on a new connection
			$this->server->rollback_outcome = NULL;
			$this->assertTrue($db->trans_begin(), $outcome);
			$this->assertCount(2, $this->server->connections, $outcome);
			$this->assertSame(1, $this->server->entry('BEGIN')['conn'], $outcome);
			$this->assertTrue($db->trans_commit(), $outcome);
			$this->assertFalse($db->trans_active(), $outcome);
		}
	}

	// ------------------------------------------------------------------------

	public function test_failed_rollback_on_live_connection_keeps_transaction()
	{
		$db = $this->mysql();

		$this->assertTrue($db->trans_begin());
		$this->server->rollback_outcome = 'fail';

		$this->assertFalse($db->trans_rollback());
		$this->assertTrue($db->trans_active());
	}

	// ------------------------------------------------------------------------

	public function test_failed_count_all_resets_query_builder()
	{
		$db = $this->mysql();
		$this->server->fail('42S02', 1146);

		$this->assertFalse($db->where('id', 1)->count_all('missing_table'));

		$db->get('job');
		$this->assertStringNotContainsString('WHERE', $db->last_query());
	}

	// ------------------------------------------------------------------------

	public function test_failed_reconnect_is_retried()
	{
		$db = $this->mysql(array('query_retries' => 2));
		$this->server->fail('HY000', 2006);
		$this->server->fail_connects = 1;

		// Attempt 1 loses the connection, attempt 2 can't connect, attempt 3 succeeds
		$this->assertNotFalse($db->query('SELECT 1'));
		$this->assertCount(2, $this->server->queries());
		$this->assertCount(2, $this->server->connections);
	}

	// ------------------------------------------------------------------------

	public function test_failed_reconnect_throws_when_attempts_are_exhausted()
	{
		$db = $this->mysql(array('query_retries' => 1));
		$this->server->fail('HY000', 2006);
		$this->server->fail_connects = 5;

		$this->setExpectedException('RuntimeException', 'Unable to connect to the database.');
		$db->query('SELECT 1');
	}

	// ------------------------------------------------------------------------

	public function test_reconnect_opens_new_connection()
	{
		$db = $this->mysql();
		$first = $db->conn_id;

		$db->reconnect();

		$this->assertCount(2, $this->server->connections);
		$this->assertNotSame($first, $db->conn_id);
		$this->assertSame($this->server->connections[1], $db->conn_id);
	}

	// ------------------------------------------------------------------------

	public function test_pending_reconnect_happens_before_transaction()
	{
		$db = $this->mysql();
		$this->server->fail('HY000', 2013);
		$this->assertFalse($db->query('DELETE FROM job'));

		$this->assertTrue($db->trans_begin());
		$this->assertCount(2, $this->server->connections);
		$this->assertSame(1, $this->server->entry('BEGIN')['conn']);
		$db->trans_commit();
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_deadlock_and_serialization_failure_are_retried()
	{
		foreach (array('40P01', '40001', '55P03') as $sqlstate)
		{
			$db = $this->pgsql();
			$this->server->fail($sqlstate, 7);

			$this->assertNotFalse($db->query('UPDATE job SET name = 1'), $sqlstate);
			$this->assertCount(2, $this->server->queries(), $sqlstate);
			$this->assertCount(1, $this->server->connections, $sqlstate);
		}
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_connection_errors_retry_reads_on_new_connection()
	{
		foreach (array('08006', '08003', '57P01', '57P02', '57P03', '57P05') as $sqlstate)
		{
			$db = $this->pgsql();
			$this->server->fail($sqlstate, 7);

			$this->assertNotFalse($db->query('SELECT 1'), $sqlstate);
			$this->assertCount(2, $this->server->queries(), $sqlstate);
			$this->assertCount(2, $this->server->connections, $sqlstate);
		}
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_bad_connection_without_sqlstate_is_retried()
	{
		$db = $this->pgsql();
		$this->server->fail('HY000', 7, 'Bad connection.');

		$this->assertNotFalse($db->query('SELECT 1'));
		$this->assertCount(2, $this->server->connections);
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_bad_connection_error_is_reported()
	{
		$db = $this->pgsql(array('query_retries' => 0));
		$this->server->fail('HY000', 7, 'Bad connection.');

		// The error must survive the connection status check, which clears it in PDO
		$this->assertFalse($db->query('SELECT 1'));
		$error = $db->error();
		$this->assertEquals('HY000/7', $error['code']);
		$this->assertEquals('Fake error HY000/7', $error['message']);
		$this->assertTrue($db->fake_get('_reconnect'));

		// It's not reported for the next query
		$db->query('SELECT 1');
		$error = $db->error();
		$this->assertEquals('00000', $error['code']);
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_error_without_sqlstate_on_good_connection_is_reported()
	{
		$db = $this->pgsql();
		$this->server->fail('HY000', 7);

		$this->assertFalse($db->query('SELECT 1'));
		$this->assertCount(1, $this->server->queries());
		$this->assertFalse($db->fake_get('_reconnect'));

		$error = $db->error();
		$this->assertEquals('HY000/7', $error['code']);
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_idle_session_timeout_retries_writes()
	{
		$db = $this->pgsql();
		$this->server->fail('57P05', 7);

		// The server closed the idle connection before the query was received
		$this->assertNotFalse($db->query('UPDATE job SET name = 1'));
		$this->assertCount(2, $this->server->queries());
		$this->assertCount(2, $this->server->connections);
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_idle_in_transaction_timeout_reconnects_after_transaction()
	{
		$db = $this->pgsql();

		$this->assertTrue($db->trans_begin());
		$this->server->fail('25P03', 7);

		$this->assertFalse($db->query('UPDATE job SET name = 1'));
		$this->assertCount(1, $this->server->queries());
		$this->assertTrue($db->fake_get('_reconnect'));

		$db->trans_rollback();
		$this->assertTrue($db->trans_begin());
		$this->assertCount(2, $this->server->connections);
		$db->trans_commit();
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_lost_connection_does_not_retry_writes()
	{
		$sqls = array(
			'UPDATE job SET name = 1',
			// Treated as a read by is_write_type(), but it is still a write
			'INSERT INTO job (name) VALUES (1) RETURNING id'
		);

		foreach ($sqls as $sql)
		{
			$db = $this->pgsql();
			$this->server->fail('08006', 7);

			$this->assertFalse($db->query($sql), $sql);
			$this->assertCount(1, $this->server->queries(), $sql);
			$this->assertTrue($db->fake_get('_reconnect'), $sql);

			$db->query('SELECT 1');
			$this->assertCount(2, $this->server->connections, $sql);
		}
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_constraint_violation_is_not_retried()
	{
		$db = $this->pgsql();
		$this->server->fail('23505', 7);

		$this->assertFalse($db->query('INSERT INTO job (id) VALUES (1)'));
		$this->assertCount(1, $this->server->queries());

		// The error of a non-retryable query is still available
		$error = $db->error();
		$this->assertEquals('23505/7', $error['code']);
		$this->assertEquals('Fake error 23505/7', $error['message']);
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_returning_is_read_type()
	{
		$db = $this->pgsql();

		$this->assertFalse($db->is_write_type('INSERT INTO job (name) VALUES (1) RETURNING id'));
		$this->assertFalse($db->is_write_type('UPDATE job SET name = 1 RETURNING id, name'));
		$this->assertTrue($db->is_write_type('INSERT INTO job (name) VALUES (1)'));
		$this->assertFalse($db->is_write_type('SELECT * FROM job'));
	}

}
