<?php

/**
 * PDO MySQL transactions
 *
 * Runs against Mock_Database_Fake_Server, where every connection records
 * the attributes set on it, so the autocommit handling can be verified.
 */
class Pdo_mysql_transaction_test extends CI_TestCase {

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

	protected function autocommit($index = 0)
	{
		$attributes = $this->server->connections[$index]->attributes;
		return array_key_exists(PDO::ATTR_AUTOCOMMIT, $attributes) ? $attributes[PDO::ATTR_AUTOCOMMIT] : NULL;
	}

	// ------------------------------------------------------------------------

	public function test_autocommit_is_untouched_without_a_transaction()
	{
		$db = $this->db();

		$db->query('INSERT INTO job (id) VALUES (1)');

		$this->assertNull($this->autocommit());
	}

	// ------------------------------------------------------------------------

	public function test_begin_disables_autocommit()
	{
		$db = $this->db();

		$this->assertTrue($db->trans_begin());

		$this->assertFalse($this->autocommit());
		$this->assertTrue($this->server->connections[0]->in_transaction);
		$this->assertEquals(array('BEGIN'), $this->server->queries(TRUE));
	}

	// ------------------------------------------------------------------------

	public function test_commit_restores_autocommit()
	{
		$db = $this->db();

		$db->trans_begin();
		$db->query('INSERT INTO job (id) VALUES (1)');
		$this->assertTrue($db->trans_commit());

		$this->assertTrue($this->autocommit());
		$this->assertFalse($this->server->connections[0]->in_transaction);
		$this->assertEquals(array('BEGIN', 'INSERT INTO job (id) VALUES (1)', 'COMMIT'), $this->server->queries(TRUE));
	}

	// ------------------------------------------------------------------------

	public function test_rollback_restores_autocommit()
	{
		$db = $this->db();

		$db->trans_begin();
		$db->query('INSERT INTO job (id) VALUES (1)');
		$this->assertTrue($db->trans_rollback());

		$this->assertTrue($this->autocommit());
		$this->assertEquals(array('BEGIN', 'INSERT INTO job (id) VALUES (1)', 'ROLLBACK'), $this->server->queries(TRUE));
	}

	// ------------------------------------------------------------------------

	public function test_failed_begin_restores_autocommit()
	{
		$db = $this->db();
		$this->server->fail_begin = TRUE;

		$this->assertFalse($db->trans_begin());

		$this->assertTrue($this->autocommit());
		$this->assertFalse($db->trans_active());

		// The following writes are committed as usual
		$this->server->fail_begin = FALSE;
		$this->assertTrue($db->query('INSERT INTO job (id) VALUES (1)'));
		$this->assertTrue($this->autocommit());
	}

	// ------------------------------------------------------------------------

	public function test_failed_rollback_keeps_the_transaction_open()
	{
		$db = $this->db();

		$db->trans_begin();
		$this->server->rollback_outcome = 'fail';

		$this->assertFalse($db->trans_rollback());

		$this->assertFalse($this->autocommit());
		$this->assertTrue($db->trans_active());
	}

	// ------------------------------------------------------------------------

	public function test_nested_transactions_toggle_autocommit_only_once()
	{
		$db = $this->db();

		$db->trans_begin();
		$db->trans_begin();
		$this->assertTrue($db->trans_commit());

		// Only the outermost transaction commits
		$this->assertFalse($this->autocommit());
		$this->assertTrue($db->trans_active());

		$this->assertTrue($db->trans_commit());
		$this->assertTrue($this->autocommit());
		$this->assertEquals(array('BEGIN', 'COMMIT'), $this->server->queries(TRUE));
	}

	// ------------------------------------------------------------------------

	public function test_test_mode_rolls_back_and_restores_autocommit()
	{
		$db = $this->db();

		$db->trans_start(TRUE);
		$db->query('INSERT INTO job (id) VALUES (1)');
		$this->assertFalse($db->trans_complete());

		$this->assertTrue($this->autocommit());
		$this->assertEquals(array('BEGIN', 'INSERT INTO job (id) VALUES (1)', 'ROLLBACK'), $this->server->queries(TRUE));
	}

	// ------------------------------------------------------------------------

	public function test_transaction_after_lost_connection_uses_a_fresh_connection()
	{
		$db = $this->db();

		$db->trans_begin();
		$this->server->fail('HY000', 2013);
		$db->query('UPDATE job SET name = 1');
		$db->trans_rollback();

		$this->assertTrue($db->trans_begin());

		// The old connection is left as it was, the new one starts its own transaction
		$this->assertFalse($this->autocommit(0));
		$this->assertFalse($this->autocommit(1));
		$this->assertTrue($this->server->connections[1]->in_transaction);

		$db->trans_commit();
		$this->assertTrue($this->autocommit(1));
	}

}
