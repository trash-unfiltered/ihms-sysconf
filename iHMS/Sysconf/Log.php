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
 * @subpackage  Log
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

namespace iHMS\Sysconf;

/**
 * iHMS_Sysconf_Log class
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Log
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
class Log
{
    /**
     * @var \Zend_Log_Writer_Syslog
     */
    protected static $sysLogWriter = null;

    /**
     * Outputs an infomational message
     *
     * The first parameter specifies the type of information that is being logged. If the user has specified a debug or
     * log setting that matches the parameter, the message is output and/or logged.
     *
     * Currently used types of information are: user, developer, debug and db
     *
     * @static
     * @param string $type type of information that is being logged
     * @param string $message Message
     * @return void
     */
    public static function debug($type, $message)
    {
        $debug = Config::getInstance()->debug;

        if ($debug && preg_match("/$debug/", $type)) {
            fwrite(STDERR, "sysconf: ({$type}): {$message}\n");
        }

        $log = Config::getInstance()->log;

        if ($log && preg_match("/{$log}/", $type)) {
            if (!self::$sysLogWriter) {
                self::$sysLogWriter = new \Zend_Log_Writer_Syslog(array('application' => 'iHMS'));
            }

            self::$sysLogWriter->write(array('priority' => LOG_DEBUG, 'message' => "({$type}) {$message}"));
        }
    }

    /**
     * Outputs a warning message
     *
     * @param string $message Message
     * @return void
     */
    public static function warn($message)
    {
        if (Config::getInstance()->noWarnings != 'yes') {
            fwrite(STDERR, "sysconf: {$message}\n");
        }
    }
}
