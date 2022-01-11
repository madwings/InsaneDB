.. image:: https://travis-ci.org/madwings/InsaneDB.svg?branch=develop
    :target: https://travis-ci.org/madwings/InsaneDB

###################
What is InsaneDB
###################

InsaneDB is an database toolkit for PHP forked from CodeIgniter 3 database layer. 
Its goal is to remove limitations existing in the upstream version and add additional
functionalities while keeping sync with CodeIgniter. It can be used standalone or
as a replacement for the CodeIgniter database layer.

*******************
Release Information
*******************

| First official version released.
| Documentation is about to be updated.

**************************
Changelog and New Features
**************************

You can find a list of all changes in the upstream version in the `user
guide change log <https://github.com/bcit-ci/CodeIgniter/blob/develop/user_guide_src/source/changelog.rst>`_.

InsaneDB
=============

Initial Release Date: 14.10.2018

New features
-------------------------

   -  Added Read/Write connections mode.
   -  Read delay option in Read/Write connections mode.
   -  Automatic reconnect on dropped connection for the MySQL and PostgreSQL drivers.

<<<<<<< HEAD
=======
Please see the `installation section <https://codeigniter.com/userguide3/installation/index.html>`_
of the CodeIgniter User Guide.
>>>>>>> d314be05ec50dace2cd8dc94b81e285208bc3a76

Improvements
-------------------------

   -  PDO adapter drivers replacing all vendor specific drivers(where applicable).
   -  Added option for manual setting the size of the batch query functions.
   -  General simplifications of the configuration file.
   -  Removed ['compress'] and ['encrypt'] options, they can be passed by the new ['options'] variable.
   -  Improved ``reconnect()``, now working for all the drivers.
   -  Improved ``num_rows()``, should be faster for big result sets.
   -  Added option which keys to be included in ``update_batch()``, ``insert_batch()`` and ``insert_ignore_batch()``.
   
Bug fixes
-------------------------

   -  Fixed a bug (#) - method ``close()`` didn't close a connection with valid result object.

<<<<<<< HEAD
*******************
Server Requirements
*******************
=======
-  `User Guide <https://codeigniter.com/docs>`_
-  `Contributing Guide <https://github.com/bcit-ci/CodeIgniter/blob/develop/contributing.md>`_
-  `Language File Translations <https://github.com/bcit-ci/codeigniter3-translations>`_
-  `Community Forums <https://forum.codeigniter.com/>`_
-  `Community Wiki <https://github.com/bcit-ci/CodeIgniter/wiki>`_
-  `Community Slack Channel <https://codeigniterchat.slack.com>`_
>>>>>>> d314be05ec50dace2cd8dc94b81e285208bc3a76

| PHP version 7.1 or newer is required.
| It is recommend to migrate to newer version of PHP sooner than later.

*******
License
*******

Please see the `license
agreement <https://github.com/madwings/InsaneDB/blob/master/license.txt>`_.
