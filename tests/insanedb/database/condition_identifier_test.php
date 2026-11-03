<?php

class Condition_identifier_test extends CI_TestCase {

	protected $db;

	public function set_up()
	{
		$this->db = Mock_Database_Fake_Mysql::create();
	}

	/**
	 * @dataProvider condition_method_provider
	 */
	public function test_condition_methods_reject_untrusted_sql($method, array $arguments)
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Condition fields must be simple identifiers');

		call_user_func_array(array($this->db, $method), $arguments);
	}

	public function condition_method_provider()
	{
		$cases = array();
		foreach (array('name) OR 1=1 --', "ENGINE like '*' union select database(),2,3,4,5,6 -- -") as $field)
		{
			$cases[] = array('where', array($field, 'value'));
			$cases[] = array('or_where', array($field, 'value'));
			$cases[] = array('having', array($field, 'value'));
			$cases[] = array('or_having', array($field, 'value'));
			$cases[] = array('where_in', array($field, array('value')));
			$cases[] = array('or_where_in', array($field, array('value')));
			$cases[] = array('where_not_in', array($field, array('value')));
			$cases[] = array('or_where_not_in', array($field, array('value')));
			$cases[] = array('having_in', array($field, array('value')));
			$cases[] = array('or_having_in', array($field, array('value')));
			$cases[] = array('having_not_in', array($field, array('value')));
			$cases[] = array('or_having_not_in', array($field, array('value')));
			$cases[] = array('like', array($field, 'value'));
			$cases[] = array('or_like', array($field, 'value'));
			$cases[] = array('not_like', array($field, 'value'));
			$cases[] = array('or_not_like', array($field, 'value'));
		}

		return $cases;
	}

	/**
	 * @dataProvider invalid_field_provider
	 */
	public function test_invalid_where_fields_are_rejected($field)
	{
		$this->expectException(InvalidArgumentException::class);
		$this->db->where($field, 1);
	}

	public function invalid_field_provider()
	{
		return array(
			array('name OR 1=1'),
			array("name' UNION SELECT secret --"),
			array('name; DELETE FROM job'),
			array('name/*comment*/'),
			array('COUNT(name)'),
			array('`name`'),
			array('1'),
			array('table..name'),
			array("name\nOR 1=1"),
			array("\xff"),
			array(NULL)
		);
	}

	public function test_simple_identifiers_and_comparison_operators()
	{
		$sql = $this->db->where('job.id >=', 2)
			->or_where('job.name !=', "O'Reilly")
			->where('deleted_at IS NOT NULL')
			->where_in('job.name', array('Developer', "O'Reilly"))
			->like('job.name', 'dev')
			->get_compiled_select('job');

		$this->assertStringContainsString('`job`.`id` >= 2', $sql);
		$this->assertStringContainsString("`job`.`name` != 'O''Reilly'", $sql);
		$this->assertStringContainsString('`deleted_at` IS NOT NULL', $sql);
		$this->assertStringContainsString("'O''Reilly'", $sql);
		$this->assertStringContainsString('`job`.`name` LIKE', $sql);
	}

	/**
	 * @dataProvider valid_where_operator_provider
	 */
	public function test_supported_where_operators($field, $value, $expected)
	{
		$sql = $this->db->where($field, $value)->get_compiled_select('job');
		$this->assertStringContainsString($expected, $sql);
	}

	public function valid_where_operator_provider()
	{
		return array(
			array('id', 5, '`id` = 5'),
			array('id =', 5, '`id` = 5'),
			array('id !=', 5, '`id` != 5'),
			array('id <>', 5, '`id` <> 5'),
			array('id<', 5, '`id` < 5'),
			array('id <=', 5, '`id` <= 5'),
			array('id >', 5, '`id` > 5'),
			array('id >=', 5, '`id` >= 5'),
			array('id', NULL, '`id` IS NULL'),
			array('id IS NULL', NULL, '`id` IS NULL'),
			array('id IS NOT NULL', NULL, '`id` IS NOT NULL')
		);
	}

	public function test_unicode_and_dollar_identifiers()
	{
		$field = "\xc3\xa9".'quipe.member$code';
		$sql = $this->db->where($field, 1)->get_compiled_select('job');

		$this->assertStringContainsString('`'."\xc3\xa9".'quipe`.`member$code` = 1', $sql);
	}

	public function test_validation_does_not_depend_on_the_global_escape_setting()
	{
		$db = Mock_Database_Fake_Mysql::create(array('_protect_identifiers' => FALSE));

		$this->expectException(InvalidArgumentException::class);
		$db->where('name OR 1=1', 'value');
	}

	public function test_non_boolean_escape_argument_does_not_disable_validation()
	{
		$this->expectException(InvalidArgumentException::class);
		$this->db->where('name OR 1=1', 'value', 0);
	}

	public function test_trusted_field_expressions_keep_value_escaping()
	{
		$expression = $this->db->trusted_expression("COALESCE(name, '')");
		$sql = $this->db->where($expression, "O'Reilly")->get_compiled_select('job');
		$this->assertMatchesRegularExpression("/COALESCE\\(name, ''\\)\\s*=\\s+'O''Reilly'/", $sql);

		$expression = $this->db->trusted_expression('LOWER(name)');
		$sql = $this->db->having_in($expression, array("O'Reilly"))->get_compiled_select('job');
		$this->assertStringContainsString("LOWER(name) IN('O''Reilly')", $sql);

		$sql = $this->db->like($expression, "O'Reilly")->get_compiled_select('job');
		$this->assertMatchesRegularExpression("/LOWER\\(name\\)\\s+LIKE\\s+'%O''Reilly%'/", $sql);
	}

	public function test_trusted_expression_preserves_an_explicit_operator()
	{
		$expression = $this->db->trusted_expression("COALESCE(name, '') >=");
		$sql = $this->db->where($expression, 'A')->get_compiled_select('job');

		$this->assertStringContainsString("COALESCE(name, '') >= 'A'", $sql);
	}

	public function test_trusted_complete_condition_is_preserved()
	{
		$condition = "id > 2 AND name != 'Accountant'";
		$sql = $this->db->where($this->db->trusted_expression($condition))->get_compiled_select('job');

		$this->assertStringContainsString("\nWHERE ".$condition, $sql);
	}

	public function test_zero_is_a_valid_trusted_in_expression()
	{
		$sql = $this->db->having_in($this->db->trusted_expression('0'), array(0))
			->get_compiled_select('job');

		$this->assertStringContainsString('HAVING 0 IN(0)', $sql);
	}

	public function test_explicit_escape_false_remains_an_unsafe_opt_out()
	{
		$sql = $this->db->where('`id` > 0', NULL, FALSE)->get_compiled_select('job');
		$this->assertStringContainsString("\nWHERE `id` > 0", $sql);

		$sql = $this->db->where('name', 'CURRENT_TIMESTAMP', FALSE)->get_compiled_select('job');
		$this->assertMatchesRegularExpression('/WHERE name\\s*=\\s+CURRENT_TIMESTAMP/', $sql);

		$sql = $this->db->where_in('LOWER(name)', array('1'), FALSE)->get_compiled_select('job');
		$this->assertStringContainsString('LOWER(name) IN(1)', $sql);

		$sql = $this->db->like('LOWER(name)', 'x', 'both', FALSE)->get_compiled_select('job');
		$this->assertMatchesRegularExpression("/LOWER\\(name\\)\\s+LIKE\\s+'%x%'/", $sql);
	}

	public function test_trusted_expressions_survive_query_builder_cache()
	{
		$this->db->start_cache();
		$this->db->where($this->db->trusted_expression('LOWER(name)'), "O'Reilly");
		$this->db->having_in($this->db->trusted_expression('SUM(id)'), array(1, 2));
		$this->db->stop_cache();

		for ($i = 0; $i < 2; $i++)
		{
			$sql = $this->db->get_compiled_select('job');
			$this->assertMatchesRegularExpression("/LOWER\\(name\\)\\s*=\\s+'O''Reilly'/", $sql);
			$this->assertStringContainsString('HAVING SUM(id) IN(1, 2)', $sql);
			$this->assertSame(1, substr_count($sql, 'LOWER(name)'));
			$this->assertSame(1, substr_count($sql, 'SUM(id)'));
		}
	}

	/**
	 * @dataProvider invalid_expression_provider
	 */
	public function test_trusted_expression_requires_a_non_empty_string($expression)
	{
		$this->expectException(InvalidArgumentException::class);
		$this->db->trusted_expression($expression);
	}

	public function invalid_expression_provider()
	{
		return array(array(NULL), array(FALSE), array(1), array(''), array('  '));
	}

	public function test_invalid_where_array_does_not_partially_change_the_query_or_cache()
	{
		$this->db->start_cache();
		try
		{
			$this->db->where(array('valid' => 1, 'name OR 1=1' => 2));
			$this->fail('An invalid field must be rejected');
		}
		catch (InvalidArgumentException $exception)
		{
			$this->assertStringContainsString('simple identifiers', $exception->getMessage());
		}
		$this->db->stop_cache();

		$sql = $this->db->where('id', 1)->get_compiled_select('job');
		$this->assertStringContainsString('`id` = 1', $sql);
		$this->assertStringNotContainsString('valid', $sql);
	}

	public function test_invalid_like_array_does_not_partially_change_the_query()
	{
		try
		{
			$this->db->like(array('valid' => 'x', 'name OR 1=1' => 'y'));
			$this->fail('An invalid field must be rejected');
		}
		catch (InvalidArgumentException $exception)
		{
			$this->assertStringContainsString('simple identifiers', $exception->getMessage());
		}

		$sql = $this->db->get_compiled_select('job');
		$this->assertStringNotContainsString('WHERE', $sql);
	}

	public function test_invalid_having_array_does_not_partially_change_the_query()
	{
		try
		{
			$this->db->having(array('valid' => 1, 'name OR 1=1' => 2));
			$this->fail('An invalid field must be rejected');
		}
		catch (InvalidArgumentException $exception)
		{
			$this->assertStringContainsString('simple identifiers', $exception->getMessage());
		}

		$sql = $this->db->get_compiled_select('job');
		$this->assertStringNotContainsString('HAVING', $sql);
	}

	public function test_update_string_accepts_a_trusted_condition()
	{
		$condition = $this->db->trusted_expression('id = 1');
		$sql = $this->db->update_string('job', array('name' => 'safe'), $condition);

		$this->assertStringContainsString("`name` = 'safe'", $sql);
		$this->assertStringContainsString('WHERE id = 1', $sql);
	}

	public function test_invalid_get_where_does_not_add_a_table()
	{
		try
		{
			$this->db->get_where('unexpected', array('name OR 1=1' => 'x'));
			$this->fail('An invalid field must be rejected');
		}
		catch (InvalidArgumentException $exception)
		{
			$this->assertStringContainsString('simple identifiers', $exception->getMessage());
		}

		$sql = $this->db->get_compiled_select('job');
		$this->assertStringNotContainsString('unexpected', $sql);
		$this->assertStringContainsString('FROM `job`', $sql);
	}

	public function test_invalid_update_does_not_queue_values()
	{
		try
		{
			$this->db->update('job', array('unexpected' => 'x'), array('name OR 1=1' => 'x'));
			$this->fail('An invalid field must be rejected');
		}
		catch (InvalidArgumentException $exception)
		{
			$this->assertStringContainsString('simple identifiers', $exception->getMessage());
		}

		$sql = $this->db->set('name', 'safe')->where('id', 1)->get_compiled_update('job');
		$this->assertStringNotContainsString('unexpected', $sql);
		$this->assertStringContainsString("`name` = 'safe'", $sql);
	}

	public function test_invalid_delete_does_not_execute_any_query()
	{
		try
		{
			$this->db->delete(array('first', 'second'), array('name OR 1=1' => 'x'));
			$this->fail('An invalid field must be rejected');
		}
		catch (InvalidArgumentException $exception)
		{
			$this->assertStringContainsString('simple identifiers', $exception->getMessage());
		}

		$this->assertSame(array(), $this->db->fake_server->queries());
	}

	public function test_pdo_sqlite_rejects_the_attack_and_executes_a_safe_query()
	{
		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is required');
		}

		$db = new CI_DB_pdo_sqlite_driver(array('dsn' => 'sqlite::memory:'));
		$db->initialize();
		$this->assertInstanceOf(PDO::class, $db->conn_id);
		$db->conn_id->exec('CREATE TABLE members (id INTEGER, name TEXT)');
		$db->conn_id->exec("INSERT INTO members VALUES (1, 'guest'), (2, 'admin')");
		$db->conn_id->exec('CREATE TABLE secrets (secret TEXT)');
		$db->conn_id->exec("INSERT INTO secrets VALUES ('private')");

		$attack = 'id IN (0) UNION SELECT secret FROM secrets -- ';
		$sql = $db->select('name')->or_where_not_in($attack, array(2), FALSE)->get_compiled_select('members');
		$result = $db->conn_id->query($sql);
		$this->assertNotFalse($result);
		$this->assertSame(array('private'), $result->fetchAll(PDO::FETCH_COLUMN, 0));

		try
		{
			$db->or_where_not_in($attack, array(2));
			$this->fail('An invalid field must be rejected');
		}
		catch (InvalidArgumentException $exception)
		{
			$this->assertStringContainsString('simple identifiers', $exception->getMessage());
		}

		$sql = $db->or_where_not_in('id', array(2))->get_compiled_select('members');
		$result = $db->conn_id->query($sql);
		$this->assertNotFalse($result);
		$this->assertSame(array(1), $result->fetchAll(PDO::FETCH_COLUMN, 0));

		$payload = "guest' OR 1=1 --";
		$insert = $db->conn_id->prepare('INSERT INTO members VALUES (?, ?)');
		$this->assertTrue($insert->execute(array(3, $payload)));

		$expression = $db->trusted_expression("COALESCE(name, '')");
		$sql = $db->where($expression, $payload)->get_compiled_select('members');
		$result = $db->conn_id->query($sql);
		$this->assertNotFalse($result);
		$this->assertSame(array(3), $result->fetchAll(PDO::FETCH_COLUMN, 0));

		$sql = $db->like($expression, $payload, 'none')->get_compiled_select('members');
		$result = $db->conn_id->query($sql);
		$this->assertNotFalse($result);
		$this->assertSame(array(3), $result->fetchAll(PDO::FETCH_COLUMN, 0));
	}

	public function test_database_session_gc_deletes_only_expired_rows()
	{
		if ( ! extension_loaded('pdo_sqlite'))
		{
			$this->markTestSkipped('The pdo_sqlite extension is required');
		}

		$session_dir = SYSTEM_PATH.'libraries'.DIRECTORY_SEPARATOR.'Session'.DIRECTORY_SEPARATOR;
		require_once $session_dir.'Session_driver.php';
		require_once $session_dir.'CI_Session_driver_interface.php';
		require_once $session_dir.'drivers'.DIRECTORY_SEPARATOR.'Session_database_driver.php';

		$db = new CI_DB_pdo_sqlite_driver(array('dsn' => 'sqlite::memory:'));
		$db->initialize();
		$db->conn_id->exec('CREATE TABLE sessions (id TEXT, timestamp INTEGER)');
		$insert = $db->conn_id->prepare('INSERT INTO sessions VALUES (?, ?)');
		$this->assertTrue($insert->execute(array('expired', time() - 7200)));
		$this->assertTrue($insert->execute(array('live', time())));

		$db->where('id', 'unrelated');
		$this->ci_instance_var('db', $db);
		$config = array('save_path' => 'sessions');
		$session = new CI_Session_database_driver($config);

		$this->assertTrue($session->gc(3600));
		$result = $db->conn_id->query('SELECT id FROM sessions');
		$this->assertNotFalse($result);
		$this->assertSame(array('live'), $result->fetchAll(PDO::FETCH_COLUMN, 0));
	}
}
