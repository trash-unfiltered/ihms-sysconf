## Sysconf - Interactive configuration system for iHMS software packages

### INTRODUCTION

Sysconf is an interactive configuration system written in PHP, allowing to configure iHMS software packages. It provides
a consistent approach for configuring packages and gives the user a choice of interfaces. It can configure modules before
they are installed, allowing installations and major upgrades to request all necessary information in advance, and let
you do what you want while packages are installed. Sysconf lets you, if hurry, to skip the less important questions when
installing modules.

### REQUIREMENT

**Sysconf requires the following:**

 * GNU make
 * PHP **5.3.3** or later; we recommend using the latest PHP version whenever possible
 * PHP Iconv extension
 * Zend Framework **1.10.x** (ZF library directory **must** be correctly set in your PHP include path)
 * whiptail and/or dialog program (OPTIONAL but still recommended for better visual experience)

### USAGE

See the [confmodule library](https://github.com/i-HMS/ihms-sysconf/blob/master/confmodule "confmodule") file. A better documentation will
come soon.

### Installation

Before installing sysconf, you must ensure that you respect all requirements as mentionned in the **REQUIREMENT** section.
For ***Debian*** and ***Ubuntu*** this can be done like this:

**Debian**

	# aptitude update && aptitude -R install make php5-cli php5-cgi zendframework

**Note:** Here, we install php-cgi package to avoid to install Apache2 && cie

**Ubuntu**

	# aptitude update && aptitude -R install make php5-cli libzend-framework-php

**Note:** For ubuntu, you still need to add Zend Framework library in your PHP include_path. You can do this by editing
the /etc/php/conf.d/zend-framework.ini file (simply uncomment the second line).

#### Installing sysconf on your system

 To install sysconf, just type as root user:

 	# make install

**Note:** By default, sysconf will be installed under /usr/local. You can override installation directories by using the
following variables:

	prefix, bindir, sbindir, datarootdir, datadir, sysconfdir, localstatedir, docdir, localedir and mandir.

 For instance :

	# make prefix=/usr sysconfdir=/etc localstatedir=/var install

See this [document] (http://www.gnu.org/prep/standards/html_node/Directory-Variables.html#Directory-Variables "Directory-Variables") for more information about these variables

### demo script

To run the demo script without installing sysconf, just type:

	$ make prefix=/tmp demo

This command will build and run the demo script. The **/tmp** directory will be used to store the sysconf database. Do
not forget to run **make clean** at end to reset the source tree.

### LICENSE

The files in this archive are released under the [GPL v2](http://www.gnu.org/licenses/gpl-2.0.html "GPL v2")
You can find a copy of this license in LICENSE.txt.

### ACKNOWLEDGEMENTS

The i-HMS team would like to thank all the contributors to the i-HMS project, our corporate sponsor, and you, the i-HMS
software users.

The i-HMS team would like particularly thank **Joey Hess**, a Debian developer that is the author of the Debconf program on
which our work is based.

### AUTHORS AND CONTRIBUTORS
Laurent Declercq <l.declercq@nuxwin.com> (Author)
