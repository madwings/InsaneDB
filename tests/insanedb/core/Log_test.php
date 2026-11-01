<?php

class Log_test extends CI_TestCase {

	protected function new_log(array $config = array(), $class = 'CI_Log')
	{
		$this->ci_set_config(array_merge(array(
			'log_path' => '',
			'log_threshold' => 4,
			'log_filename' => '',
			'log_file_suffix' => '',
			'log_file_extension' => ''
		), $config));

		return new $class();
	}

	// --------------------------------------------------------------------

	protected function get_log_filename(CI_Log $log)
	{
		$method = new ReflectionMethod($log, '_get_log_filename');
		if (PHP_VERSION_ID < 80100)
		{
			$method->setAccessible(TRUE);
		}

		return $method->invoke($log);
	}

	// --------------------------------------------------------------------

	protected function get_property(CI_Log $log, $name)
	{
		$property = new ReflectionProperty('CI_Log', $name);
		if (PHP_VERSION_ID < 80100)
		{
			$property->setAccessible(TRUE);
		}

		return $property->getValue($log);
	}

	// --------------------------------------------------------------------

	public function test_default_filename()
	{
		$log = $this->new_log();
		$this->assertSame('log-'.date('Y-m-d').'.php', $this->get_log_filename($log));
		$this->assertSame('', $this->get_property($log, '_log_file_suffix'));
	}

	// --------------------------------------------------------------------

	public function test_custom_filename()
	{
		$log = $this->new_log(array('log_filename' => 'app.log'));
		$this->assertSame('app.log', $this->get_log_filename($log));
	}

	// --------------------------------------------------------------------

	public function test_deprecated_extension()
	{
		$log = $this->new_log(array('log_file_extension' => '.txt'));
		$this->assertSame('log-'.date('Y-m-d').'.txt', $this->get_log_filename($log));
	}

	// --------------------------------------------------------------------

	public function test_suffix_with_default_filename()
	{
		$log = $this->new_log(array('log_file_suffix' => '-web01'));
		$this->assertSame('-web01', $this->get_property($log, '_log_file_suffix'));
		$this->assertSame('log-'.date('Y-m-d').'-web01.php', $this->get_log_filename($log));
	}

	// --------------------------------------------------------------------

	public function test_suffix_with_deprecated_extension()
	{
		$log = $this->new_log(array('log_file_suffix' => '-web01', 'log_file_extension' => '.txt'));
		$this->assertSame('log-'.date('Y-m-d').'-web01.txt', $this->get_log_filename($log));
	}

	// --------------------------------------------------------------------

	public function test_suffix_with_custom_filename()
	{
		$log = $this->new_log(array('log_filename' => 'app.log', 'log_file_suffix' => '_prod'));
		$this->assertSame('app_prod.log', $this->get_log_filename($log));

		$log = $this->new_log(array('log_filename' => 'app.2026.log', 'log_file_suffix' => '_prod'));
		$this->assertSame('app.2026_prod.log', $this->get_log_filename($log));

		$log = $this->new_log(array('log_filename' => 'app', 'log_file_suffix' => '_prod'));
		$this->assertSame('app_prod', $this->get_log_filename($log));

		$log = $this->new_log(array('log_filename' => '.log', 'log_file_suffix' => '_prod'));
		$this->assertSame('.log_prod', $this->get_log_filename($log));

		// The deprecated extension is ignored when a custom filename is set
		$log = $this->new_log(array('log_filename' => 'app.log', 'log_file_suffix' => '_prod', 'log_file_extension' => 'txt'));
		$this->assertSame('app_prod.log', $this->get_log_filename($log));
	}

	// --------------------------------------------------------------------

	public function test_suffix_edge_cases()
	{
		$log = $this->new_log(array('log_file_suffix' => ''));
		$this->assertSame('log-'.date('Y-m-d').'.php', $this->get_log_filename($log));

		$log = $this->new_log(array('log_file_suffix' => '0'));
		$this->assertSame('log-'.date('Y-m-d').'0.php', $this->get_log_filename($log));

		$log = $this->new_log(array('log_file_suffix' => 1));
		$this->assertSame('1', $this->get_property($log, '_log_file_suffix'));
		$this->assertSame('log-'.date('Y-m-d').'1.php', $this->get_log_filename($log));
	}

	// --------------------------------------------------------------------

	public function test_suffix_cannot_escape_log_directory()
	{
		$log = $this->new_log(array('log_file_suffix' => '/../-x\\'));
		$this->assertSame('..-x', $this->get_property($log, '_log_file_suffix'));
		$this->assertSame('log-'.date('Y-m-d').'..-x.php', $this->get_log_filename($log));

		$log = $this->new_log(array('log_filename' => 'app.log', 'log_file_suffix' => '\\..\\..'));
		$this->assertSame('app.....log', $this->get_log_filename($log));
	}

	// --------------------------------------------------------------------

	public function test_suffix_is_fixed_at_construction()
	{
		$log = $this->new_log(array('log_file_suffix' => '-web01'));

		$this->ci_set_config('log_file_suffix', '-changed');
		$this->assertSame('log-'.date('Y-m-d').'-web01.php', $this->get_log_filename($log));
	}

	// --------------------------------------------------------------------

	public function test_write_log_uses_suffix()
	{
		$log = $this->new_log(array('log_file_suffix' => '-web01'));
		$this->assertTrue($log->write_log('error', 'suffixed entry'));

		$path = APPPATH.'logs'.DIRECTORY_SEPARATOR;
		$contents = file_get_contents($path.'log-'.date('Y-m-d').'-web01.php');
		$this->assertNotFalse(strpos($contents, 'suffixed entry'));
		$this->assertSame(1, substr_count($contents, '<?php'));
		$this->assertFalse(file_exists($path.'log-'.date('Y-m-d').'.php'));

		$log = $this->new_log(array('log_filename' => 'app.log', 'log_file_suffix' => '_prod'));
		$this->assertTrue($log->write_log('error', 'custom entry'));

		$contents = file_get_contents($path.'app_prod.log');
		$this->assertNotFalse(strpos($contents, 'custom entry'));
		$this->assertSame(0, substr_count($contents, '<?php'));
		$this->assertFalse(file_exists($path.'app.log'));
	}

	// --------------------------------------------------------------------

	public function test_suffix_with_subclass_filename_override()
	{
		$log = $this->new_log(array('log_file_suffix' => '-web01'), 'Log_suffix_filename_stub');
		$this->assertSame('subclass-web01.php', $this->get_log_filename($log));
	}
}

class Log_suffix_filename_stub extends CI_Log {

	public function __construct()
	{
		parent::__construct();
		$this->_log_filename = 'subclass.php';
	}
}
