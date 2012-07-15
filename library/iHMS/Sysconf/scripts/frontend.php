#!/usr/bin/env php
<?php
/**
 * Sysconf - Interactive configuration system for PHP applications
 * Copyright (C) 2012 by iHMS Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  scripts
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/***********************************************************************************************************************
 * Short description
 *
 * This is a helper program for confmodules. It expects to be passed the name of the confmodule script to run, and any
 * parameters for it.
 */

// Include bootstrap
require_once dirname(__DIR__) . '/Bootstrap.php';

/** @see iHMS_Sysconf_Db */
require_once 'iHMS/Sysconf/Db.php';

/** @see iHMS_Sysconf_Template */
require_once 'iHMS/Sysconf/Template.php';

/** @see iHMS_Sysconf_AutoSelect */
require_once 'iHMS/Sysconf/AutoSelect.php';

/** @see iHMS_Sysconf_Log */
require_once 'iHMS/Sysconf/Log.php';

putenv('SYSCONF_SYSTEMRC=../library/iHMS/Sysconf/sysconf.conf');

// Load sysconf database
iHMS_Sysconf_Db::load();

iHMS_Sysconf_Log::debug('developer', 'frontend started');

$frontend = iHMS_Sysconf_AutoSelect::frontendFactory();

array_shift($argv);

if ($argv[0] == '--') {
    array_shift($argv[0]);
}

// Set the default title
$module = null;
if (($env = getenv('SYSCONF_MODULE')) !== false) {
    $module = $env;
} elseif (preg_match('!^.*/(.*?)\.(?:postinst|postrm|prerm)!', $argv[0], $m)) {
    $module = $m[1];
} elseif (file_exists('/usr/local/var/lib/ihms/tmp.ci/control')) { // TODO review for better reusability - /tmp ???
    // The preinst is running, presumably. Now, it gets really ugly, because we have to parse the control file
    $fh = @fopen('/usr/local/var/lib/ihms/tmp.ci/control', 'r');

    if (!is_resource($fh)) {
        throw new RuntimeException("{$php_errormsg}\n");
    }

    while ($lines = fgets($fh)) {
        if (preg_match('/^Module: (.*)/', $lines, $m)) {
            $module = $m[1];
            break;
        }
    }

    fclose($fh);
} else {
    // Being run some other way, not via an iHMS script
    $module = '';

    iHMS_Sysconf_Log::debug('developer', 'Trying to find a templates file..');

    $tryTemplate = function($fn) use($module)
    {
        iHMS_Sysconf_Log::debug('developer', "Trying {$fn}");

        if (file_exists($fn)) {
            iHMS_Sysconf_Log::debug('developer', "I guess it is {$fn}");
            iHMS_Sysconf_Template::load($fn, $module);
            return true;
        }

        return false;
    };

    //  See if there is a templates file in the same directory as the script, with the same name except .templates is
    // appended
    if (!$tryTemplate("{$argv[0]}.templates")) {
        // Next try removing "config" from the end of method name and putting in "templates".
        if (!preg_match('/(.*)config$/', $argv[0], $m) || !$tryTemplate("{$m[1]}.templates")) {
            // Finally, look in sysconf lib directory for the base filename with .templates appended
            if (
                !(preg_match('!^(?:.*/)?(.*)!', $argv[0], $m) &&
                    $tryTemplate("/usr/local/share/sysconf/templates/{$m[1]}.templates"))
            ) {
                iHMS_Sysconf_Log::debug('developer', "Couldn't find a templates file.");
            }
        }
    }
}

iHMS_Sysconf_Log::debug('developer', "frontend running, module name is {$module}");

if ($module != '') {
    $frontend->defaultTitle($module);
}

$frontend->setInfo();

// See if the preinst or postinst of the module is being run, and if there is a config script associated with this
// module. If so, run it first as a confmodule (also loading the templates). This is a bit of a nasty hack, that lets
// you ihms install module somemodule and have its config script be run first.
//
// If it is the preinst, everything is in this weird directory deep in /usr/local/var/lib/ihms.
if (preg_match('/^(.*[.\/])(?:postinst|preinst)$/', $argv[0], $m)) {
    $base = $m[1];

    // Load templates, if any
    $templates = $base . '.templates';
    if (file_exists($templates)) {
        iHMS_Sysconf_Template::load($templates, $module);
    }

    // Run config method, if any
    $config = $base . 'config';

    if (file_exists($config)) {
        // We assume that the third argument passed to this program (which should be the second argument passed to the
        // preinst or postinst that ran it), is the module version.
        $version = isset($argv[2]) ? $argv[2] : '';
        $confmodule = iHMS_Sysconf_AutoSelect::confModuleFactory($config, 'configure', $version);

        // Make sure any questions the confmodule generates are owned by this module
        $confmodule->setOwner($module);

        // Talk to it until it s done
        while ($confmodule->communicate()) ;

        if (($exitCode = $confmodule->getExitCode()) > 0) {
            exit($exitCode);
        }
    }
}

// Start up the confmodule we were asked to run
$confmodule = iHMS_Sysconf_AutoSelect::confModuleFactory($argv);

// Make sure any questions the confmodule generates are owned by this module
$confmodule->setOwner($module);

// Talk to it until it s done
while ($confmodule->communicate()) ;

// Shutdown the frontend
$frontend->shutdown();

// Save sysconf database state
iHMS_Sysconf_Db::save();

exit($confmodule->getExitCode());
