<?php

/**
 * Fake database server
 *
 * Hands out Mock_Database_Fake_Connection objects (stand-ins for PDO) and
 * decides the outcome of each query, so that the retry, reconnect and
 * read/write logic of the drivers can be tested without a real server.
 *
 * Every query, including transaction commands, is recorded in $log.
 */
class Mock_Database_Fake_Server {

	/**
	 * @var array Scripted outcomes, consumed one per query (NULL = success)
	 */
	public $outcomes = array();

	/**
	 * @var array Executed queries: array('sql' => string, 'dsn' => string, 'conn' => int)
	 */
	public $log = array();

	/**
	 * @var Mock_Database_Fake_Connection[] Every connection opened so far
	 */
	public $connections = array();

	/**
	 * @var int Number of upcoming connection attempts that should fail
	 */
	public $fail_connects = 0;

	/**
	 * @var bool Whether beginTransaction() should fail
	 */
	public $fail_begin = FALSE;

	/**
	 * @var string|null Outcome of rollBack(): NULL (success), 'fail' (returns FALSE) or 'throw' (PDOException)
	 */
	public $rollback_outcome = NULL;

	/**
	 * Open a connection
	 *
	 * @param	string	$dsn
	 * @return	Mock_Database_Fake_Connection|bool
	 */
	public function connect($dsn)
	{
		if ($this->fail_connects > 0)
		{
			$this->fail_connects--;
			return FALSE;
		}

		return $this->connections[] = new Mock_Database_Fake_Connection($this, (string) $dsn, count($this->connections));
	}

	/**
	 * Make the next query fail
	 *
	 * @param	string	$sqlstate	errorInfo()[0]
	 * @param	int	$code		errorInfo()[1], the driver specific error code
	 * @param	string	$status		PDO::ATTR_CONNECTION_STATUS while the error is active (pgsql)
	 * @return	Mock_Database_Fake_Server
	 */
	public function fail($sqlstate, $code = NULL, $status = NULL)
	{
		$this->outcomes[] = array(
			'error' => array($sqlstate, $code, 'Fake error '.$sqlstate.($code === NULL ? '' : '/'.$code)),
			'status' => $status
		);

		return $this;
	}

	/**
	 * Make the next query succeed
	 *
	 * @param	array	$rows		Rows (associative arrays) of the result set
	 * @param	int	$affected	Affected rows reported by rowCount()
	 * @return	Mock_Database_Fake_Server
	 */
	public function succeed(array $rows = array(), $affected = NULL)
	{
		$this->outcomes[] = array('rows' => $rows, 'affected' => $affected);
		return $this;
	}

	/**
	 * @return	array|null
	 */
	public function next_outcome()
	{
		return array_shift($this->outcomes);
	}

	/**
	 * Executed SQL statements
	 *
	 * @param	bool	$with_transactions	Whether to include BEGIN/COMMIT/ROLLBACK
	 * @return	string[]
	 */
	public function queries($with_transactions = FALSE)
	{
		$queries = array();
		foreach ($this->log as $entry)
		{
			if ($with_transactions OR ! in_array($entry['sql'], array('BEGIN', 'COMMIT', 'ROLLBACK'), TRUE))
			{
				$queries[] = $entry['sql'];
			}
		}

		return $queries;
	}

	/**
	 * Last log entry for the given SQL statement
	 *
	 * @param	string	$sql
	 * @return	array|null
	 */
	public function entry($sql)
	{
		for ($i = count($this->log) - 1; $i >= 0; $i--)
		{
			if ($this->log[$i]['sql'] === $sql)
			{
				return $this->log[$i];
			}
		}

		return NULL;
	}

}
