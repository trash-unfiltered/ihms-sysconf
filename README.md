i-HMS Sysconf
=============

INTRODUCTION
------------
Sysconf is a configuration system primarily developed for configuration of i-HMS modules. Sysconf provides a consistent
approach for configuring modules and gives the user a choice of interfaces. It can configure modules before they are
installed, allowing installations and major upgrades to request all necessary information in advance, and let you do
what you want while modules are installed. Sysconf lets you, if hurry, to skip the less important questions when
installing modules.

Sysconf can also be used as standalone program and/or be integrated in any project that requires installation process.
Sysconf is only compatible with GNU/Linux OS.

REQUIREMENT
-----------

Sysconf requires the following:

 - PHP 5.3.3 or later; we recommend using the latest PHP version whenever possible
 - PHP Iconv extension
 - Zend Framework >= 1.11.x (Acces to the ZF library directory *must* be correctly set in your PHP include path)
 - whiptail and/or dialog program (OPTIONAL but still recommended for better visual experience)

USAGE
-----

Sysconf still in development state. Sample usage will come soon.

LICENSE
-------

The files in this archive are released under the http://www.gnu.org/licenses/gpl-2.0.html GPL v2.
You can find a copy of this license in LICENSE.txt.

ACKNOWLEDGEMENTS
----------------

The i-HMS team would like to thank all the contributors to the i-HMS project, our corporate sponsor,
and you, the i-HMS user.

The i-HMS team would like  particulary thank to Joey Hess, a Debian developer that is the author of the Debconf program
on which our work is based.

AUTHORS
-------
Laurent Declercq (nuxwin)
