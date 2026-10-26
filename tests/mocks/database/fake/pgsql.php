<?php

require_once __DIR__.'/bootstrap.php';

/**
 * PDO PostgreSQL driver connected to a Mock_Database_Fake_Server
 */
class Mock_Database_Fake_Pgsql extends CI_DB_pdo_pgsql_driver {

	use Mock_Database_Fake_Driver;

}
