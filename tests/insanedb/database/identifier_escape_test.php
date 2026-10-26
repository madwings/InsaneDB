<?php

/**
 * Identifier escaping must use the escape character of each connection,
 * also when connections of different platforms are used side by side.
 */
class Identifier_escape_test extends CI_TestCase {

	public function test_escape_identifiers_per_connection()
	{
		$mysql = Mock_Database_Fake_Mysql::create(array(), FALSE);
		$pgsql = Mock_Database_Fake_Pgsql::create(array(), FALSE);

		$this->assertEquals('`job`.`name`', $mysql->escape_identifiers('job.name'));
		$this->assertEquals('"job"."name"', $pgsql->escape_identifiers('job.name'));
		$this->assertEquals('`job`.`name`', $mysql->escape_identifiers('job.name'));
	}

	// ------------------------------------------------------------------------

	public function test_compiled_queries_per_connection()
	{
		$pgsql = Mock_Database_Fake_Pgsql::create();
		$mysql = Mock_Database_Fake_Mysql::create();

		$this->assertEquals(
			"SELECT \"name\"\nFROM \"job\"\nWHERE \"id\" >= 2",
			$pgsql->select('name')->where('id >=', 2)->get_compiled_select('job')
		);

		$this->assertEquals(
			"SELECT `name`\nFROM `job`\nWHERE `id` >= 2",
			$mysql->select('name')->where('id >=', 2)->get_compiled_select('job')
		);
	}

	// ------------------------------------------------------------------------

	public function test_string_literals_per_connection()
	{
		$pgsql = Mock_Database_Fake_Pgsql::create(array(), FALSE);
		$mysql = Mock_Database_Fake_Mysql::create(array(), FALSE);

		// Double quotes delimit identifiers in PostgreSQL, but string literals in MySQL
		$this->assertEquals(
			"SELECT *\nFROM \"job\"\nGROUP BY \"name\", 'x'",
			$pgsql->group_by(array('"name"', "'x'"))->get_compiled_select('job')
		);

		$this->assertEquals(
			"SELECT *\nFROM `job`\nGROUP BY \"name\", 'x'",
			$mysql->group_by(array('"name"', "'x'"))->get_compiled_select('job')
		);
	}

}
