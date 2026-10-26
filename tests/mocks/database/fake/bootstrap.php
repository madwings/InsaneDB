<?php

// Loads what DB() would normally load, so that the fake drivers can be
// instantiated directly, without a configuration file or a database server.

require_once SYSTEM_PATH.'database/DB_helpers.php';

if ( ! class_exists('CI_DB', FALSE))
{
	class CI_DB extends CI_DB_query_builder {}
}
