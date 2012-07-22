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

/** @see iHMS_Sysconf_Template */
require_once 'iHMS/Sysconf/Template.php';

/** @see iHMS_Sysconf_Log */
require_once 'iHMS/Sysconf/Log.php';

/** @see Zend_Filter_Word_UnderscoreToCamelCase */
require_once 'Zend/Filter/Word/UnderscoreToCamelCase.php';

/**
 * iHMS_Sysconf_DbDriver class
 *
 * This is a base class that may be inherited from by the sysconf database drivers. It provides a simple interface that
 * sysconf uses to look up information related to items in the database.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  DbDriver
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
abstract class iHMS_Sysconf_DbDriver
{
    /**
     * @var string The name of the database. This field is required
     */
    protected $_name = null;

    /**
     * Set to true if this database driver is read only. Defaults to false
     *
     * @var bool Is read only database?
     */
    protected $_readonly = false;

    /**
     * Detemrines whether a backup should be made of the old version of the database or not
     *
     * @var string
     */
    protected $_backup = true;

    /**
     * Tells if a database driver is required for proper operation of sysconf. Required drivers can cause sysconf to
     * abort if they are not accessible. It can be useful to make remote databases non-required, so sysconf is usable
     * if connections to them go down. Defaults to TRUE
     *
     * @var bool Is required database driver?
     */
    protected $_required = true;

    /**
     * Tells if a database driver failed to work. If this is set the driver should begin to reject all requests
     *
     * @var bool Database driver failed to work?
     */
    protected $_failed = false;

    /**
     * A regular expression indicating types of items that may be queried in this driver. Defaults to accepting all
     * types of items
     *
     * @var string
     */
    protected $_acceptType = null;

    /**
     * A regular expression indicating types of items that are rejected by this driver
     *
     * @var string
     */
    protected $_rejectType = null;

    /**
     * A regular expression that is matched against item names to see if they are accepted by this driver. Defaults to
     * accepting all item names
     *
     * @var string
     */
    protected $_acceptName = null;

    /**
     * A regular expression that is matched against item names to see if they are rejected by this driver
     *
     * @var string
     */
    protected $_rejectName = null;

    /**
     * @var array Class data
     */
    protected static $_drivers = array();

    /**
     * Constructor
     *
     * @param array $params Parameters
     */
    public function __construct($params)
    {
        // Set property from parameters
        foreach ($params as $field => $value) {
            if ($field == 'readonly' || $field == 'required' || $field == 'backup') {

                // Convert from true/false strings to boolean
                if (strtolower($value) == 'true') {
                    $this->{'_' . $field} = true;
                    continue;
                }

                if (strtolower($value) == 'false') {
                    $this->{'_' . $field} = false;
                    continue;
                }
            } elseif (preg_match('/^(accept|reject)/', $field, $match)) {
                // Tests user's regular expression and raises an error if compile failed
                if (false === @preg_match("/{$value}/", '')) {
                    $this->error(
                        sprintf(_("Wrong configuration value '%s' for %s field: %s"), $value, $field, join(' ', error_get_last()))
                    );
                }
            }

            $this->{'_' . $field} = $value;
        }

        // Name is a required field
        if (!isset($this->_name)) {
            // Set to something since error method uses this field
            $this->_name = _('(unknown)');
            $this->error(_('no name specified'));
        }

        // Register in class data.
        self::$_drivers[$this->_name] = $this;

        // Other initialization
        $this->_init();
    }

    /**
     * Called when a new object of this class is instantiated
     *
     * Override to add initialization code
     */
    protected function _init()
    {
    }

    /**
     * Raise tbe given error message
     *
     * Rather than ever dying on errors, drivers should instead call this method to state than an error was encountered.
     * If the driver is required, it will be a fatal error. If not, the error message will merely be displayed to the
     * user, the driver will be marked as failed, and sysconf will continue on, "dazed and confuzed".
     *
     * @param string $errorMessage Error message
     * @return void
     */
    public function error($errorMessage)
    {
        if ($this->_required) {
            iHMS_Sysconf_Log::warn("DbDriver {$this->_name}: {$errorMessage}");
            exit(1);
        } else {
            iHMS_Sysconf_Log::warn("DbDriver {$this->_name} warning: {$errorMessage}");
        }
    }

    /**
     * Returns driver db name
     *
     * @return string Driver db name
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Allows any driver to be looked up by name
     *
     * If any driver with the given name exists, it is returned
     *
     * @static
     * @param string $driverName Driver name
     * @return iHMS_Sysconf_DbDriver|null
     */
    public static function getDriver($driverName)
    {
        return isset(self::$_drivers[$driverName]) ? self::$_drivers[$driverName] : null;
    }

    /**
     * Returns all drivers
     *
     * @static
     * @return array
     */
    public static function getDrivers()
    {
        return self::$_drivers;
    }

    /**
     * Current driver accept queries for the given item?
     *
     * Return TRUE if this driver will accept queries for the given item. Uses the various accept* and reject* fields
     * to determine this.
     *
     * The type field should be passed when possible, giving the type of the item. If it is not passed, the function
     * will try to look up the type in the item's template, but that may not always work, if the template is not yet
     * set up.
     *
     * @param string $itemName Item name
     * @param string $type OPTIONAL item type
     * @return bool TRUE if the driver will accept queries for the given item, FALSE otherwise
     */
    public function accept($itemName, $type = null)
    {
        if ($this->_failed) {
            return false;
        }

        if ( // TODO test behavior
            !is_null($this->_acceptName) && !preg_match("/{$this->_acceptName}/i", $itemName) ||
            !is_null($this->_rejectName) && preg_match("/{$this->_rejectName}/i", $itemName)
        ) {
            iHMS_Sysconf_Log::debug("db {$this->_name}", "reject {$itemName}");
            return false;
        }

        if (isset($this->_acceptType) || isset($this->_rejectType)) {
            if (!$type) {
                $template = iHMS_Sysconf_Template::get($this->getField($itemName, 'template'));

                if (!$template) {
                    return true;
                } // no type to act on

                if (!$template->type) {
                    $type = '';
                }
            }

            if (!is_null($this->_acceptType) && !preg_match("/{$this->_acceptType}/i", $type)) {
                return false;
            }

            if (!is_null($this->_rejectType) && preg_match("/{$this->_rejectType}/i", $type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Is an item containing a password?
     *
     * Returns TRUE if the item appears to hold a password. This is pretty messy; we have to dig up its template
     * (unless it _is_ a template).
     *
     * @param string $itemName
     * @return bool TRUE if $itemName is of type password, FALSE otherwise
     */
    public function isPassword($itemName)
    {
        $template = $this->getField($itemName, 'template');

        if (!$template) {
            return false;
        }

        $template = iHMS_Sysconf_Template::get($template);

        if (!$template) {
            return false;
        }

        $type = $template->type ? : '';

        return ($type == 'password') ? true : false;
    }

    /**
     * Create an iterator object that can be used to iterate over each item in the database, and return it.
     *
     * Each subclass must implement this method
     *
     * @abstract
     * @return Iterator
     */
    abstract public function getIterator();

    /**
     * Save the entire database state, and closes down the driver's access to the database
     *
     * @abstract
     * @return bool TRUE on success, FALSE otherwise
     */
    abstract public function shutdown();

    /**
     * Return TRUE if the given item exists in the database.
     *
     * @abstract
     * @param string $itemName Item name
     * @return bool TRUE if $itemName is found in the database, FALSE otherwise
     */
    abstract public function exists($itemName);

    /**
     * Register an owner for the given item. Returns the owner name, or NULL if this failed
     *
     * Note that adding an owner can cause a new item to spring into existance. The type field is used to tell the
     * DbDriver what type of item is being added (the DbDriver may decide to reject some types of items).
     *
     * @abstract
     * @param string $itemName Item name
     * @param string $ownerName Owner name
     * @param string $type Item type
     * @return string|null
     */
    abstract public function addOwner($itemName, $ownerName, $type);

    /**
     * Remove an owner from an item.
     *
     * Returns the owner name, or NULL if removal failed. If the number of owners goes to zero, the item should be
     * removed.
     *
     * @abstract
     * @param string $itemName Item name
     * @param string $ownerName Owner name
     * @return string|null
     */
    abstract public function removeOwner($itemName, $ownerName);

    /**
     * Return a list of all owners of the item
     *
     * @abstract
     * @param string $itemName Item name
     * @return array|null An array that hold a list of owner for the given item or NULL if the item doesn't exists
     */
    abstract public function getOwners($itemName);

    /**
     * Return the given field of the given item, or NULL if getting that field failed
     *
     * @abstract
     * @param string $itemName Item name
     * @param string $fieldName Field name
     * @return string|null
     */
    abstract public function getField($itemName, $fieldName);

    /**
     * Set the given field with the the given value, and return the value, or NULL if setting failed
     *
     * @abstract
     * @param string $itemName Item name
     * @param string $fieldName Field name
     * @param string $value Field value
     * @return string|null
     */
    abstract public function setField($itemName, $fieldName, $value);

    /**
     * Remove the given field from the given item, if it exists. This is _not_ the same as setting the field to '',
     * instead, it removes it from the list of fields. Returns TRUE unless removing of the field failed, when it will
     * return FALSE
     *
     * @abstract
     * @param string $itemName Item name
     * @param string $fieldName Field name
     * @return bool TRUE on sucess, FALSE otherwise
     */
    abstract public function removeField($itemName, $fieldName);

    /**
     * Return the fields present in the item
     *
     * @abstract
     * @param string $itemName Item name
     * @return array
     */
    abstract public function getFields($itemName);

    /**
     * Return 'true if the given flag is set for the given item, 'false' if not.
     *
     * @abstract
     * @param string $itemName Item name
     * @param string $flagName Flag name
     * @return string 'true' if $flagName is set for the given $itemName, 'false' otherwise
     */
    abstract public function getFlag($itemName, $flagName);

    /**
     * Set the given flag to the given value
     *
     * will be one of 'true' or 'false', and return the value. Or return NULL if setting failed
     *
     * @abstract
     * @param string $itemName Item name
     * @param string $flagName Flag name
     * @param string $value Flag value
     * @return string|null
     */
    abstract public function setFlag($itemName, $flagName, $value);

    /**
     * Return the flags that are present for the item
     *
     * @abstract
     * @param string $itemName Item name
     * @return array
     */
    abstract public function getFlags($itemName);

    /**
     * Return the value of the given variable of the given item, or NULL if there is no such variable
     *
     * @abstract
     * @param string $itemName Item name
     * @param string $variableName Variable name
     * @return string|null
     */
    abstract public function getVariable($itemName, $variableName);

    /**
     * Set the given variable of the given item to the value, and return the value, or NULL if setting failed
     *
     * @abstract
     * @param string $itemName Item name
     * @param string $variableName Variable name
     * @param string $value Variable value
     * @return string|null
     */
    abstract public function setVariable($itemName, $variableName, $value);

    /**
     * Return the variables that exist for the item
     *
     * @abstract
     * @param string $itemName Item name
     * @return array
     */
    abstract public function getVariables($itemName);
}
