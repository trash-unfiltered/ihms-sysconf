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
 * @subpackage  DbDriver
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

namespace iHMS\Sysconf\DbDriver;

use iHMS\Sysconf\DbDriver\Copy;
use iHMS\Sysconf\Log;
use Iterator;

/**
 * Backup class
 *
 * This driver passes all reads and writes on to another database. But copies of all writes are sent to a second
 * database, too.
 */
class Backup extends Copy
{
    /**
     * @var null The database to pass reads and writes to.
     */
    protected $_db = null;

    /**
     * The database to write the backup to.
     *
     * In the config file, the name of the database can be used.
     *
     * @var null
     */
    protected $_backupDb = null;

    /**
     * On initialization, convert db names to drivers.
     */
    protected function _init()
    {
        // Handle values from config file

        foreach (array('db', 'backupDb') as $f) {
            if (!is_object($f)) {
                $db = $this->getDriver($this->{'_' . $f});

            }
        }
    }

    /**
     * Ensures that the given item is backed up by doing a full copy of it into the backup database
     *
     * @param string $item
     */
    public function copy($item)
    {

    }

    /**
     * Saves both databases
     *
     * @return bool|void
     */
    public function shutdown()
    {

    }

    /**
     * Read from db
     *
     * @internal string $command
     * @internal mixed $parameters... Command parameters
     * @return mixed
     */
    protected function _query()
    {
        $args = func_get_args();
        $command = array_shift($args);
        return $this->_db->{$command}($args);
    }

    /**
     * Read from db and write to backup db on success
     *
     * @internal string $command
     * @internal mixed $parameters... Command parameters
     * @return mixed
     */
    protected function _change()
    {
        $args = func_get_args();
        $command = array_shift($args);

        $ret = $this->_db->{$command}($args);

        if (!is_null($ret)) {
            $this->_backup->{$command}($args);
        }

        return $ret;
    }

    /**
     * Create an iterator object that can be used to iterate over each item in the database, and return it.
     *
     * Each subclass must implement this method
     *
     * @return Iterator
     */
    public function getIterator()
    {
        return $this->_query(__FUNCTION__);
    }

    /**
     * Return TRUE if the given item exists in the database.
     *
     * @param string $itemName Item name
     * @return bool TRUE if $itemName is found in the database, FALSE otherwise
     */
    public function exists($itemName)
    {
        return $this->_query(__FUNCTION__, $itemName);
    }

    /**
     * Register an owner for the given item. Returns the owner name, or NULL if this failed
     *
     * Note that adding an owner can cause a new item to spring into existance. The type field is used to tell the
     * DbDriver what type of item is being added (the DbDriver may decide to reject some types of items).
     *
     * @param string $itemName Item name
     * @param string $ownerName Owner name
     * @param string $type Item type
     * @return string|null
     */
    public function addOwner($itemName, $ownerName, $type)
    {
        return $this->_change(__FUNCTION__, $itemName, $ownerName, $type);
    }

    /**
     * Remove an owner from an item.
     *
     * Returns the owner name, or NULL if removal failed. If the number of owners goes to zero, the item should be
     * removed.
     *
     * @param string $itemName Item name
     * @param string $ownerName Owner name
     * @return string|null
     */
    public function removeOwner($itemName, $ownerName)
    {
        return $this->_change(__FUNCTION__, $itemName, $ownerName);
    }

    /**
     * Return a list of all owners of the item
     *
     * @param string $itemName Item name
     * @return array|null An array that hold a list of owner for the given item or NULL if the item doesn't exists
     */
    public function getOwners($itemName)
    {
        return $this->_query(__FUNCTION__, $itemName);
    }

    /**
     * Return the given field of the given item, or NULL if getting that field failed
     *
     * @param string $itemName Item name
     * @param string $fieldName Field name
     * @return string|null
     */
    public function getField($itemName, $fieldName)
    {
        return $this->_query(__FUNCTION__, $itemName, $fieldName);
    }

    /**
     * Set the given field with the the given value, and return the value, or NULL if setting failed
     *
     * @param string $itemName Item name
     * @param string $fieldName Field name
     * @param string $value Field value
     * @return string|null
     */
    public function setField($itemName, $fieldName, $value)
    {
        return $this->_change(__FUNCTION__, $itemName, $fieldName, $value);
    }

    /**
     * Return the fields present in the item
     *
     * @param string $itemName Item name
     * @return array
     */
    public function getFields($itemName)
    {
        return $this->_query(__FUNCTION__, $itemName);
    }

    /**
     * Return 'true if the given flag is set for the given item, 'false' if not.
     *
     * @param string $itemName Item name
     * @param string $flagName Flag name
     * @return string 'true' if $flagName is set for the given $itemName, 'false' otherwise
     */
    public function getFlag($itemName, $flagName)
    {
        return $this->_query(__FUNCTION__, $itemName, $flagName);
    }

    /**
     * Set the given flag to the given value
     *
     * will be one of 'true' or 'false', and return the value. Or return NULL if setting failed
     *
     * @param string $itemName Item name
     * @param string $flagName Flag name
     * @param string $value Flag value
     * @return string|null
     */
    public function setFlag($itemName, $flagName, $value)
    {
        return $this->_change(__FUNCTION__, $itemName, $flagName, $value);
    }

    /**
     * Return the flags that are present for the item
     *
     * @param string $itemName Item name
     * @return array
     */
    public function getFlags($itemName)
    {
        return $this->_query(__FUNCTION__, $itemName);
    }

    /**
     * Return the value of the given variable of the given item, or NULL if there is no such variable
     *
     * @param string $itemName Item name
     * @param string $variableName Variable name
     * @return string|null
     */
    public function getVariable($itemName, $variableName)
    {
        return $this->_query(__FUNCTION__, $itemName, $variableName);
    }

    /**
     * Set the given variable of the given item to the value, and return the value, or NULL if setting failed
     *
     * @param string $itemName Item name
     * @param string $variableName Variable name
     * @param string $value Variable value
     * @return string|null
     */
    public function setVariable($itemName, $variableName, $value)
    {
        return $this->_change(__FUNCTION__, $itemName, $variableName, $value);
    }

    /**
     * Return the variables that exist for the item
     *
     * @param string $itemName Item name
     * @return array
     */
    public function getVariables($itemName)
    {
        return $this->_query(__FUNCTION__, $itemName);

    }

    /**
     * Remove the given field from the given item, if it exists. This is _not_ the same as setting the field to '',
     * instead, it removes it from the list of fields. Returns TRUE unless removing of the field failed, when it will
     * return FALSE
     *
     * @param string $itemName Item name
     * @param string $fieldName Field name
     * @return bool TRUE on sucess, FALSE otherwise
     */
    public function removeField($itemName, $fieldName)
    {
        // Not available from this driver
    }
}
