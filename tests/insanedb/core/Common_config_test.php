<?php

class Common_config_test extends \PHPUnit\Framework\TestCase {

	private $fixtureRoot;

	protected function setUp(): void
	{
		$this->fixtureRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'insanedb-config-'.bin2hex(random_bytes(8));
		mkdir($this->fixtureRoot, 0777, TRUE);
	}

	protected function tearDown(): void
	{
		$this->removeDirectory($this->fixtureRoot);
	}

	public function test_custom_folder_takes_precedence()
	{
		$this->writeConfig('custom/config.php', '$config = array("source" => "custom");');
		$this->writeConfig('application/config/config.php', '$config = array("source" => "application");');
		$this->writeConfig('custom/testing/config.php', '$config["environment"] = "custom";');
		$this->writeConfig('application/config/testing/config.php', '$config["environment"] = "application";');

		$result = $this->runLoader(TRUE);

		$this->assertSame($this->path('custom').DIRECTORY_SEPARATOR, $result['config_file_directory']);
		$this->assertSame(array('source' => 'custom', 'environment' => 'custom'), $result['config']);
	}

	public function test_environment_config_falls_back_to_application_folder()
	{
		$this->writeConfig('custom/config.php', '$config = array("source" => "custom");');
		$this->writeConfig('application/config/testing/config.php', '$config["environment"] = "application";');

		$result = $this->runLoader(TRUE);

		$this->assertSame(array('source' => 'custom', 'environment' => 'application'), $result['config']);
	}

	public function test_missing_file_returns_false()
	{
		$this->writeConfig('application/config/config.php', '$config = array("source" => "application");');

		$result = $this->runLoader(TRUE);

		$this->assertFalse($result['missing_file']);
	}

	public function test_lookup_works_without_configpath()
	{
		$this->writeConfig('application/config/config.php', '$config = array("source" => "application");');

		$result = $this->runLoader(FALSE);

		$this->assertSame(array($this->path('application/config').DIRECTORY_SEPARATOR), $result['paths']);
		$this->assertSame(array('source' => 'application'), $result['config']);
	}

	private function runLoader($useConfigPath)
	{
		$script = <<<'PHP'
define('APPPATH', getenv('TEST_APP_PATH').DIRECTORY_SEPARATOR);
define('BASEPATH', dirname(getenv('TEST_COMMON_PATH')).DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'testing');
if (($configPath = getenv('TEST_CONFIG_PATH')) !== FALSE && $configPath !== '')
{
	define('CONFIGPATH', $configPath.DIRECTORY_SEPARATOR);
}
require getenv('TEST_COMMON_PATH');
$config =& get_config();
echo json_encode(array(
	'paths' => get_config_paths(),
	'config_file_directory' => dirname(get_config_file('config.php')).DIRECTORY_SEPARATOR,
	'missing_file' => get_config_file('missing.php'),
	'config' => $config
));
PHP;
		$environment = array(
			'TEST_APP_PATH' => $this->path('application'),
			'TEST_COMMON_PATH' => dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'system'.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.'Common.php',
			'TEST_CONFIG_PATH' => $useConfigPath ? $this->path('custom') : ''
		);
		$process = proc_open(
			array(PHP_BINARY, '-r', $script),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes,
			NULL,
			$environment
		);
		$this->assertIsResource($process);
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		$this->assertSame(0, proc_close($process), $error);

		$result = json_decode($output, TRUE);
		$this->assertIsArray($result, $output."\n".$error);

		return $result;
	}

	private function writeConfig($relativePath, $contents)
	{
		$filePath = $this->path($relativePath);
		$directory = dirname($filePath);
		if ( ! is_dir($directory))
		{
			mkdir($directory, 0777, TRUE);
		}

		file_put_contents($filePath, "<?php\n".$contents."\n");
	}

	private function path($relativePath)
	{
		return $this->fixtureRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
	}

	private function removeDirectory($directory)
	{
		if ( ! is_dir($directory))
		{
			return;
		}

		foreach (scandir($directory) as $entry)
		{
			if ($entry === '.' OR $entry === '..')
			{
				continue;
			}

			$path = $directory.DIRECTORY_SEPARATOR.$entry;
			is_dir($path) ? $this->removeDirectory($path) : unlink($path);
		}

		rmdir($directory);
	}
}
