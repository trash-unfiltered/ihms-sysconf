i-HMS Sysconf component
======================

Introduction
------------
Sysconf is a configuration system for i-HMS modules. Sysconf provides a consistent approach for configuring modules and
gives the user a choice of interfaces. It can configure modules before they are installed, allowing installations and
major upgrades to request all necessary information in advance, and let you do what you want while modules are installed.
Sysconf lets you, if hurry, to skip the less important questions when installing modules.

Sysconf is a portage of the Debian debconf configuration system for IHMS.

Installation
------------

Sysconf still in prototype state. Install procedure will coming soon..

Todo
----
 - Unit tests for DB layer
 - Unit tests for ConfModule (client layer)
 - Unit test for getopt
 - Unit test for frontend layer
 - ini_set('track_errors', 1)