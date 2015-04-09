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
 * Database Driver Class for write/read mode
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
abstract class CI_DB_driver_mstrslve extends CI_DB_driver_core {

	/**
	 * Databases credentials in write/read mode
	 *
	 * @var	array
	 */
	public $cred;
	
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
		parent::__construct();
		$this->mstrslve = TRUE;

		log_message('info', 'Database Read Write Driver Class Initialized');
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Initialize database credentials when in write/read setup
	 *
	 * @return	void
	 */
	private function _set_cred() 
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
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Initialize Database Settings
	 *
	 * @return	bool
	 */
	public function initialize()
	{
		/* If an established connection is available, then there's
		 * no need to connect and select the database.
		 *
		 * Depending on the database driver, conn_id can be either
		 * boolean TRUE, a resource or an object.
		 */
		if ($this->conn_id)
		{
			return TRUE;
		}

		// ----------------------------------------------------------------
		
		// Set credentials first
		$this->_set_cred();
		
		// Connect to the database and set the connection ID
		$this->conn_id = $this->db_connect($this->pconnect);

		// No connection resource? Check if there is a failover else throw an error
		if ( ! $this->conn_id)
		{
			// Check if there is a failover set
			if ( ! empty($this->failover) && is_array($this->failover))
			{
				// Go over all the failovers
				foreach ($this->failover as $failover)
				{
					// Replace the current settings with those of the failover
					foreach ($failover as $key => $val)
					{
						$this->$key = $val;
					}

					// Try to connect
					$this->conn_id = $this->db_connect($this->pconnect);

					// If a connection is made break the foreach loop
					if ($this->conn_id)
					{
						break;
					}
				}
			}

			// We still don't have a connection?
			if ( ! $this->conn_id)
			{
				log_message('error', 'Unable to connect to the database');

				if ($this->db_debug)
				{
					$this->display_error('db_unable_to_connect');
				}

				return FALSE;
			}
		}

		// Now we set the character set and that's all
		return $this->db_set_charset($this->char_set);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Configure db params for write/read setup
	 *
	 * @param	string	the sql query
	 * @return	void
	 */
	private function _config_write_read($sql = '') 
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
	 * Start Transaction
	 *
	 * @param	bool	$test_mode = FALSE
	 * @return	void
	 */
	public function trans_start($test_mode = FALSE)
	{
		if ( ! $this->trans_enabled)
		{
			return FALSE;
		}

		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 0)
		{
			$this->_trans_depth += 1;
			return;
		}
		
		$this->db_force('write', FALSE);
		$this->trans_begin($test_mode);
		$this->_trans_depth += 1;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Complete Transaction
	 *
	 * @return	bool
	 */
	public function trans_complete()
	{
		if ( ! $this->trans_enabled)
		{
			return FALSE;
		}

		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 1)
		{
			$this->_trans_depth -= 1;
			return TRUE;
		}
		else
		{
			$this->_trans_depth = 0;
		}

		// The query() function will set this flag to FALSE in the event that a query failed
		if ($this->_trans_status === FALSE OR $this->_trans_failure === TRUE)
		{
			$this->trans_rollback();

			// If we are NOT running in strict mode, we will reset
			// the _trans_status flag so that subsequent groups of transactions
			// will be permitted.
			if ($this->trans_strict === FALSE)
			{
				$this->_trans_status = TRUE;
			}

			log_message('debug', 'DB Transaction Failure');
			$this->db_force_clear();
			return FALSE;
		}

		$this->trans_commit();
		$this->db_force_clear();
		return TRUE;
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
