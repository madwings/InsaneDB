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

	/**
	 * Connect to the fake server instead of creating a PDO object
	 *
	 * @return	Mock_Database_Fake_Connection
	 * @throws	PDOException	When the server refuses the connection, like PDO does
	 */
	protected function _pdo_connect()
	{
		$conn = $this->fake_server->connect($this->dsn, $this->username, $this->options);

		if ($conn === FALSE)
		{
			throw new PDOException('SQLSTATE[HY000] [2002] Connection refused');
		}

		return $conn;
	}

	/**
	 * Don't sleep between retries
	 */
	protected function _retry_backoff()
	{
	}

	/**
	 * Throw instead of printing the error and exiting, like the show_error() mock does
	 *
	 * @param	string|array	$error
	 * @param	string		$swap
	 * @param	bool		$native
	 * @return	void
	 * @throws	RuntimeException
	 */
	public function display_error($error = '', $swap = '', $native = FALSE)
	{
		throw new RuntimeException('DB Error: '.implode(' | ', (array) $error));
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
