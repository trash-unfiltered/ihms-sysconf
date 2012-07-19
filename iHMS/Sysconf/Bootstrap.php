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
 * @subpackage  Bootstrap
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Initialize localization
setlocale(LC_MESSAGES, ''); // sets the locale according to the user's environment variables
textdomain('sysconf');
bindtextdomain('sysconf', '/usr/local/share/locale');

// Check for PHP version
if (version_compare(phpversion(), '5.3.3') == -1) {
    fwrite(STDERR, sprintf(_('Your PHP version is %s. Sysconf require PHP %s or newer.'), phpversion(), '5.3.3') . "\n");
    exit(1);
}

// Check for PHP SAPI
if (PHP_SAPI != 'cli') {
    fwrite(STDERR, _('Sysconf is a CLI program.') . "\n");
    exit(1);
}

// Check for Iconv PHP extension availability
if (!extension_loaded('iconv')) {
    fwrite(STDERR, _('Sysconf require Iconv PHP extension.') . "\n");
    exit(1);
}

// Check for Zend Framework library availability and version
if (stream_resolve_include_path('Zend/Version.php') === false) {
    fwrite(STDERR, _('Sysconf require Zend Framework 1.11.x or newer.') . "\n");
    exit(1);
}

/** @see Zend_Version */
require_once 'Zend/Version.php';
if (version_compare(Zend_Version::VERSION, '1.11') == -1) {
    fwrite(STDERR,
        sprintf(
            _('Your Zend Framework version is %s. Sysconf require Zend Framework 1.11.x or newer.'),
            Zend_Version::VERSION
        ) . "\n"
    );
    exit(1);
}

// Add sysconf library directory to the PHP include_path
set_include_path(dirname(dirname(__DIR__)) . PATH_SEPARATOR . get_include_path());

// Register classes loader
spl_autoload_register(
    function($className)
    {
        $classPath = str_replace('_', '/', $className);
        require_once "$classPath.php";
    }
);

// Sets handler for uncaught exceptions
set_exception_handler(
    function($exception)
    {
        /** @var $exception Exception */
        fwrite(STDERR, $exception->getMessage());
    }
);
