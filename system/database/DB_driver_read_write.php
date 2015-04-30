<?php
/**
 * InsaneDB
 *
 * PHP Database Library forked from CodeIgniter 3
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2015, Stiliyan Ivanov
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
 * @author	Stiliyan Ivanov
 * @copyright	Copyright (c) 2015, Stiliyan Ivanov (https://github.com/madwings/)
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @link	https://github.com/madwings/InsaneDB
 * @since	Version 1.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Database Driver Class for read/write mode
 *
 * This is the platform-independent write/read mode DB implementation class.
 * It extends base DB implementation class. This class will not be called directly. 
 * Rather, the adapter class for the specific database will extend and instantiate it.
 *
 * @package		InsaneDB
 * @subpackage	Drivers
 * @category	Database
 * @author		Stiliyan Ivanov
 */
abstract class CI_DB_driver_read_write extends CI_DB_driver_core {
	
	/**
	 * Default database in write/read mode when autoinit is used
	 *
	 * @var	string 'write'/'read'
	 */
	public $db_deflt       		= 'read';
	
	/**
	 * Connection ID write
	 *
	 * @var	object|resource
	 */
	public $conn_id_write		= FALSE;
	
	/**
	 * Connection ID read
	 *
	 * @var	object|resource
	 */
	public $conn_id_read		= FALSE;
	
	/**
	 * Force usage of particular database in write/read mode
	 *
	 * @var	string|null
	 */
	protected $db_force   	    = NULL;
	
	/**
	 * Whether to clear forced usage of particular database in write/read mode
	 * after each query
	 *
	 * @var	bool
	 */
	protected $db_force_clr     = TRUE;
	
	/**
	 * Active database in write/read mode
	 *
	 * @var	string|null
	 */
	protected $dbactive      	= NULL;
	
	// --------------------------------------------------------------------

	/**
	 * Class constructor
	 *
	 * @param	array	$params
	 * @return	void
	 */
	public function __construct($params)
	{
		parent::__construct($params);
		$this->read_write = TRUE;

		log_message('info', 'Database Read Write Driver Class Initialized');
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Initialize database credentials when in write/read setup
	 *
	 * @return	void
	 */
	protected function _set_cred() 
	{
		// Handle autoinit in write/read mode
		if ($this->dbactive === NULL) 
		{
			$this->dbactive = $this->db_deflt;
		}
		
		if (is_array($this->{$this->dbactive}))
		{
			foreach ($this->{$this->dbactive} as $key => $val)
			{
				$this->$key = $val;
			}
		}
		$this->_build_dsn();
	}

	// --------------------------------------------------------------------
	
	/**
	 * Configure db params for read/write setup
	 *
	 * @param	string	the sql query
	 * @return	void
	 */
	protected function _config_read_write($sql = '') 
	{	
		if ($this->db_force === 'write' OR ($this->db_force === NULL AND $this->is_write_type($sql) === TRUE))
		{
			if ($this->dbactive === 'read') 
			{
				if(gettype($this->conn_id_read) !== gettype($this->conn_id))
				{
					$this->conn_id_read = $this->conn_id;
				}
				$this->conn_id = &$this->conn_id_write;
			}
			$this->dbactive = 'write';
			log_message('error', 'write ' . $sql);
		}
		else
		{
			if ($this->dbactive === 'write')
			{
				if(gettype($this->conn_id_read) !== gettype($this->conn_id))
				{
					$this->conn_id_write = $this->conn_id;
				}
				$this->conn_id = &$this->conn_id_read;
			}
			$this->dbactive = 'read';
			log_message('error', 'read ' . $sql);
		}
		
		// Clear database force if not explicitly set not to
		if ($this->db_force_clr === TRUE)
		{
			$this->db_force = NULL;
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Force using specific database in write/read connections
	 *
	 * @param	string which database to use
	 * @param	boolean toggle auto/manual database selection after the first query
	 * @return	void
	 */
	public function db_force($database = 'write', $db_force_clr = TRUE)
	{
		$this->db_force = $database;
		$this->db_force_clr = $db_force_clr;
		$this->_config_write_read();
	}

	// --------------------------------------------------------------------
	
	/**
	 * Clear force
	 *
	 * @return	void
	 */
	public function db_force_clear()
	{
		$this->db_force = NULL;
		$this->db_force_clr = TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Close DB Connection
	 *
	 * If no value is passed to the param, it closes all connections
	 *
	 * @param	string	$conn	'active'|'write'|'read'
	 *
	 * @return	void
	 */
	public function close($conn = NULL)
	{
		if ($conn === NULL)
		{
			$closed = 0;
			if (is_resource($this->conn_id_write) OR is_object($this->conn_id_write))
			{
				$this->_close($this->conn_id_write);
				++$closed;
			}
			$this->conn_id_write = FALSE;
			
			if (is_resource($this->conn_id_read) OR is_object($this->conn_id_read))
			{
				$this->_close($this->conn_id_read);
				++$closed;
			}
			$this->conn_id_read = FALSE;
			
			// If write and read were closed, conn_id should not be closed
			if ($closed !== 2 AND (is_resource($this->conn_id) OR is_object($this->conn_id)))
			{
				$this->_close($this->conn_id);
			}
			$this->conn_id = FALSE;
		}
		else if ($conn === 'active')
		{
			$this->_close($this->{"conn_id_{$this->dbactive}"});
		}
		else if ($conn === 'write')
		{
			$this->_close($this->conn_id_write);
		}
		else if ($conn === 'read')
		{
			$this->_close($this->conn_id_read);
		}
	}
}
