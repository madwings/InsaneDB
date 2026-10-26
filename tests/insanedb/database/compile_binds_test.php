<?php

/**
 * Query bindings
 *
 * Runs against Mock_Database_Fake_Server, so the SQL that reaches the
 * server after the binds were compiled can be inspected.
 */
class Compile_binds_test extends CI_TestCase {

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

	public function test_scalar_binds_are_escaped()
	{
		$db = $this->db();

		$this->assertEquals(
			"SELECT * FROM job WHERE id = 1 AND name = 'O''Reilly'",
			$db->compile_binds('SELECT * FROM job WHERE id = ? AND name = ?', array(1, "O'Reilly"))
		);
	}

	// ------------------------------------------------------------------------

	public function test_single_bind_does_not_need_an_array()
	{
		$db = $this->db();

		$this->assertEquals('SELECT * FROM job WHERE id = 5', $db->compile_binds('SELECT * FROM job WHERE id = ?', 5));
		$this->assertEquals("SELECT * FROM job WHERE name = 'x'", $db->compile_binds('SELECT * FROM job WHERE name = ?', 'x'));
	}

	// ------------------------------------------------------------------------

	public function test_types_are_converted()
	{
		$db = $this->db();

		$this->assertEquals(
			'SELECT NULL, 1, 0, 1.5, -3',
			$db->compile_binds('SELECT ?, ?, ?, ?, ?', array(NULL, TRUE, FALSE, 1.5, -3))
		);
	}

	// ------------------------------------------------------------------------

	public function test_array_bind_becomes_a_list()
	{
		$db = $this->db();

		$this->assertEquals(
			"SELECT * FROM job WHERE id IN (1,2,'3')",
			$db->compile_binds('SELECT * FROM job WHERE id IN ?', array(array(1, 2, '3')))
		);
	}

	// ------------------------------------------------------------------------

	public function test_binds_are_applied_in_order_regardless_of_keys()
	{
		$db = $this->db();

		$this->assertEquals(
			'SELECT 1, 2',
			$db->compile_binds('SELECT ?, ?', array('second' => 1, 'first' => 2))
		);
	}

	// ------------------------------------------------------------------------

	public function test_markers_inside_string_literals_are_ignored()
	{
		$db = $this->db();

		$this->assertEquals(
			"SELECT 'a?b', \"c?d\" FROM job WHERE id = 7",
			$db->compile_binds("SELECT 'a?b', \"c?d\" FROM job WHERE id = ?", array(7))
		);
	}

	// ------------------------------------------------------------------------

	public function test_markers_inside_bound_values_are_not_replaced_again()
	{
		$db = $this->db();

		$this->assertEquals(
			"SELECT * FROM job WHERE name = 'what?' AND id = 1",
			$db->compile_binds('SELECT * FROM job WHERE name = ? AND id = ?', array('what?', 1))
		);
	}

	// ------------------------------------------------------------------------

	public function test_count_mismatch_leaves_the_query_untouched()
	{
		$db = $this->db();

		$sqls = array(
			'SELECT * FROM job WHERE id = ? AND name = ?' => array(1),
			'SELECT * FROM job WHERE id = ?' => array(1, 2),
			"SELECT 'a?b' FROM job WHERE id = ?" => array(1, 2),
			"SELECT 'a?b' FROM job WHERE id = ? AND name = ?" => array(1)
		);

		foreach ($sqls as $sql => $binds)
		{
			$this->assertSame($sql, $db->compile_binds($sql, $binds), $sql);
		}
	}

	// ------------------------------------------------------------------------

	public function test_query_without_markers_is_returned_as_is()
	{
		$db = $this->db();

		$this->assertSame('SELECT 1', $db->compile_binds('SELECT 1', array(1, 2)));
		$this->assertSame('SELECT 1', $db->compile_binds('SELECT 1', FALSE));
	}

	// ------------------------------------------------------------------------

	public function test_custom_bind_marker()
	{
		$db = $this->db(array('bind_marker' => ':val'));

		$this->assertEquals(
			"SELECT * FROM job WHERE name = 'a' AND id = 2",
			$db->compile_binds('SELECT * FROM job WHERE name = :val AND id = :val', array('a', 2))
		);
	}

	// ------------------------------------------------------------------------

	public function test_query_executes_the_compiled_sql()
	{
		$db = $this->db();
		$this->server->succeed(array(array('id' => 1)));

		$query = $db->query('SELECT id FROM job WHERE id = ? AND name = ?', array(1, "O'Reilly"));

		$this->assertInstanceOf('CI_DB_result', $query);
		$this->assertEquals(array("SELECT id FROM job WHERE id = 1 AND name = 'O''Reilly'"), $this->server->queries());
		$this->assertEquals("SELECT id FROM job WHERE id = 1 AND name = 'O''Reilly'", $db->last_query());
	}

	// ------------------------------------------------------------------------

	public function test_query_with_scalar_bind()
	{
		$db = $this->db();

		$db->query('DELETE FROM job WHERE id = ?', 3);

		$this->assertEquals(array('DELETE FROM job WHERE id = 3'), $this->server->queries());
	}

}
