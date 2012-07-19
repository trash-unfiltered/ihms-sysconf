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

/** @see iHMS_Sysconf_DbDriver_Cache */
require_once 'iHMS/Sysconf/DbDriver/Cache.php';

/** @see iHMS_Sysconf_Log */
require_once 'iHMS/Sysconf/Log.php';

/**
 * Class iHMS_Sysconf_DbDriver_Pipe
 *
 * This is a sysconf database driver that reads the db from a file descriptor when it starts, and writes it out to
 * another when it saves it. By default, stdin and stdout are used.
 *
 * Note: Setting both $_infd and $_outfd to "none" gets you a writable temporary db in memory.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  DbDriver
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_DbDriver_Pipe extends iHMS_Sysconf_DbDriver_Cache
{
    /**
     * File descriptor number to read from
     *
     * Defaults to reading from stdin. If it's set to "none", the db won't bother to try to read in an initial database.
     *
     * @var int|string
     */
    protected $_infd = null;

    /**
     * File descriptor number to write to
     *
     * Defaults to writing to stdout. If it's set to "none", the db will be thrown away rather than saved
     *
     * @var int|string
     */
    protected $_outfd = null;

    /**
     * The Format object to use for reading and writing.
     *
     * In the config file, just the name of the format to use, such as '822' can be specified. Default is 822.
     *
     * @var string|iHMS_Sysconf_Format_Interface
     */
    protected $_format = '822';

    /**
     * Intiialize the database driver
     *
     * On initialization, load the entire db into memory and populate the cache.
     *
     * @return null|void
     */
    protected function _init()
    {
        try {
            /** @see Zend_Loader */
            require_once 'Zend/Loader.php';
            @Zend_Loader::loadClass($formatClass = 'iHMS_Sysconf_Format_' . $this->_format);
        } catch (Zend_Exception $e) {
            $this->error("error setting up format object {$this->_format}: " . $e->getMessage());
        }

        $this->_format = new $formatClass();

        if (!is_null($this->_infd)) {
            if ($this->_infd != 'none') {
                if (!($fh = @fopen("php://fd/{$this->_infd}", 'r'))) {
                    $this->error("could not open file descriptor #[$this->_infd}: {$php_errormsg}");
                }
            }
        } else {
            $fh = fopen('php://stdin', 'r');
        }

        parent::_init();

        iHMS_Sysconf_Log::debug("db {$this->_name}", 'Loading database');

        // Now read in the whole file using the iHMS_Sysconf_Format_Interface object
        if (isset($fh)) {
            while (!feof($fh)) {
                if ($ret = $this->_format->read($fh)) {
                    list($item, $cache) = $ret;
                    $this->_cache[$item] = $cache;
                }
            }

            fclose($fh);
        }
    }

    /**
     * Save the entire cache out to the fd.
     *
     * Always write the cache, even if it's not dirty, for consistency's sake.
     *
     * @return bool|void
     */
    public function shutdown()
    {
        if ($this->_readonly) {
            return false;
        }

        if (!is_null($this->_outfd)) {
            if ($this->_outfd != 'none') {
                if (!($fh = @fopen("php://fd/{$this->_outfd}", 'w'))) {
                    $this->error("could not open file descriptor #{$this->_outfd}: " . join(' ', error_get_last()));
                }
            }
        } else {
            $fh = fopen('php://stdout', 'w');
        }

        if (isset($fh)) {
            $this->_format->beginFile($fh);

            $cacheKeys = array_keys($this->_cache);
            sort($cacheKeys);

            foreach ($cacheKeys as $item) {
                if (is_null($this->_cache[$item])) { // Skip items marked as deleted in the cache
                    continue;
                }

                if (!$this->_format->write($fh, $this->_cache[$item], $item)) {
                    $this->error("could not write to pipe: " . join(' ', error_get_last()));
                }
            }

            $this->_format->endFile($fh);

            if (!@fclose($fh)) {
                $this->error("could not close pipe: " . join(' ', error_get_last()));
            }
        }

        return true;
    }

    /**
     * Ensure that the given item is loaded. It will want to call back to the cacheadd method (see below) to add an item
     * or items to the cache.
     *
     * Always return null since if it's not in the cache, it doesn't exist
     *
     * @param string $itemName Item name
     * @return null|void
     */
    public function load($itemName)
    {
        return null;
    }
}
