<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.2.4 or newer
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is
 * bundled with this package in the files license.txt / license.rst.  It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (http://ellislab.com/)
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Initialize the database
 *
 * @category	Database
 * @author	Stiliyan Ivanov
 * @link	http://codeigniter.com/user_guide/database/
 *
 */
function DB()
{
	// Is the config file in the environment folder?
	if ( ! file_exists($file_path = APPPATH.'config/'.ENVIRONMENT.'/database.php')
		&& ! file_exists($file_path = APPPATH.'config/database.php'))
	{
		show_error('The configuration file database.php does not exist.');
	}

	include($file_path);

	if ( ! isset($db) OR count($db) === 0)
	{
		show_error('No database connection settings were found in the database config file.');
	}

	if ( ! isset($active_group))
	{
		show_error('You have not specified a database connection group via $active_group in your config/database.php file.');
	}
	elseif ( ! isset($db[$active_group]))
	{
		show_error('You have specified an invalid database connection group ('.$active_group.') in your config/database.php file.');
	}

	$params = $db[$active_group];

	// No DB specified yet? Beat them senseless...
	if (empty($params['dbdriver']))
	{
		show_error('You have not selected a database type to connect to.');
	}

	require_once(BASEPATH.'database/DB_driver_single.php');
	if ( ! empty($params['mstrslve']))
	{
		require_once(BASEPATH.'database/DB_driver_mstrslve.php');
		abstract class CI_DB_driver extends CI_DB_driver_mstrslve { }
	}
	else
	{
		abstract class CI_DB_driver extends CI_DB_driver_single { }
	}
	
	if ( ! isset($query_builder) OR $query_builder === TRUE)
	{
		require_once(BASEPATH.'database/DB_query_builder.php');
		if ( ! class_exists('CI_DB', FALSE))
		{
			/**
			 * CI_DB
			 *
			 * Acts as an alias for both CI_DB_driver and CI_DB_query_builder.
			 *
			 * @see	CI_DB_query_builder
			 * @see	CI_DB_driver
			 */
			class CI_DB extends CI_DB_query_builder { }
		}
	}
	elseif ( ! class_exists('CI_DB', FALSE))
	{
		/**
	 	 * @ignore
		 */
		class CI_DB extends CI_DB_driver { }
	}

	// Load the DB driver
	$driver_file = BASEPATH.'database/drivers/'.$params['dbdriver'].'/'.$params['dbdriver'].'_driver.php';

	file_exists($driver_file) OR show_error('Invalid DB driver');
	require_once($driver_file);

	// Instantiate the DB adapter
	$driver = 'CI_DB_'.$params['dbdriver'].'_driver';
	$DB = new $driver($params);

	// Check for a subdriver
	if ( ! empty($DB->subdriver))
	{
		$driver_file = BASEPATH.'database/drivers/'.$DB->dbdriver.'/subdrivers/'.$DB->dbdriver.'_'.$DB->subdriver.'_driver.php';

		if (file_exists($driver_file))
		{
			require_once($driver_file);
			$driver = 'CI_DB_'.$DB->dbdriver.'_'.$DB->subdriver.'_driver';
			$DB = new $driver($params);
		}
	}

	if ($DB->autoinit === TRUE)
	{
		$DB->initialize();
	}

	return $DB;
}

/* End of file DB_init.php */
/* Location: ./system/database/DB_init.php */