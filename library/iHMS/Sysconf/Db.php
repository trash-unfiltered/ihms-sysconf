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
 * @subpackage  Db
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_DbDriver */
require_once 'iHMS/Sysconf/DbDriver.php';

/** @see iHMS_Sysconf_Log */
require_once 'iHMS/Sysconf/Log.php';

/**
 * iHMS_Sysconf_Db class
 *
 * This class makes available an {@link iHMS_Sysconf_Db::$_config}, which is the root db driver for storing state, and
 * an {@link iHMS_Sysconf_Db::$_templates}, which is the root db driver for storing template data. Both are available
 * through their respective getters {@link iHMS_Sysconf_Db::getConfig()} and {@link iHMS_Sysconf_Db::getTemplates()}
 *
 * Requests can be sent directly to the db's by things like {@link iHMS_Sysconf_Db::getConfig()->setfield()}
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Db
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Db
{
    /**
     * @var iHMS_Sysconf_DbDriver
     */
    protected static $_config = null;

    /**
     * @var iHMS_Sysconf_DbDriver
     */
    protected static $_templates = null;

    /**
     * Loads up the database drivers
     *
     * If an array of named parameters is passed, those parameters are used as the defaults for *every* database driver
     * that is loaded up. Practically, setting (readonly => "true") is the only use of this.
     *
     * @static
     * @throws LogicException in case a database was not initialized
     * @param array $parameters OPTIONAL Array of named parameters
     * @return void
     */
    public static function load(array $parameters = array())
    {
        $config = iHMS_Sysconf_Config::getInstance()->load('', $parameters); // Load default config file

        if (!self::$_config = iHMS_Sysconf_DbDriver::getDriver($config->config)) {
            throw new LogicException("Configuration database {$config->config} was not initialized\n");
        }

        if (!self::$_templates = iHMS_Sysconf_DbDriver::getDriver($config->templates)) {
            throw new LogicException("Template database {$config->templates} was not initialized\n");
        }
    }

    /**
     * Set up a driver
     *
     * Pass it all the fields the driver needs, and one more field, called "driver" that specifies the type of driver
     * to make.
     *
     * @static
     * @throws DomainException in case Driver type is not specified
     * @throws InvalidArgumentException in case Driver of type is not found
     * @param array $config Array that holds driver configuration
     * @return iHMS_Sysconf_DbDriver
     */
    public static function makeDriver($config)
    {
        if (!isset($config['driver'])) {
            throw new DomainException("Driver type not specified\n");
        } else {
            $type = $config['driver'];
        }

        // Ensure class is loaded
        try {
            require_once 'Zend/Loader.php';
            @Zend_Loader::loadClass($className = "iHMS_Sysconf_DbDriver_{$type}");
        } catch (Zend_Exception $e) {
            throw new InvalidArgumentException("Driver '{$type}' not found: " . $e->getMessage() . "\n");
        }

        unset($config['driver']); // not a field for the object

        iHMS_Sysconf_Log::debug('db', "making DbDriver of type {$type}");

        return new $className($config);
    }

    /**
     * Return config database driver
     *
     * @static
     * @return iHMS_Sysconf_DbDriver|null
     */
    public static function getConfig()
    {
        return self::$_config;
    }

    /**
     * Returns templates database driver
     *
     * @static
     * @return iHMS_Sysconf_DbDriver|null
     */
    public static function getTemplates()
    {
        return self::$_templates;
    }

    /**
     * Save the databases, and shutdown the drivers
     *
     * @static
     * @return void
     */
    public static function save()
    {
        // TODO: This method shutdown only drivers which are declared in Config and Templates fields in the
        // configuration file while the load() method (see above) make and init ALL drivers from it

        if (self::$_config) {
            self::$_config->shutdown();
        }

        // TODO: if the installer is killed right here, the db is inconsistent. - make a signal handler??

        if (self::$_templates) {
            self::$_templates->shutdown();
        }

        self::$_config = null;
        self::$_templates = null;
    }
}
