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
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_DbDriver_Copy */
require_once 'iHMS/Sysconf/DbDriver/Copy.php';

/** @see iHMS_Sysconf_Log */
require_once 'iHMS/Sysconf/Log.php';

/**
 * iHMS_Sysconf_DbDriver_Stack class
 *
 * This sets up a stack of drivers. Items in drivers higher in the stack shadow items lower in the stack, so requests
 * for items will be passed on to the first driver in the stack that contains the item.
 *
 * Writing to the stack is more complex, because we meed to worry about readonly drivers. Instead of trying to write to
 * a readonly driver and having it fail, this class will copy the item from the readonly driver to the writable driver
 * closest to the top of the stack that accepts the given item, and then perform the write.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  DbDriver
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_DbDriver_Stack extends iHMS_Sysconf_DbDriver_Copy
{
    /**
     * Either a string representing a configuration stanza for stack or an array of DbDriver objects
     * @var string|iHMS_Sysconf_DbDriver[]
     */
    protected $_stack = null;

    /**
     * @var array
     */
    protected $_stackChangeErrors = array();

    /**
     * On initialization, the topmost driver is checked for writability
     *
     * @return void
     */
    protected function _init()
    {
        // Handle value from config file
        if (!is_array($this->_stack)) {
            $stack = array();
            foreach (preg_split('/\s*,\s/', $this->_stack) as $name) {
                if (isset(self::$_drivers[$name])) {
                    $stack[] = self::$_drivers[$name];
                } else {
                    $this->error("could not find a database named \"$name\" to use in the stack (it should be defined before the stack in the config file).");
                    continue;
                }
            }

            $this->_stack = $stack;
        }

        if (!$this->_stack) {
            $this->error("stack is empty");
        }
    }

    /**
     * Returns iterator
     *
     * Iterates over all the items in all the drivers in the whole stack. However, only return each item once, even if
     * multiple drivers contain it
     *
     * @return Iterator|void
     */
    public function getIterator()
    {
        $iterator = new AppendIterator();

        /** @var $_ iHMS_Sysconf_DbDriver  */
        array_map(
            function($_) use($iterator)
            {
                /** @var $iterator AppendIterator */
                $iterator->append($_->getIterator());
            },
            array_reverse($this->_stack)
        );

        /** @see iHMS_Sysconf_Iterator_CallbackFilter */
        require_once 'iHMS/Sysconf/Iterator/CallbackFilter.php';

        return new iHMS_Sysconf_Iterator_CallbackFilter(
            $iterator,
            function($item)
            {
                static $seen = array();
                if (!isset($seen[$item])) {
                    $seen[$item] = true;
                    return true;
                }

                return false;
            }
        );
    }

    /**
     * Calls shutdown on the entire stack. If any shutdown call returns FALSE, returns FALSE too, but only after calling
     * them all.
     *
     * Save the entire database state, and closes down the driver's access to the database
     *
     * @return bool TRUE on success, FALSE otherwise
     */
    public function shutdown()
    {
        $ret = true;

        foreach ($this->_stack as $driver) {
            $ret = $driver->shutdown();
        }

        if ($this->_stackChangeErrors) {
            $this->error('unable to save changes to: ' . join(' ', $this->_stackChangeErrors) . '.');
            $ret = false;
        }

        return $ret;
    }

    /**
     * Return TRUE if the given item exists in a database
     *
     * An item exists if any item in the stack contains it. So don't give up at the first failure, but keep digging down
     *
     * @param string $itemName Item name
     * @return bool TRUE if $itemName is found in the database, FALSE otherwise
     */
    public function exists($itemName)
    {
        foreach ($this->_stack as $driver) {
            if ($driver->exists($itemName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Query the stack
     *
     * @param string $command Command name
     * @internal string $params,... Command parameters
     * @return mixed Command result
     */
    protected function _query($command)
    {
        $params = array_slice(func_get_args(), 1);

        iHMS_Sysconf_Log::debug("db {$this->_name}", "trying to {$command}(" . join(', ', $params) . ') ..');

        foreach ($this->_stack as $driver) {
            $ret = call_user_func_array(array($driver, $command), $params);

            if (is_array($ret)) {
                if (!empty($ret)) {
                    iHMS_Sysconf_Log::debug("db {$this->_name}", "{$command} done by {$driver->_name}");
                    return $ret;
                }
            } else {
                if (!is_null($ret)) {
                    iHMS_Sysconf_Log::debug("db {$this->_name}", "{$command} done by {$driver->_name}");
                    return $ret;
                }
            }
        }

        return null;
    }

    /**
     * Make a change to a writable item, copying an item from lower in the stack first as is necessary
     *
     * @internal string $command Command name
     * @internal string $params,... Command parameters
     * @return mixed|null
     * @TODO review (argv)
     */
    protected function _change()
    {
        $args = func_get_args(array_slice(func_get_args(), 2));
        $command = array_shift($args);
        $itemName = array_shift($args);

        iHMS_Sysconf_Log::debug("db {$this->_name}", "trying to {$command}({$itemName}, " . join(', ', $args) . ') ..');

        // Check to see if we can just write to some driver in the stack.
        foreach ($this->_stack as $driver) {
            if ($driver->exists($itemName)) {
                if ($driver->_readonly) { # nope, hit a readonly one
                    break;
                }

                iHMS_Sysconf_Log::debug("db {$this->_name}", "passing to {$driver->_name} ..");

                array_unshift($args, $itemName);
                return call_user_func_array(array($driver, $command), $args);
            }
        }

        // Set if we need to copy from something
        $src = false;

        // Find out what (readonly) driver on the stack first contains the item
        foreach ($this->_stack as $driver) {
            if ($driver->exists($itemName)) {
                // Check if this modification would really have any effect.
                $ret = $this->_noChange($driver, $command, $itemName, $args);

                if (!is_null($ret)) {
                    iHMS_Sysconf_Log::debug("db {$this->_name}", "skipped {$command}({$itemName}) as it would have not effect");
                    return $ret;
                }

                // Nope, we have to copy after all
                $src = $driver;
                break;
            }
        }

        // Work out what driver on the stack will be written to. We'll take the first that accepts the item
        $writer = false;

        foreach ($this->_stack as $driver) {
            if ($driver === $src) {
                $this->_stackChangeErrors[] = $itemName;
                return null;
            }

            if (!$driver->_readonly) {
                // Adding an owner is a special case because the item may not exist yet, and so accept() should be told
                // the type, if possible. Luckily the type is the second parameter of the addOwner() command, or $args[1]..
                if ($command == 'addOwner') {
                    if ($driver->accept($itemName, $args[1])) {
                        $writer = $driver;
                        break;
                    }
                } elseif ($driver->accept($itemName)) {
                    $writer = $driver;
                    break;
                }
            }
        }

        if (!$writer) {
            iHMS_Sysconf_Log::debug("db {$this->_name}", "FAILED {$command}");
            return null;
        }

        // Do the copy if we have to
        if ($src) {
            $this->copy($itemName, $src, $writer);
        }

        // Finally, do the write
        iHMS_Sysconf_Log::debug("db {$this->_name}", "passing to {$writer->_name} ..");
        array_unshift($args, $itemName);

        return call_user_func_array(array($writer, $command), $args);

    } // end-_change();

    /**
     * A problem occurs sometimes: A write might be attempted that will not actually change the database at all. If we
     * naively copy an item up the stack in these cases, we have shadowed the real data unnecessarily. Instead, I
     * bothered to add a shitload of extra intelligence, to detect such null writes, and do nothing but return whatever
     * the current value is. Gar gar gar!
     *
     * @param iHMS_Sysconf_DbDriver $driver
     * @param string $command Command name
     * @param string $itemName Item name
     * @param array $args Arguments
     * @return string|null
     */
    protected function _noChange($driver, $command, $itemName, $args)
    {
        if ($command == 'addOwner') {
            $value = array_shift($args);

            //  If the owner is already there, no change
            foreach ($driver->getOwners($itemName) as $owner) {
                if ($owner == $value) {
                    return $value;
                }
            }

            return null;
        } elseif ($command == 'removeOwner') {
            $value = array_shift($args);

            //  If the owner is already in the list, there is a change
            foreach ($driver->getOwners($itemName) as $owner) {
                if ($owner == $value) {
                    return null;
                }
            }

            return $value; // No change
        } elseif ($command == 'removeField') {
            $value = array_shift($args);

            //  If the field is no present, no change
            foreach ($driver->getFields($itemName) as $field) {
                if ($field == $value) {
                    return null;
                }
            }

            return $value; // No change
        }

        // Ok, the rest is close to the same for fields, flags, and variables
        $list = array();
        $getCommand = null;

        if ($command == 'setField') {
            $list = $driver->getFields($itemName);
            $getCommand = 'getField';
        } elseif ($command == 'setFlag') {
            $list = $driver->getFlags($itemName);
            $getCommand = 'getFlag';
        } elseif ($command == 'setVariable') {
            $list = $driver->getVariables($itemName);
            $getCommand = 'getVariable';
        } else {
            $this->error("internal error; bad command: $command");
        }

        $thing = array_shift($args);
        $value = array_shift($args);
        $currentValue = $driver->{$getCommand}($itemName, $thing);

        // If the thing doesn't exist yet, there will be a change
        $exists = false;

        foreach ($list as $i) {
            if ($thing == $i) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            return $currentValue;
        }

        // If the thing does not have the same value, there will be a change
        if ($currentValue == $value) {
            return $currentValue;
        }

        return null;

    } // end-_noChange();

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
    public function  addOwner($itemName, $ownerName, $type)
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
     * @return array|null
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
     * Set the given field the the given value, and return the value, or FALSE if setting failed
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
        return $this->_change(__FUNCTION__, $itemName, $fieldName);
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
     * Return 'true' if the given flag is set for the given item, 'false' if not.
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
     * Set the given flag to the given value (will be one of 'true' or 'false'), and return the value. Or return NULL if
     * setting failed
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
     * Set the given variable of the given item to the given value, and return the value, or NULL if setting failed
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
}
