<?php

/**
 * Methods that run a query internally must return FALSE when it fails,
 * instead of calling methods on FALSE (a fatal error).
 */
class Failed_query_test extends CI_TestCase {

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

		// Table doesn't exist
		$this->server->fail('42S02', 1146);
	}

	// ------------------------------------------------------------------------

	public function test_count_all()
	{
		$this->assertFalse($this->db->count_all('missing'));
	}

	// ------------------------------------------------------------------------

	public function test_count_all_results()
	{
		$this->assertFalse($this->db->where('id', 1)->count_all_results('missing'));

		// The query builder is reset after the failure
		$this->assertEquals("SELECT *\nFROM `job`", $this->db->get_compiled_select('job'));
	}

	// ------------------------------------------------------------------------

	public function test_list_tables()
	{
		$this->assertFalse($this->db->list_tables());

		// Failures are not cached
		$this->server->succeed(array(array('Tables_in_ci_test' => 'job')));
		$this->assertEquals(array('job'), $this->db->list_tables());
	}

	// ------------------------------------------------------------------------

	public function test_table_exists()
	{
		$this->assertFalse($this->db->table_exists('job'));
	}

	// ------------------------------------------------------------------------

	public function test_list_fields()
	{
		$this->assertFalse($this->db->list_fields('missing'));
	}

	// ------------------------------------------------------------------------

	public function test_field_exists()
	{
		$this->assertFalse($this->db->field_exists('id', 'missing'));
	}

	// ------------------------------------------------------------------------

	public function test_primary()
	{
		$this->assertFalse($this->db->primary('missing'));
	}

	// ------------------------------------------------------------------------

	public function test_field_data()
	{
		$this->assertFalse($this->db->field_data('missing'));
	}

	// ------------------------------------------------------------------------

	public function test_successful_queries()
	{
		// Consume the scripted failure
		$this->db->query('SELECT 1');

		$this->server->succeed(array(array('numrows' => '4')));
		$this->assertSame(4, $this->db->count_all('job'));

		$this->server->succeed(array(array('numrows' => '2')));
		$this->assertSame(2, $this->db->where('id >', 2)->count_all_results('job'));

		$this->server->succeed(array());
		$this->assertSame(0, $this->db->count_all_results('job'));

		$this->server->succeed(array(array('Field' => 'id'), array('Field' => 'name')));
		$this->assertTrue($this->db->field_exists('name', 'job'));

		$this->server->succeed(array(array('Field' => 'id')));
		$this->assertFalse($this->db->field_exists('name', 'job'));
	}

}
