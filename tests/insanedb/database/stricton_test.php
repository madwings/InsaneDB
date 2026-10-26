<?php

/**
 * Strict SQL mode on a real MySQL server
 *
 * Verifies that the 'stricton' setting reaches the server through the
 * PDO init command, also after a reconnect.
 */
class Stricton_test extends CI_TestCase {

	/**
	 * @var CI_DB_pdo_mysql_driver[]
	 */
	protected $connections = array();

	// ------------------------------------------------------------------------

	public function set_up()
	{
		if (DB_DRIVER !== 'pdo/mysql')
		{
			$this->markTestSkipped('stricton is only supported by the MySQL driver');
		}

		// Loads what DB() would normally load
		require_once __DIR__.'/../../mocks/database/fake/bootstrap.php';
	}

	// ------------------------------------------------------------------------

	public function tear_down()
	{
		foreach ($this->connections as $db)
		{
			$db->close();
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Connect to the test database with the given stricton setting
	 *
	 * @param	bool|null	$stricton
	 * @return	CI_DB_pdo_mysql_driver
	 */
	protected function connect($stricton)
	{
		$config = Mock_Database_DB::config(DB_DRIVER);
		$config = $config[DB_DRIVER];
		$config['subdriver'] = $config['driver'];
		unset($config['driver']);
		$config['db_debug'] = FALSE;
		$config['stricton'] = $stricton;

		$db = new CI_DB_pdo_mysql_driver($config);
		$db->initialize();

		return $this->connections[] = $db;
	}

	// ------------------------------------------------------------------------

	protected function sql_mode(CI_DB_pdo_mysql_driver $db)
	{
		$query = $db->query('SELECT @@session.sql_mode AS mode');
		$this->assertNotFalse($query, 'Unable to read sql_mode: '.$db->error()['message']);

		return explode(',', $query->row()->mode);
	}

	// ------------------------------------------------------------------------

	public function test_strict_mode_enabled()
	{
		$db = $this->connect(TRUE);

		$this->assertContains('STRICT_ALL_TABLES', $this->sql_mode($db));
	}

	// ------------------------------------------------------------------------

	public function test_strict_mode_disabled()
	{
		$db = $this->connect(FALSE);

		$mode = $this->sql_mode($db);
		$this->assertNotContains('STRICT_ALL_TABLES', $mode);
		$this->assertNotContains('STRICT_TRANS_TABLES', $mode);
	}

	// ------------------------------------------------------------------------

	public function test_server_default_is_kept_when_not_configured()
	{
		$db = $this->connect(NULL);

		$pdo = new PDO($db->dsn, $db->username, $db->password);
		$expected = $pdo->query('SELECT @@session.sql_mode')->fetchColumn();

		$this->assertEquals(explode(',', $expected), $this->sql_mode($db));
	}

	// ------------------------------------------------------------------------

	public function test_strict_mode_survives_a_reconnect()
	{
		$db = $this->connect(TRUE);
		$options = $db->options;

		$db->reconnect();

		$this->assertContains('STRICT_ALL_TABLES', $this->sql_mode($db));
		$this->assertSame($options, $db->options);
	}

	// ------------------------------------------------------------------------

	public function test_strict_mode_rejects_out_of_range_values()
	{
		$db = $this->connect(TRUE);
		$db->query('DROP TABLE IF EXISTS stricton_test');
		$this->assertTrue($db->query('CREATE TABLE stricton_test (id INT, name VARCHAR(2))'));

		$this->assertFalse($db->query("INSERT INTO stricton_test (id, name) VALUES (1, 'too long')"));
		$this->assertStringContainsString('too long', $db->error()['message']);

		$db->query('DROP TABLE stricton_test');
	}

}
