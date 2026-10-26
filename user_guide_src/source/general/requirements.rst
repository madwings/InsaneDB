###################
Server Requirements
###################

InsaneDB 1.5.0 requires `PHP <https://www.php.net/>`_ version 8.3 or newer.

A database is required for most web application programming.
Currently supported databases are:

  - MySQL (5.1+) via the *mysql* (deprecated), *mysqli* and *pdo* drivers
  - Oracle via the *oci8* and *pdo* drivers
  - PostgreSQL via the *postgre* and *pdo* drivers
  - MS SQL via the *mssql*, *sqlsrv* (version 2005 and above only) and *pdo* drivers
  - SQLite via the *sqlite3* and *pdo* drivers
  - CUBRID via the *cubrid* and *pdo* drivers
  - Interbase/Firebird via the *ibase* and *pdo* drivers
  - ODBC via the *odbc* and *pdo* drivers (you should know that ODBC is actually an abstraction layer)
