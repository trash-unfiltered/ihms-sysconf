<?php
/**
 * iHMS - internet Hosting Management System
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
 * @subpackage  Client_ConfModule
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/***********************************************************************************************************************
 * SYNOPSIS
 * #!usr/bin/php
 * <?php
 * namespace iHMS\Sysconf\Client;
 * require_once 'iHMS/Sysconf/Client/ConfModule.php';
 * version('1.0');
 * $capb = capb('backup');
 * input('medium', 'foo/bar');
 * $ret=go();
 * if ($ret[0] == 30) {
 *     // Back button pressed (ESC or CANCEL button).
 *     ...
 * }
 * ...
 *
 * DESCRIPTION
 *
 * This is a module to ease writing confmodule for the Sysconf configuration management system. It can communicate with
 * a FrontEnd via the sysconf protocol.
 *
 * The design is that each command in the protocol is represented by one function in this module (with the name normalized).
 * Call the function and pass in any parameters you want to follow the command. It will return an array consisting of
 * the numeric return code and the textual return code (if any).
 */

namespace iHMS\Sysconf\Client;

// Ensure that a FrontEnd is running.  It's a little hackish. If SYSCONF_HAS_FRONTEND is set, a FrontEnd is assumed to
// be running. If not, one is started up automatically and stdin and out are connected to it. Note that this statement
// is always run when the module is loaded in the usual way.
if (getenv('SYSCONF_HAS_FRONTEND') === false) {
    pcntl_exec(realpath('../../../frontend'), $_SERVER['argv']);
}

/**
 * ConfModule class
 *
 * @method static array version($version)
 * @method static array capb($capabilities)
 * @method static string stop()
 * @method static array qreset($questionName)
 * @method static array title($title)
 * @method static array input($priority, $questionName)
 * @method static array beginBlock()
 * @method static array endBlock()
 * @method static array go()
 * @method static array set($questionName, $value)
 * @method static array get($questionName)
 * @method static array register($template, $questionName)
 * @method static array unregister($questionName)
 * @method static array clear()
 * @method static array fset($questionName, $flagName, $value)
 * @method static array fget($questionName, $flagName)
 * @method static array subst($questionName, $key, $value)
 * @method static array metaget($questionName, $fieldName)
 * @method static array setTitle($questionName)
 * @method static array info($questionName)
 * @method static array progress($subcommand)
 * @method static array data($template, $item, $value)
 * @method static array purge()
 * @method static array xLoadTemplateFile($templatePath, $owner)
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Client_ConfModule
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */
final class ConfModule
{
    /**
     * @var array List of valid commands
     */
    protected static $_validCommands = array(
        'VERSION', 'CAPB', 'STOP', 'QRESET', 'TITLE', 'INPUT', 'BEGINBLOCK', 'ENDBLOCK', 'GO', 'SET', 'GET', 'REGISTER',
        'UNREGISTER', 'CLEAR', 'FSET', 'FGET', 'SUBST', 'PURGE', 'METAGET', 'SETTITLE', 'INFO', 'PROGRESS', 'DATA',
        'XLOADTEMPLATEFILE'
    );

    /**
     * Run the given command
     *
     * @static
     * @throws \InvalidArgumentException in case Command is not supported
     * @param string $command Command name to run
     * @param array $args Command arguments
     * @return array
     */
    public static function __callStatic($command, $args)
    {
        // Normalise command name
        $command = strtoupper($command);

        if (!in_array($command, self::$_validCommands)) {
            throw new \InvalidArgumentException("Unsupported command `{$command}.\n");
        }

        $command = $command . ' ' . join(' ', $args);

        // Newlines in input can really badly confuse the protocol, so detect and warn
        if (preg_match('/\n/', $command)) {
            fwrite(STDERR, "Warning: Newline present in parameters passed to sysconf\n");
            fwrite(STDERR, "This will probably cause strange things to happend!\n");
        }

        // Print out the command
        print "{$command}\n";

        $ret = fgets(STDIN);
        $ret = chop($ret, "\n");

        $ret = preg_split('/\s/', $ret, 2);

        if ($ret[0] == '1') {
            // Escaped data
            $unescaped = '';
            foreach (preg_split('/(\\.)/', $ret[1], null, PREG_SPLIT_DELIM_CAPTURE) as $val) {
                $unescaped .= preg_replace('/\\\\(.)/e', '"$1" == "n" ? "\n" : "$1"', $val);
            }

            $ret[0] = '0';
            $ret[1] = $unescaped;
        }

        return $ret;
    }
}
