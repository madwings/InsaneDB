<?php
// Errors on full!
ini_set('display_errors', 1);

if (version_compare(PHP_VERSION, '8.4', '>=')) {
	error_reporting(E_ALL);
} else {
	error_reporting(E_ALL | E_STRICT);
}

$dir = realpath(dirname(__FILE__));

// Path constants
defined('PROJECT_BASE') OR define('PROJECT_BASE', realpath($dir.'/../').'/');
defined('SYSTEM_PATH') OR define('SYSTEM_PATH', PROJECT_BASE.'system/');

require_once PROJECT_BASE.'vendor/autoload.php';
class_alias('org\bovigo\vfs\vfsStream', 'vfsStream');
class_alias('org\bovigo\vfs\vfsStreamDirectory', 'vfsStreamDirectory');
class_alias('org\bovigo\vfs\vfsStreamWrapper', 'vfsStreamWrapper');

// Define CI path constants to VFS (filesystem setup in CI_TestCase::setUp)
defined('BASEPATH') OR define('BASEPATH', vfsStream::url('system/'));
defined('APPPATH') OR define('APPPATH', vfsStream::url('application/'));
defined('VIEWPATH') OR define('VIEWPATH', APPPATH.'views/');
defined('ENVIRONMENT') OR define('ENVIRONMENT', 'development');

// Set localhost "remote" IP
isset($_SERVER['REMOTE_ADDR']) OR $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Prep our test environment
include_once $dir.'/mocks/core/common.php';
include_once SYSTEM_PATH.'core/Common.php';

ini_set('default_charset', 'UTF-8');

if (extension_loaded('mbstring'))
{
	defined('MB_ENABLED') OR define('MB_ENABLED', TRUE);
	mb_substitute_character('none');
}
else
{
	defined('MB_ENABLED') OR define('MB_ENABLED', FALSE);
}

if (extension_loaded('iconv'))
{
	defined('ICONV_ENABLED') OR define('ICONV_ENABLED', TRUE);
}
else
{
	defined('ICONV_ENABLED') OR define('ICONV_ENABLED', FALSE);
}

include_once SYSTEM_PATH.'core/compat/mbstring.php';

include_once $dir.'/mocks/autoloader.php';
spl_autoload_register('autoload');

unset($dir);
