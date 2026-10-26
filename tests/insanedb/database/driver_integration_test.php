<?php

/**
 * Driver behavior against the database configured by DB_DRIVER
 */
class Driver_integration_test extends CI_TestCase {

	/**
	 * @var CI_DB_query_builder
	 */
	protected $db;

	public function set_up()
	{
		$this->db = Mock_Database_Schema_Skeleton::init(DB_DRIVER);

		Mock_Database_Schema_Skeleton::create_tables();
		Mock_Database_Schema_Skeleton::create_data();
	}

	// ------------------------------------------------------------------------

	public function tear_down()
	{
		// Never leave a transaction open for the next test
		while ($this->db->trans_active())
		{
			$this->db->trans_rollback();
		}

		$this->db->trans_strict(TRUE);
	}

	// ------------------------------------------------------------------------

	public function test_transaction_commit()
	{
		$this->assertTrue($this->db->trans_start());
		$this->db->insert('job', array('id' => 5, 'name' => 'Pilot', 'description' => 'Flies'));
		$this->db->where('id', 1)->update('job', array('name' => 'Programmer'));
		$this->assertTrue($this->db->trans_complete());

		$this->assertTrue($this->db->trans_status());
		$this->assertFalse($this->db->trans_active());
		$this->assertEquals(5, $this->db->count_all('job'));
		$this->assertEquals('Programmer', $this->db->where('id', 1)->get('job')->row()->name);
	}

	// ------------------------------------------------------------------------

	public function test_transaction_test_mode_rolls_back()
	{
		$this->assertTrue($this->db->trans_start(TRUE));
		$this->db->insert('job', array('id' => 5, 'name' => 'Pilot', 'description' => 'Flies'));
		$this->assertFalse($this->db->trans_complete());

		$this->assertEquals(4, $this->db->count_all('job'));
	}

	// ------------------------------------------------------------------------

	public function test_failed_query_rolls_back_transaction()
	{
		$this->db->trans_strict(FALSE);

		$this->db->trans_start();
		$this->db->insert('job', array('id' => 5, 'name' => 'Pilot', 'description' => 'Flies'));
		$this->assertFalse($this->db->query('SELECT * FROM missing_table'));
		$this->assertFalse($this->db->trans_status());
		$this->assertFalse($this->db->trans_complete());

		$this->assertFalse($this->db->trans_active());
		$this->assertEquals(4, $this->db->count_all('job'));
	}

	// ------------------------------------------------------------------------

	public function test_nested_transactions_commit_with_outermost()
	{
		$this->db->trans_begin();
		$this->db->trans_begin();
		$this->db->insert('job', array('id' => 5, 'name' => 'Pilot', 'description' => 'Flies'));
		$this->assertTrue($this->db->trans_commit());

		// Only the outermost transaction really commits
		$this->assertTrue($this->db->trans_active());
		$this->assertTrue($this->db->trans_rollback());

		$this->assertFalse($this->db->trans_active());
		$this->assertEquals(4, $this->db->count_all('job'));
	}

	// ------------------------------------------------------------------------

	public function test_table_metadata()
	{
		$this->assertTrue($this->db->table_exists('job'));
		$this->assertFalse($this->db->table_exists('missing_table'));

		$fields = $this->db->list_fields('job');
		$this->assertContains('id', $fields);
		$this->assertContains('name', $fields);
		$this->assertTrue($this->db->field_exists('description', 'job'));

		// Missing tables don't cause fatal errors
		$this->assertFalse($this->db->field_exists('id', 'missing_table'));
	}

	// ------------------------------------------------------------------------

	public function test_failed_query_returns_false()
	{
		$this->assertFalse($this->db->query('SELECT * FROM missing_table'));

		$error = $this->db->error();
		$this->assertNotEmpty($error['code']);
		$this->assertNotEmpty($error['message']);

		// The connection is still usable
		$this->assertEquals(4, $this->db->count_all('job'));
	}

	// ------------------------------------------------------------------------

	public function test_reconnect()
	{
		$this->db->reconnect();

		$this->assertNotEmpty($this->db->conn_id);
		$this->assertEquals(4, $this->db->count_all('job'));
	}

	// ------------------------------------------------------------------------

	public function test_query_after_close()
	{
		$this->db->close();
		$this->assertEmpty($this->db->conn_id);

		// The connection is re-established on demand
		$this->assertEquals(4, $this->db->count_all('job'));
		$this->assertNotEmpty($this->db->conn_id);
	}

}
