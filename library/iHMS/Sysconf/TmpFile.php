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
 * @subpackage  TempFile
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/**
 * iHMS_Sysconf_TmpFile class
 *
 * This class helps sysconf make safe temporary files. At least, their are safe, if /tmp is not on NFS.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  TmpFile
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_TmpFile
{
    /**
     * @var string Filename of the last opened temp file
     */
    protected static $_fileName = '';

    /**
     * Open a temporary file for writing
     *
     * Returns an open file descriptor. Optionally a file extension may be passed to it.
     *
     * @param string $extension File extension
     * @return resource
     */
    public static function open($extension = '')
    {
        do {
            self::$_fileName = tempnam('/tmp', $extension);
        } while (!$fh = fopen(self::$_fileName, 'w') or !flock($fh, LOCK_EX));

        return $fh;
    }

    /**
     * Returns the name of the last opened temporary file
     *
     * @static
     * @return string name of last temporary file opened
     */
    public static function getFilename()
    {
        return self::$_fileName;
    }

    /**
     * Unlinks the last opened temporary file
     *
     * @static
     * @return bool true on success or false on failure
     */
    public static function cleanup()
    {
        return @unlink(self::$_fileName);
    }
}
