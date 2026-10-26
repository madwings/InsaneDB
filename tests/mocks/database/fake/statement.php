<?php

/**
 * Fake PDOStatement
 */
class Mock_Database_Fake_Statement {

	protected $rows;
	protected $affected;
	protected $position = 0;

	public function __construct(array $rows, $affected)
	{
		$this->rows = array_values($rows);
		$this->affected = (int) $affected;
	}

	public function rowCount()
	{
		return $this->affected;
	}

	public function columnCount()
	{
		return isset($this->rows[0]) ? count($this->rows[0]) : 0;
	}

	public function getColumnMeta($i)
	{
		$names = isset($this->rows[0]) ? array_keys($this->rows[0]) : array();
		return isset($names[$i]) ? array('name' => $names[$i], 'native_type' => 'VAR_STRING', 'len' => 255) : FALSE;
	}

	public function fetch($mode = NULL)
	{
		return isset($this->rows[$this->position]) ? $this->rows[$this->position++] : FALSE;
	}

	public function fetchObject($class_name = 'stdClass')
	{
		if (($row = $this->fetch()) === FALSE)
		{
			return FALSE;
		}

		$object = new $class_name();
		foreach ($row as $key => $value)
		{
			$object->$key = $value;
		}

		return $object;
	}

	public function closeCursor()
	{
		return TRUE;
	}

}
