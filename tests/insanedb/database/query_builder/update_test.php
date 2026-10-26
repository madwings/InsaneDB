<?php

class Update_test extends CI_TestCase {

	/**
	 * @var object Database/Query Builder holder
	 */
	protected $db;

	public function set_up()
	{
		$this->db = Mock_Database_Schema_Skeleton::init(DB_DRIVER);

		Mock_Database_Schema_Skeleton::create_tables();
		Mock_Database_Schema_Skeleton::create_data();
	}

	// ------------------------------------------------------------------------

	/**
	 * @see ./mocks/schema/skeleton.php
	 */
	public function test_update()
	{
		// Check initial record
		$job1 = $this->db->where('id', 1)->get('job')->row();
		$this->assertEquals('Developer', $job1->name);

		// Do the update
		$this->db->where('id', 1)->update('job', array('name' => 'Programmer'));

		// Check updated record
		$job1 = $this->db->where('id', 1)->get('job')->row();
		$this->assertEquals('Programmer', $job1->name);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * @see ./mocks/schema/skeleton.php
	 */
	public function test_update_with_set()
	{
		// Check initial record
		$job1 = $this->db->where('id', 4)->get('job')->row();
		$this->assertEquals('Musician', $job1->name);

		// Do the update
		$this->db->set('name', 'Vocalist');
		$this->db->update('job', NULL, 'id = 4');

		// Check updated record
		$job1 = $this->db->where('id', 4)->get('job')->row();
		$this->assertEquals('Vocalist', $job1->name);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * @see ./mocks/schema/skeleton.php
	 */
	public function test_update_batch()
	{
		// Check initial record
		$job1 = $this->db->where('id', 1)->get('job')->row();
		$this->assertEquals('Developer', $job1->name);
		
		// Check initial record
		$job2 = $this->db->where('id', 2)->get('job')->row();
		$this->assertEquals('Politician', $job2->name);
		
		$job_datas = array(
			array('id' => 1, 'name' => 'Programmer'), 
			array('id' => 2, 'name' => 'Musician')
		);
		
		// Do the update
		$this->assertEquals(2, $this->db->update_batch('job', $job_datas, 'id'));

		// Check updated record
		$job1 = $this->db->where('id', 1)->get('job')->row();
		$this->assertEquals('Programmer', $job1->name);
		
		$job2 = $this->db->where('id', 2)->get('job')->row();
		$this->assertEquals('Musician', $job2->name);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * @see ./mocks/schema/skeleton.php
	 */
	public function test_update_batch_with_keys()
	{
		// Check initial record
		$job1 = $this->db->where('id', 1)->get('job')->row();
		$this->assertEquals('Developer', $job1->name);
		
		// Check initial record
		$job2 = $this->db->where('id', 2)->get('job')->row();
		$this->assertEquals('Politician', $job2->name);

		$job_datas = array(
			array('id' => 1, 'name' => 'Racing driver', 'description' => 'Nascar racing driver'), 
			array('id' => 2, 'name' => 'Football player', 'description' => 'Milan defender')
		);
		
		// Do the update
		$this->assertEquals(2, $this->db->update_batch('job', $job_datas, 'id', 500, array('name')));
			
		// Check updated record
		$job1 = $this->db->where('id', 1)->get('job')->row();
		$this->assertEquals('Racing driver', $job1->name);
		$this->assertEquals('Awesome job, but sometimes makes you bored', $job1->description);
		
		$job2 = $this->db->where('id', 2)->get('job')->row();
		$this->assertEquals('Football player', $job2->name);
		$this->assertEquals('This is not really a job', $job2->description);
	}

	// ------------------------------------------------------------------------

	/**
	 * @see ./mocks/schema/skeleton.php
	 */
	public function test_update_batch_size()
	{
		$job_datas = array(
			array('id' => 1, 'name' => 'Programmer'),
			array('id' => 2, 'name' => 'Senator'),
			array('id' => 3, 'name' => 'Auditor')
		);

		$this->assertEquals(3, $this->db->update_batch('job', $job_datas, 'id', 2));

		$this->assertEquals('Programmer', $this->db->where('id', 1)->get('job')->row()->name);
		$this->assertEquals('Senator', $this->db->where('id', 2)->get('job')->row()->name);
		$this->assertEquals('Auditor', $this->db->where('id', 3)->get('job')->row()->name);
		$this->assertEquals('Musician', $this->db->where('id', 4)->get('job')->row()->name);
	}

	// ------------------------------------------------------------------------

	/**
	 * @see ./mocks/schema/skeleton.php
	 */
	public function test_update_batch_where_applies_to_every_batch()
	{
		$user_datas = array(
			array('id' => 1, 'name' => 'Derek'),
			array('id' => 2, 'name' => 'Mahmoud'),
			array('id' => 3, 'name' => 'Richard')
		);

		// User 2 is not from the US, so it must not be updated by any of the batches
		$this->db->where('country', 'US');
		$this->assertEquals(2, $this->db->update_batch('user', $user_datas, 'id', 1));

		$this->assertEquals('Derek', $this->db->where('id', 1)->get('user')->row()->name);
		$this->assertEquals('Ahmadinejad', $this->db->where('id', 2)->get('user')->row()->name);
		$this->assertEquals('Richard', $this->db->where('id', 3)->get('user')->row()->name);
	}
}