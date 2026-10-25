<?php

/**
 * Shared behavior of the fake PDO drivers
 *
 * Connections are opened on a Mock_Database_Fake_Server instead of a real
 * server. Everything else is the real driver code.
 */
trait Mock_Database_Fake_Driver {

	/**
	 * @var Mock_Database_Fake_Server
	 */
	public $fake_server;

	/**
	 * Create a fake driver instance
	 *
	 * @param	array	$params		Overrides of the default configuration
	 * @param	bool	$connect	Whether to connect right away, as DB() does
	 * @return	static
	 */
	public static function create(array $params = array(), $connect = TRUE)
	{
		$params = array_merge(array(
			'dsn' => '',
			'hostname' => 'localhost',
			'username' => 'user',
			'password' => 'secret',
			'database' => 'ci_test',
			'db_debug' => FALSE,
			'fake_server' => new Mock_Database_Fake_Server()
		), $params);

		$db = new static($params);
		$connect && $db->initialize();

		return $db;
	}

	public function db_connect($persistent = FALSE)
	{
		return $this->fake_server->connect($this->dsn);
	}

	/**
	 * Read private/protected properties of the driver
	 *
	 * @param	string	$name
	 * @return	mixed
	 */
	public function fake_get($name)
	{
		$property = new ReflectionProperty('CI_DB_driver', $name);
		PHP_VERSION_ID < 80100 && $property->setAccessible(TRUE);
		return $property->getValue($this);
	}

}
