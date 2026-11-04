<?php
/**
 * InsaneDB
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2019 - 2022, CodeIgniter Foundation
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	InsaneDB
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2019, British Columbia Institute of Technology (https://bcit.ca/)
 * @copyright	Copyright (c) 2019 - 2022, CodeIgniter Foundation (https://codeigniter.com/)
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 * @since	Version 2.1.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PDO Result Class
 *
 * This class extends the parent result class: CI_DB_result
 *
 * @package		InsaneDB
 * @subpackage	Drivers
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/userguide3/database/
 */
class CI_DB_pdo_result extends CI_DB_result {

	/**
	 * Database driver object
	 *
	 * Not kept by the base class, which is used for the serialized query cache.
	 *
	 * @var	CI_DB_pdo_driver
	 */
	protected $db;

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @param	object	$driver_object
	 * @return	void
	 */
	public function __construct($driver_object)
	{
		parent::__construct($driver_object);
		$this->db = $driver_object;
	}

	// --------------------------------------------------------------------

	/**
	 * Number of rows in the result set
	 *
	 * @return	int
	 */
	public function num_rows()
	{
		if ( ! is_int($this->num_rows))
		{
			if (($num_rows = count($this->result_array)) > 0 
			|| ($num_rows = count($this->result_object)) > 0
			|| ($num_rows = $this->result_id->rowCount()) > 0)
			{
				$this->num_rows = $num_rows;
			}
			else
			{
				$this->num_rows = count($this->result_array());
			}

		}

		return $this->num_rows;
	}

	// --------------------------------------------------------------------

	/**
	 * Number of fields in the result set
	 *
	 * @return	int
	 */
	public function num_fields()
	{
		return $this->result_id->columnCount();
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch Field Names
	 *
	 * Generates an array of column names
	 *
	 * @return	array|bool
	 */
	public function list_fields()
	{
		$field_names = array();
		for ($i = 0, $c = $this->num_fields(); $i < $c; $i++)
		{
			if (($field = $this->_column_meta($i)) === FALSE)
			{
				return $this->_unsupported_meta();
			}

			$field_names[$i] = $field['name'];
		}

		return $field_names;
	}

	// --------------------------------------------------------------------

	/**
	 * Field data
	 *
	 * Generates an array of objects containing field meta-data
	 *
	 * @return	array|bool
	 */
	public function field_data()
	{
		$retval = array();

		for ($i = 0, $c = $this->num_fields(); $i < $c; $i++)
		{
			if (($field = $this->_column_meta($i)) === FALSE)
			{
				return $this->_unsupported_meta();
			}

			$retval[$i]			= new stdClass();
			$retval[$i]->name		= $field['name'];
			$retval[$i]->type		= $field['native_type'] ?? NULL;
			$retval[$i]->max_length		= ($field['len'] > 0) ? $field['len'] : NULL;
			$retval[$i]->primary_key	= (int) ( ! empty($field['flags']) && in_array('primary_key', $field['flags'], TRUE));
		}

		return $retval;
	}

	// --------------------------------------------------------------------

	/**
	 * Column meta-data
	 *
	 * Not all subdrivers support getColumnMeta(). Depending on PDO::ATTR_ERRMODE
	 * it then returns FALSE or throws a PDOException.
	 *
	 * @param	int	$i	Column index
	 * @return	array|false
	 */
	protected function _column_meta($i)
	{
		try
		{
			return $this->result_id->getColumnMeta($i);
		}
		catch (PDOException $e)
		{
			return FALSE;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Report that column meta-data is not supported
	 *
	 * @return	bool
	 */
	protected function _unsupported_meta()
	{
		return ($this->db->db_debug) ? $this->db->display_error('db_unsupported_feature') : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Free the result
	 *
	 * @return	void
	 */
	public function free_result()
	{
		if (is_object($this->result_id))
		{
			$this->result_id = FALSE;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Result - associative array
	 *
	 * Returns the result set as an array
	 *
	 * @return	array
	 */
	protected function _fetch_assoc()
	{
		return $this->result_id->fetch(PDO::FETCH_ASSOC);
	}

	// --------------------------------------------------------------------

	/**
	 * Result - object
	 *
	 * Returns the result set as an object
	 *
	 * @param	string	$class_name
	 * @return	object
	 */
	protected function _fetch_object($class_name = 'stdClass')
	{
		return $this->result_id->fetchObject($class_name);
	}

}
