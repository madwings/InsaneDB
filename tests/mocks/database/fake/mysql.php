<?php

require_once __DIR__.'/bootstrap.php';

/**
 * PDO MySQL driver connected to a Mock_Database_Fake_Server
 */
class Mock_Database_Fake_Mysql extends CI_DB_pdo_mysql_driver {

	use Mock_Database_Fake_Driver;

}
