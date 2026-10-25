<?php

/**
 * Fake PDO connection
 *
 * Implements the subset of the PDO API used by the PDO drivers.
 */
class Mock_Database_Fake_Connection {

	/**
	 * @var string DSN used to open the connection
	 */
	public $dsn;

	/**
	 * @var int Index of the connection on the server
	 */
	public $index;

	/**
	 * @var array Attributes set with setAttribute()
	 */
	public $attributes = array();

	/**
	 * @var bool
	 */
	public $in_transaction = FALSE;

	/**
	 * @var Mock_Database_Fake_Server
	 */
	protected $server;

	/**
	 * @var array
	 */
	protected $error_info = array('00000', NULL, NULL);

	/**
	 * @var string
	 */
	protected $status = 'Connection OK; waiting to send.';

	public function __construct(Mock_Database_Fake_Server $server, $dsn, $index)
	{
		$this->server = $server;
		$this->dsn = $dsn;
		$this->index = $index;
	}

	public function query($sql)
	{
		$this->_log($sql);
		$outcome = $this->server->next_outcome();

		if (isset($outcome['error']))
		{
			$this->error_info = $outcome['error'];
			isset($outcome['status']) && $this->status = $outcome['status'];
			return FALSE;
		}

		$this->error_info = array('00000', NULL, NULL);
		$rows = isset($outcome['rows']) ? $outcome['rows'] : array();
		$affected = isset($outcome['affected']) ? $outcome['affected'] : count($rows);

		return new Mock_Database_Fake_Statement($rows, $affected);
	}

	public function errorInfo()
	{
		return $this->error_info;
	}

	public function quote($str)
	{
		return "'".str_replace("'", "''", (string) $str)."'";
	}

	public function getAttribute($attribute)
	{
		// Like PDO, getAttribute() clears the last error
		$this->error_info = array('00000', NULL, NULL);

		switch ($attribute)
		{
			case PDO::ATTR_CONNECTION_STATUS:
				return $this->status;
			case PDO::ATTR_SERVER_VERSION:
			case PDO::ATTR_CLIENT_VERSION:
				return '99.0';
		}

		return NULL;
	}

	public function setAttribute($attribute, $value)
	{
		$this->attributes[$attribute] = $value;
		return TRUE;
	}

	public function beginTransaction()
	{
		$this->_log('BEGIN');

		if ($this->server->fail_begin)
		{
			return FALSE;
		}

		return $this->in_transaction = TRUE;
	}

	public function commit()
	{
		$this->_log('COMMIT');
		$this->in_transaction = FALSE;
		return TRUE;
	}

	public function rollBack()
	{
		$this->_log('ROLLBACK');

		if ($this->server->rollback_outcome === 'throw')
		{
			throw new PDOException('There is no active transaction');
		}
		elseif ($this->server->rollback_outcome === 'fail')
		{
			return FALSE;
		}

		$this->in_transaction = FALSE;
		return TRUE;
	}

	public function inTransaction()
	{
		return $this->in_transaction;
	}

	public function lastInsertId($name = NULL)
	{
		return '1';
	}

	protected function _log($sql)
	{
		$this->server->log[] = array('sql' => $sql, 'dsn' => $this->dsn, 'conn' => $this->index);
	}

}
