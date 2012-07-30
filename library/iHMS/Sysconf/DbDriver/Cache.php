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

use iHMS\Sysconf\DbDriver;
use IteratorAggregate;
use Iterator;
use Traversable;
use ArrayIterator;
use iHMS\Sysconf\Iterator\CallbackFilter;
use iHMS\Sysconf\Log;

/**
 * Cache class
 *
 * This is a base class for cacheable database drivers. Use this as the base class for your driver if it makes sense to
 * load and store items as a whole (eg, if you are using text files to represent each item, or downloading whole items
 * over the net).
 *
 * Don't use this base class for your driver if it makes more sense for your driver to access individual parts of each
 * item independantly (by querying a (fast) database, for example).
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  DbDriver
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
abstract class Cache extends DbDriver
{
    /**
     * An associative array that holds the data for each loaded item in the database. Each key is an item name;
     * values are either null (used to indicate that an item used to exist here, but was deleted), or are themselves
     * associative array that hold the item data
     *
     * @var array Items data
     */
    protected $_cache = array();

    /**
     * An associative array that holds data about what items in the cache are dirty. Each key is an item name;
     * if the value is TRUE, the item is dirty
     *
     * @var array
     */
    protected $_dirty = array();

    /**
     * Derived classes can call this method to add an item to the cache. If the item is already in the cache, no change
     * will be made.
     *
     * The entry field is a rather complex array structure to represent the item. The structure is an associative array
     * with 4 items:
     *
     * owners:
     *   The value of this key must be an associative array whose keys are the owner names, and values are TRUE.
     * fields:
     *   The value of this key must be an associative array whose keys are the field names, and values are the field
     *   values.
     * variables:
     *   The value of this key must be an associative array whose keys are the variable names, and values are the
     *   variable values.
     * flags:
     *   The value of this key must be an associative array whose keys are the flag names, and values are the flag
     *   values.
     *
     * @param string $itemName Item name
     * @param array $entry
     */
    public function cacheAdd($itemName, array $entry)
    {
        if (!array_key_exists($itemName, $this->_cache)) {
            $this->_cache[$itemName] = $entry;
            $this->_dirty[$itemName] = false;
        }
    }

    /**
     * Looks up an item in the cache and returns a complex data structure of the same format as the cacheadd() entry
     * parameter
     *
     * @param string $itemName Item name
     * @return array
     */
    public function cacheData($itemName)
    {
        return $this->_cache[$itemName];
    }

    /**
     * Ensure that a given item is loaded up in the cache
     *
     * @param string $itemName Item name
     * @return bool TRUE if the given item is cached, FALSE otherwise
     */
    public function cached($itemName)
    {
        if (!array_key_exists($itemName, $this->_cache)) {
            Log::debug("db {$this->_name}", "cache miss on {$itemName}");
            $this->load($itemName);
        }

        return array_key_exists($itemName, $this->_cache);
    }

    /**
     * Create an iterator that can be used to iterate over each item in the db, and return it.
     *
     * Derived classes *should* override this method and construct their own iterator. Then at the end return an
     * {@link AppendIterator} that holds both iterators (this one first)
     *
     * @return CallbackFilter|Traversable
     */
    public function getIterator()
    {
        $cache = $this->_cache;

        $iterator = new CallbackFilter(
            new ArrayIterator(array_keys($cache)),
            function($_) use($cache)
            {
                if (is_null($cache[$_])) {
                    return false;
                }

                return true;
            }
        );

        return $iterator;
    }

    /**
     * Synchronizes the underlying database with the cache.
     *
     * Saving a item involves feeding the item from the cache into the underlying database, and then telling the
     * underlying db to save it.
     *
     * However, if the item is NULL in the cache, we instead tell the underlying database to remove it.
     *
     * Returns TRUE unless any of the operations fail.
     *
     * @return bool TRUE on sucess, FALSE on any faillure
     */
    public function shutdown()
    {
        if ($this->_readonly) {
            return false;
        }

        $ret = true;

        foreach (array_keys($this->_cache) as $itemName) {
            if (is_null($this->_cache[$itemName])) { // If item marked as deleted in the cache
                // Remove item, then remove marker in cache.
                if (!$this->remove($itemName)) {
                    $ret = false; // Removal of item failed
                }

                unset($this->_cache[$itemName]);
            } elseif ($this->_dirty[$itemName]) {
                if (!$this->save($itemName, $this->_cache[$itemName])) {
                    $ret = false;
                }

                $this->_dirty[$itemName] = false;
            }
        }

        return $ret;
    }

    /**
     * Return TRUE if the given item exists in the database.
     *
     * Derived classes should override this method. Be sure to call parent::exists($itemName) first, and return
     * whatever it returns unless it returns FALSE, to check if the item exists in the cache first!
     *
     * This method returns one of three values:
     *
     * TRUE  - The item is in the cache
     * NULL - The item is marked as deleted in the cache, so does not longer exist in the database
     * FALSE - The item is not in the cache; up to derived class now
     *
     * @param string $itemName Item name
     * @return bool|null TRUE if the given item exists in database, NULL if it's marked as deleted, FALSE otherwise
     */
    public function exists($itemName)
    {
        if (array_key_exists($itemName, $this->_cache)) {
            return is_null($this->_cache[$itemName]) ? null : true;
        }

        return false;
    }

    /**
     * Add an owner, if the underlying database is not readonly, and if the given type is acceptable
     *
     * Returns the owner name, or NULL if this failed
     *
     * Note that adding an owner can cause a new item to spring into existance. The type field is used to tell the
     * DbDriver what type of item is being added (the DbDriver may decide to reject some types of items).
     *
     * @param string $itemName Item name
     * @param string $ownerName Owner name
     * @param string $type Item tyme
     * @return string|null Owner name on sucess, NULL on faillure
     */
    public function  addOwner($itemName, $ownerName, $type)
    {
        if ($this->_readonly) { // The database is marked as readonly
            return null;
        }

        $this->cached($itemName); // Ensure the item is cached

        if (!isset($this->_cache[$itemName])) {
            if (!$this->accept($itemName, $type)) {
                return null;
            }

            Log::debug("db {$this->_name}", "creating in-cache {$itemName}");

            // The item springs into existance
            $this->_cache[$itemName] = array(
                'owners' => array(),
                'fields' => array(),
                'variables' => array(),
                'flags' => array()
            );
        }

        if (!array_key_exists($ownerName, $this->_cache[$itemName]['owners'])) {
            $this->_cache[$itemName]['owners'][$ownerName] = true;
            $this->_dirty[$itemName] = true;
        }

        return $ownerName;
    }

    /**
     * Remove an owner from the cache. If all owners are removed, the item is marked as removed in the cache.
     *
     * Returns the owner name, or NULL if removal failed. If the number of owners goes to zero, the item should be
     * removed.
     *
     * @param string $itemName Item name
     * @param string $ownerName Owner name
     * @return string|null Owner name on sucess, NULL on faillure
     */
    public function removeOwner($itemName, $ownerName)
    {
        if ($this->_readonly || !$this->cached($itemName)) {
            return null;
        }

        //  Remove the owner from the cache
        if (array_key_exists($ownerName, $this->_cache[$itemName]['owners'])) {
            unset($this->_cache[$itemName]['owners'][$ownerName]);
            $this->_dirty[$itemName] = true;
        }

        // If number of owners goes to zero, item should be removed  - Mark the item as removed in the cache
        if (empty($this->_cache[$itemName]['owners'])) {
            $this->_cache[$itemName] = null;
            $this->_dirty[$itemName] = true;
        }

        return $ownerName;
    }

    /**
     * Pull owners out of the cache
     *
     * @param string $itemName Item name
     * @return array|null List of owners for the given item or null if the item is not in the database
     */
    public function getOwners($itemName)
    {
        if (!$this->cached($itemName)) {
            return null;
        }

        return array_keys($this->_cache[$itemName]['owners']);
    }

    /**
     * Pulls the field out of the cache
     *
     * Return the given field of the given item, or NULL if getting that field failed
     *
     * @param string $itemName Item name
     * @param string $fieldName Field name
     * @return string|null
     */
    public function getField($itemName, $fieldName)
    {
        if (!$this->cached($itemName)) {
            return null;
        }

        return array_key_exists($fieldName, $this->_cache[$itemName]['fields'])
            ? $this->_cache[$itemName]['fields'][$fieldName]
            : null;
    }

    /**
     * Set the field in the cache, if the underlying database is not readonly
     *
     * Return the value set, or NULL if setting failed
     *
     * @param string $itemName Item name
     * @param string $fieldName Field name
     * @param string $value Field value
     * @return string|null Field value on sucess, NULL if setting failed
     */
    public function setField($itemName, $fieldName, $value)
    {
        if ($this->_readonly || !$this->cached($itemName)) {
            return null;
        }

        $this->_dirty[$itemName] = true;

        return $this->_cache[$itemName]['fields'][$fieldName] = $value;
    }

    /**
     * Remove the field from the cache, if the underlying database is not readonly
     *
     * Remove the given field from the given item, if it exists. This is _not_ the same as setting the field to '',
     * instead, it removes it from the list of fields. Returns TRUE unless removing of the field failed, when it will
     * return FALSE
     *
     * @param string $itemName Itenm name
     * @param string $fieldName Field name
     * @return bool TRUE on sucess, FALSE otherwise
     */
    public function removeField($itemName, $fieldName)
    {
        if ($this->_readonly || !$this->cached($itemName)) {
            return false;
        }

        $this->_dirty[$itemName] = true;
        unset($this->_cache[$itemName]['fields'][$fieldName]);

        return true;
    }

    /**
     * Pulls the field list out of the cache
     *
     * @param string $itemName Item name
     * @return array|null List of fields for the given item or NULL if the item is not in the database
     */
    public function getFields($itemName)
    {
        if (!$this->cached($itemName)) {
            return null;
        }

        return array_keys($this->_cache[$itemName]['fields']);
    }

    /**
     * Pulls the flag out of the cache
     *
     * @param string $itemName Item name
     * @param string $flagName Flag name
     * @return string 'true' or 'false'
     */
    public function getFlag($itemName, $flagName)
    {
        if (!$this->cached($itemName)) {
            return null;
        }

        if (array_key_exists($flagName, $this->_cache[$itemName]['flags'])) {
            return $this->_cache[$itemName]['flags'][$flagName];
        }

        return 'false';
    }

    /**
     * Sets the flag in the cache, if the underlying database is not readonly
     *
     * Set the given flag to the given value (will be one of "true" or "false"), and return the value. Or return NULL if
     * setting failed
     *
     * @param string $itemName Item name
     * @param string $flagName Flag Name
     * @param string $value Flag value
     * @return string|null Flag value set on sucess, NULL on faillure
     */
    public function setFlag($itemName, $flagName, $value)
    {
        if ($this->_readonly || !$this->cached($itemName)) {
            return null;
        }

        $this->_dirty[$itemName] = true;

        return $this->_cache[$itemName]['flags'][$flagName] = $value;
    }

    /**
     * Return the flags that are present for the item
     *
     * @param string $itemName Item name
     * @return array|null List of flags for the given item or NULL if the item is not in the database
     */
    public function getFlags($itemName)
    {
        if (!$this->cached($itemName)) {
            return null;
        }

        return array_keys($this->_cache[$itemName]['flags']);
    }

    /**
     * Pulls the variable out of the cache
     *
     * Return the value of the given variable of the given item, or NULL if there is no such variable
     *
     * @param string $itemName Item name
     * @param string $variableName Variable name
     * @return string|null
     */
    public function getVariable($itemName, $variableName)
    {
        if (!$this->cached($itemName)) {
            return null;
        }

        return array_key_exists($variableName, $this->_cache[$itemName]['variables'])
            ? $this->_cache[$itemName]['variables'][$variableName]
            : null;
    }

    /**
     * Sets the variable  in the cache, if the underlying db is not readonly
     *
     * Set the given variable of the given item to the value, and return the value, or NULL if setting failed
     *
     * @param string $itemName Item name
     * @param string $variableName Variable name
     * @param string $value Variable value
     * @return string|null Variable value set on success, NULL otherwise
     */
    public function setVariable($itemName, $variableName, $value)
    {
        if ($this->_readonly || !$this->cached($itemName)) {
            return null;
        }

        $this->_dirty[$itemName] = true;

        return $this->_cache[$itemName]['variables'][$variableName] = $value;
    }

    /**
     * Return the variables that exist for the item
     *
     * @param string $itemName Item name
     * @return array|null List of variables for the given item or NULL if the item is not in the database
     */
    public function getVariables($itemName)
    {
        if (!$this->cached($itemName)) {
            return null;
        }

        return array_keys($this->_cache[$itemName]['variables']);
    }

    /**
     * Ensure that the given item is loaded. It will want to call back to the cacheadd method (see below) to add an
     * item or items to the cache.
     *
     * Derived classes need to implement this method in most cases
     *
     * @param string $itemName Item name
     */
    public function load($itemName)
    {
    }

    /**
     * This method will be passed a an identical array with the same format as what the load method should
     * return. The data in the array should be saved
     *
     * Derived classes need to implement this method in most cases
     *
     * @param string $itemName Item name
     * @param $value
     * @return bool|null
     */
    function save($itemName, $value)
    {
    }

    /**
     * Remove a item from the database
     *
     * Derived classes need to implement this method in most cases
     *
     * @param string $itemName Item name
     * @return bool|null
     */
    public function remove($itemName)
    {
    }
}
