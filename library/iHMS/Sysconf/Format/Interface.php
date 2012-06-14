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
 * @subpackage  Format
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */


/**
 * iHMS_Sysconf_Format class
 *
 * This is the base of a class of objects that format database output in various ways, and can read in parse the result
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Format
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
interface iHMS_Sysconf_Format_Interface
{
    /**
     * Read one record from the filehandle, parse it, and return a list with two elements.
     *
     * The first is the name of the item that was read, and the second is a structure as required by
     * {@link iHMS_Sysconf_DbDriver_Cache}. Note that the filehandle may contain multiple records, so
     * it must be able to recognize an end-of-record delimiter of some kind and stop reading after it.
     *
     * @abstract
     * @param resource $fileHandle
     * @return array list of two elements
     */
    public function read($fileHandle);

    /**
     * Called at the beginning of each file that is written, before write() is called
     *
     * @abstract
     * @param resource $filehandle
     * @return void
     */
    public function beginFile($filehandle);

    /**
     * Format a record and and write it out to the filehandle. Should include an end-of-record marker of some sort that
     * can be recognized by the parse function.
     *
     * $data is the same structure read should return.
     *
     * Returns true on success and false on error
     *
     * @abstract
     * @param resource $filehandle Filehandle
     * @param array $data List with two elements to write
     * @param string $itemName Itemname
     * @return bool TRUE on sucess, FALSE otherwise
     */
    public function write($filehandle, $data, $itemName);

    /**
     * Called at the end of each file that is written
     *
     * @abstract
     * @param resource $filehandle
     * @return void
     */
    public function endFile($filehandle);
}
