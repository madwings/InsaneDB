<?php

require_once SYSTEM_PATH.'core/Log.php';

class Mock_Core_Log extends CI_Log {

	public function __construct(array $config)
	{
		$this->_config = $config;
	}

	public function get_log_filename()
	{
		return $this->_get_log_filename();
	}
}

class Log_test extends CI_TestCase {

	public function test_default_filename()
	{
		$log = new Mock_Core_Log(array());
		$this->assertEquals('log-'.date('Y-m-d').'.php', $log->get_log_filename());
	}

	// --------------------------------------------------------------------

	public function test_custom_filename()
	{
		$log = new Mock_Core_Log(array('log_filename' => 'app.log'));
		$this->assertEquals('app.log', $log->get_log_filename());
	}

	// --------------------------------------------------------------------

	public function test_deprecated_extension()
	{
		$log = new Mock_Core_Log(array('log_filename' => '', 'log_file_extension' => '.txt'));
		$this->assertEquals('log-'.date('Y-m-d').'.txt', $log->get_log_filename());
	}

	// --------------------------------------------------------------------

	public function test_suffix_with_default_filename()
	{
		$log = new Mock_Core_Log(array('log_filename' => '', 'log_file_suffix' => '-web01'));
		$this->assertEquals('log-'.date('Y-m-d').'-web01.php', $log->get_log_filename());
	}

	// --------------------------------------------------------------------

	public function test_suffix_with_custom_filename()
	{
		$log = new Mock_Core_Log(array('log_filename' => 'app.log', 'log_file_suffix' => '_prod'));
		$this->assertEquals('app_prod.log', $log->get_log_filename());

		$log = new Mock_Core_Log(array('log_filename' => 'app', 'log_file_suffix' => '_prod'));
		$this->assertEquals('app_prod', $log->get_log_filename());

		$log = new Mock_Core_Log(array('log_filename' => '.log', 'log_file_suffix' => '_prod'));
		$this->assertEquals('.log_prod', $log->get_log_filename());
	}

	// --------------------------------------------------------------------

	public function test_suffix_edge_cases()
	{
		$log = new Mock_Core_Log(array('log_file_suffix' => ''));
		$this->assertEquals('log-'.date('Y-m-d').'.php', $log->get_log_filename());

		$log = new Mock_Core_Log(array('log_file_suffix' => '0'));
		$this->assertEquals('log-'.date('Y-m-d').'0.php', $log->get_log_filename());

		$log = new Mock_Core_Log(array('log_file_suffix' => '/../-x\\'));
		$this->assertEquals('log-'.date('Y-m-d').'..-x.php', $log->get_log_filename());
	}
}
