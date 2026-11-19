<?php

/**
 * Column meta-data of PDO results, for subdrivers without getColumnMeta() support
 *
 * Depending on PDO::ATTR_ERRMODE, getColumnMeta() then returns FALSE or throws.
 */
class Pdo_result_test extends CI_TestCase {

	/**
	 * Create a result for a statement with two columns
	 *
	 * @param	bool	$db_debug
	 * @param	bool	$throw	Whether getColumnMeta() throws instead of returning FALSE
	 * @return	CI_DB_pdo_result
	 */
	protected function make_result($db_debug, $throw)
	{
		$db = Mock_Database_Fake_Mysql::create(array('db_debug' => $db_debug));
		$db->result_id = new class($throw) {

			public function __construct(private $throw)
			{
			}

			public function columnCount()
			{
				return 2;
			}

			public function getColumnMeta($i)
			{
				if ($this->throw)
				{
					throw new PDOException('SQLSTATE[IM001]: Driver does not support this function: driver doesn\'t support meta data');
				}

				return FALSE;
			}

		};

		return new CI_DB_pdo_result($db);
	}

	// ------------------------------------------------------------------------

	public static function errmode_provider()
	{
		return array(
			'silent' => array(FALSE),
			'exception' => array(TRUE)
		);
	}

	// ------------------------------------------------------------------------

	#[\PHPUnit\Framework\Attributes\DataProvider('errmode_provider')]
	public function test_unsupported_meta_returns_false($throw)
	{
		$result = $this->make_result(FALSE, $throw);

		$this->assertFalse($result->list_fields());
		$this->assertFalse($result->field_data());
	}

	// ------------------------------------------------------------------------

	#[\PHPUnit\Framework\Attributes\DataProvider('errmode_provider')]
	public function test_unsupported_meta_displays_error_with_db_debug($throw)
	{
		$result = $this->make_result(TRUE, $throw);

		foreach (array('list_fields', 'field_data') as $method)
		{
			try
			{
				$result->$method();
				$this->fail($method.'() should display an error');
			}
			catch (RuntimeException $e)
			{
				$this->assertSame('DB Error: db_unsupported_feature', $e->getMessage(), $method);
			}
		}
	}

}
