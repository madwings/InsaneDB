.. image:: https://github.com/madwings/InsaneDB/actions/workflows/test-phpunit.yml/badge.svg
    :target: https://github.com/madwings/InsaneDB/actions/workflows/test-phpunit.yml

###################
What is InsaneDB
###################

InsaneDB is a database toolkit for PHP forked from CodeIgniter 3 database layer.
Its goal is to remove limitations existing in the upstream version and add additional
functionalities while keeping in sync with CodeIgniter. It can be used as a standalone package
or as a replacement to the CodeIgniter database layer.

**************
Installation
**************

Replacement for the CodeIgniter database layer
=======================

- Replace all files and directories in your ``system/database`` directory.
- Replace ``application/config/database.php`` file.

**************************
Changelog and New Features
**************************

You can find a list of all changes in the upstream version in the `user
guide change log <https://github.com/bcit-ci/CodeIgniter/blob/develop/user_guide_src/source/changelog.rst>`_.

InsaneDB Major Changes
=======================

New features
-------------------------

   -  Added Read/Write connections mode.
   -  Read delay option in Read/Write connections mode.
   -  Automatic reconnect on dropped connection for the MySQL and PostgreSQL drivers.


Improvements
-------------------------

   -  PDO adapter drivers replacing all vendor specific drivers(where applicable).
   -  Added option for manual setting the size of the batch query functions.
   -  General simplifications of the configuration file.
   -  Removed ['compress'] and ['encrypt'] options, they can be passed by the new ['options'] variable.
   -  Improved ``reconnect()``, now working for all the drivers.
   -  Improved ``num_rows()``, should be faster for big result sets.
   -  Added ``insert_ignore_batch()`` method.
   -  Changed parameter order of ``insert_batch()`` and ``insert_ignore_batch()``.
   -  Added option which keys to be included in ``update_batch()``, ``insert_batch()`` and ``insert_ignore_batch()``.

Behaviour bugfix
-------------------------

   -  Fixed a bug (#) - method ``close()`` didn't close a connection with valid result object.

*******************
Server Requirements
*******************

| PHP version 7.1 or newer is required.
| It is recommend to migrate to newer version of PHP sooner than later.

*******
License
*******

Please see the `license
agreement <https://github.com/madwings/InsaneDB/blob/master/license.txt>`_.
