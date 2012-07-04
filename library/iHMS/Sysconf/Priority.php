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
 * @subpackage  Priority
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/**
 * iHMS_Sysconf_Priority class
 *
 * This class deals with the priorities of questions. Currently known priorities are low, medium, high, and critical.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Priority
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Priority
{
    /**
     * @var array List of known priority levels
     */
    protected static $_priorities = array(
        'low' => 0,
        'medium' => 1,
        'high' => 2,
        'critical' => 3
    );

    /**
     * Whether or not $priority is greater than or equal to the default priority level
     *
     * Note that if an unknown priority is passed in, it is assumed to be higher.
     *
     * @param int $priority Priority level
     * @return bool TRUE if $priority is greater than or equal to the current priority level, FALSE otherwise
     */
    public static function highEnough($priority)
    {
        if (!array_key_exists($priority, self::$_priorities)) {
            return true; // any unknown priority is assumed to be higher
        }

        return (self::$_priorities[$priority] >= self::$_priorities[iHMS_Sysconf_Config::getInstance()->priority]);
    }

    /**
     * Is valid priority?
     *
     * @param string $priority Priority level
     * @return bool
     */
    public static function isValidPriority($priority)
    {
        return array_key_exists($priority, self::$_priorities);
    }

    /**
     * Returns an ordered list of all allowed priorities
     *
     * @static
     * @return array Ordered list of all allowed priorities
     */
    public static function getPriorityList()
    {
        asort(self::$_priorities);
        return array_keys(self::$_priorities);
    }
}
