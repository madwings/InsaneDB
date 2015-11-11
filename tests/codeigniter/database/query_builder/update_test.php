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
		$this->db->update_batch('job', $job_datas, 'id');

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
		$this->db->update_batch('job', $job_datas, 'id', 500, array('name'));
			
		// Check updated record
		$job1 = $this->db->where('id', 1)->get('job')->row();
		$this->assertEquals('Racing driver', $job1->name);
		$this->assertEquals('Awesome job, but sometimes makes you bored', $job1->description);
		
		$job2 = $this->db->where('id', 2)->get('job')->row();
		$this->assertEquals('Football player', $job2->name);
		$this->assertEquals('This is not really a job', $job2->description);
	}
}