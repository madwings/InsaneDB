<?php

/**
 * Batch query builder methods
 *
 * Checks the generated SQL by running the queries against
 * Mock_Database_Fake_Server, which records them.
 */
class Batch_sql_test extends CI_TestCase {

	/**
	 * @var CI_DB_query_builder
	 */
	protected $db;

	/**
	 * @var Mock_Database_Fake_Server
	 */
	protected $server;

	public function set_up()
	{
		$this->db = Mock_Database_Fake_Mysql::create();
		$this->server = $this->db->fake_server;
	}

	// ------------------------------------------------------------------------

	protected function rows()
	{
		return array(
			array('id' => 1, 'name' => 'Developer', 'description' => "Writes 'code'"),
			array('id' => 2, 'name' => 'Politician', 'description' => 'Talks')
		);
	}

	// ------------------------------------------------------------------------

	public function test_insert_batch()
	{
		$this->server->succeed(array(), 2);

		$this->assertSame(2, $this->db->insert_batch('job', $this->rows()));
		$this->assertEquals(
			array("INSERT INTO `job` (`description`, `id`, `name`) VALUES ('Writes ''code''',1,'Developer'), ('Talks',2,'Politician')"),
			$this->server->queries()
		);
	}

	// ------------------------------------------------------------------------

	public function test_insert_batch_size()
	{
		$rows = array();
		for ($i = 1; $i <= 5; $i++)
		{
			$rows[] = array('id' => $i);
		}

		$this->server->succeed(array(), 2)->succeed(array(), 2)->succeed(array(), 1);

		$this->assertSame(5, $this->db->insert_batch('job', $rows, 2));
		$this->assertEquals(array(
			'INSERT INTO `job` (`id`) VALUES (1), (2)',
			'INSERT INTO `job` (`id`) VALUES (3), (4)',
			'INSERT INTO `job` (`id`) VALUES (5)'
		), $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_insert_batch_with_include()
	{
		$this->db->insert_batch('job', $this->rows(), 100, array('name', 'id'));

		$this->assertEquals(
			array("INSERT INTO `job` (`id`, `name`) VALUES (1,'Developer'), (2,'Politician')"),
			$this->server->queries()
		);
	}

	// ------------------------------------------------------------------------

	public function test_insert_batch_include_allows_extra_keys()
	{
		$rows = array(
			array('id' => 1, 'name' => 'Developer'),
			array('id' => 2, 'name' => 'Politician', 'extra' => 'ignored')
		);

		$this->assertNotFalse($this->db->insert_batch('job', $rows, 100, array('id', 'name')));
		$this->assertEquals(
			array("INSERT INTO `job` (`id`, `name`) VALUES (1,'Developer'), (2,'Politician')"),
			$this->server->queries()
		);
	}

	// ------------------------------------------------------------------------

	public function test_insert_batch_with_different_keys_fails()
	{
		$rows = array(
			array('id' => 1, 'name' => 'Developer'),
			array('id' => 2, 'description' => 'Talks')
		);

		$this->assertFalse($this->db->insert_batch('job', $rows));
		$this->assertEquals(array(), $this->server->queries());

		// Rows with extra keys are rejected as well
		$rows[1] = array('id' => 2, 'name' => 'Politician', 'description' => 'Talks');
		$this->assertFalse($this->db->insert_batch('job', $rows));

		// Rows missing an included key are rejected
		$this->assertFalse($this->db->insert_batch('job', $rows, 100, array('id', 'description')));
		$this->assertEquals(array(), $this->server->queries());

		// No partially validated rows are left behind for the next call
		$this->db->insert_batch('job', array(array('id' => 3)));
		$this->assertEquals(array('INSERT INTO `job` (`id`) VALUES (3)'), $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_set_insert_batch_with_different_keys_fails()
	{
		$this->db->set_insert_batch(array(array('id' => 1)));
		$this->assertFalse($this->db->set_insert_batch(array(array('id' => 2), array('name' => 'x'))));

		// Rows of the valid call are kept, rows of the failed one are not
		$this->db->insert_batch('job');
		$this->assertEquals(array('INSERT INTO `job` (`id`) VALUES (1)'), $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_set_insert_batch_with_empty_array()
	{
		$this->assertSame($this->db, $this->db->set_insert_batch(array()));
		$this->assertFalse($this->db->insert_batch('job'));
		$this->assertEquals(array(), $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_insert_batch_with_objects()
	{
		$row = new stdClass();
		$row->id = 1;
		$row->name = 'Developer';

		$this->db->insert_batch('job', array($row));
		$this->assertEquals(array("INSERT INTO `job` (`id`, `name`) VALUES (1,'Developer')"), $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_insert_batch_without_escaping()
	{
		$rows = array(
			array('id' => 1, 'created' => 'NOW()'),
			array('id' => 2, 'created' => 'NOW()')
		);

		$this->db->insert_batch('job', $rows, 100, NULL, FALSE);

		$this->assertEquals(
			array('INSERT INTO job (created, id) VALUES (NOW(),1), (NOW(),2)'),
			$this->server->queries()
		);
	}

	// ------------------------------------------------------------------------

	public function test_insert_ignore_batch()
	{
		$this->server->succeed(array(), 1);

		$this->assertSame(1, $this->db->insert_ignore_batch('job', array(array('id' => 1, 'name' => 'Developer'))));
		$this->assertEquals(array("INSERT IGNORE INTO `job` (`id`, `name`) VALUES (1,'Developer')"), $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_insert_ignore_batch_options()
	{
		$this->db->insert_ignore_batch('job', $this->rows(), 1, array('id'));
		$this->assertFalse($this->db->insert_ignore_batch('job', array(array('id' => 1), array('name' => 'x'))));
		$this->db->insert_ignore_batch('job', array(array('id' => 3, 'name' => 'NULL')), 100, NULL, FALSE);

		$this->assertEquals(array(
			'INSERT IGNORE INTO `job` (`id`) VALUES (1)',
			'INSERT IGNORE INTO `job` (`id`) VALUES (2)',
			'INSERT IGNORE INTO job (id, name) VALUES (3,NULL)'
		), $this->server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_insert_ignore_batch_is_not_supported_by_pgsql()
	{
		$db = Mock_Database_Fake_Pgsql::create();

		$this->assertFalse($db->insert_ignore_batch('job', array(array('id' => 1))));
		$this->assertEquals(array(), $db->fake_server->queries());
	}

	// ------------------------------------------------------------------------

	public function test_update_batch()
	{
		$this->server->succeed(array(), 2);

		$this->assertSame(2, $this->db->update_batch('job', $this->rows(), 'id'));

		$queries = $this->server->queries();
		$this->assertCount(1, $queries);
		$this->assertStringStartsWith('UPDATE `job` SET `name` = CASE', $queries[0]);
		$this->assertStringContainsString("WHEN `id` = 1 THEN 'Developer'", $queries[0]);
		$this->assertStringContainsString("WHEN `id` = 2 THEN 'Talks'", $queries[0]);
		$this->assertStringEndsWith('WHERE `id` IN(1,2)', $queries[0]);
	}

	// ------------------------------------------------------------------------

	public function test_update_batch_with_include()
	{
		$this->db->update_batch('job', $this->rows(), 'id', 100, array('name'));

		$queries = $this->server->queries();
		$this->assertStringContainsString('`name` = CASE', $queries[0]);
		$this->assertStringNotContainsString('`description`', $queries[0]);
	}

	// ------------------------------------------------------------------------

	public function test_update_batch_applies_where_to_every_batch()
	{
		$this->server->succeed(array(), 1)->succeed(array(), 1);

		$this->db->where('name !=', 'Admin');
		$this->assertSame(2, $this->db->update_batch('job', $this->rows(), 'id', 1));

		$queries = $this->server->queries();
		$this->assertCount(2, $queries);

		$this->assertStringContainsString("WHERE `name` != 'Admin'", $queries[0]);
		$this->assertStringEndsWith('AND `id` IN(1)', $queries[0]);

		$this->assertStringContainsString("WHERE `name` != 'Admin'", $queries[1]);
		$this->assertStringEndsWith('AND `id` IN(2)', $queries[1]);
		$this->assertStringNotContainsString('IN(1)', $queries[1]);

		// The conditions don't leak into the next query
		$this->assertEquals("SELECT *\nFROM `job`", $this->db->get_compiled_select('job'));
	}

	// ------------------------------------------------------------------------

	public function test_pgsql_update_batch_applies_where_to_every_batch()
	{
		$db = Mock_Database_Fake_Pgsql::create();

		$db->where('name !=', 'Admin');
		$db->update_batch('job', $this->rows(), 'id', 1);

		$queries = $db->fake_server->queries();
		$this->assertCount(2, $queries);
		$this->assertStringContainsString("WHERE \"name\" != 'Admin'", $queries[0]);
		$this->assertStringEndsWith('AND "id" IN(1)', $queries[0]);
		$this->assertStringContainsString("WHERE \"name\" != 'Admin'", $queries[1]);
		$this->assertStringEndsWith('AND "id" IN(2)', $queries[1]);
	}

	// ------------------------------------------------------------------------

	public function test_batch_counts_only_successful_queries()
	{
		$this->server->succeed(array(), 1)->fail('23000', 1062)->succeed(array(), 1);

		$rows = array(array('id' => 1), array('id' => 2), array('id' => 3));
		$this->assertSame(2, $this->db->insert_batch('job', $rows, 1));
		$this->assertCount(3, $this->server->queries());
	}

}
