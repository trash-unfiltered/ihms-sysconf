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

use iHMS\Sysconf\DbDriver\Cache;
use iHMS\Sysconf\Format\FormatInterface;
use iHMS\Sysconf\Log;

/**
 * File class
 *
 * This is a sysconf database driver that uses a single flat file for storing the database. It uses more memory than
 * most other drivers, has a slower startup time (it reads the whole file at startup), and is very fast thereafter
 * until shutdown time (when it writes the whole file out). Of course, the resulting single file is very handy to manage.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  DbDriver
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
class File extends Cache
{
    /**
     * @var string database filename
     */
    protected $_filename = null;

    /**
     * @var int filemode
     */
    protected $_mode = 600;

    /**
     * @var string|FormatInterface
     */
    protected $_format = 'Format822';

    /**
     * @var resource File handle
     */
    protected $_fh = null;

    /**
     * Intiialize the database driver
     *
     * @return null|void
     */
    protected function _init()
    {
        if ($this->_mode) {
            $this->_mode = $mode = sprintf('%s', octdec($this->_mode));
        }

        try {
            @\Zend_Loader::loadClass($formatClass = '\\iHMS\\Sysconf\\Format\\' . $this->_format);
        } catch (\Zend_Exception $e) {
            $this->error("error setting up format object {$this->_format}: " . $e->getMessage());
        }

        $this->_format = new $formatClass();

        if (!$this->_filename) {
            $this->error('no filename specified');
        }

        Log::debug("db {$this->_name}", "started; filename is {$this->_filename}");

        // Make sure that the file exists, and set the mode too
        if (!file_exists($this->_filename)) {
            Log::debug("db {$this->_name}", "created inexistent {$this->_filename} file with filemode {$this->_mode}");

            $this->_backup = false; // No need to backup for new file

            $dirname = pathinfo($this->_filename, PATHINFO_DIRNAME);

            if (!is_dir($dirname)) {
                if (!@mkdir($dirname, 0755, true)) {
                    $this->error("could not create {$dirname}: " . join(' ', error_get_last()));
                }
            }

            if (@touch($this->_filename)) {
                if (!@chmod($this->_filename, $this->_mode)) {
                    $this->error("could not set mode for {$this->_filename}: " . join(' ', error_get_last()));
                }
            } else {
                $this->error("could not create {$this->_filename}: " . join(' ', error_get_last()));
            }
        }

        $implicitReadonly = false;

        if (!$this->_readonly) {
            // Open file for read but also with write access so exclusive lock can be done portably
            if ($this->_fh = @fopen($this->_filename, 'r+')) {
                // Now lock the file with flock locking. No wait on locks, just error out
                if (!flock($this->_fh, LOCK_EX | LOCK_NB)) {
                    $this->error("{$this->_filename} is locked by another process");
                }
            } else {
                // Fallthrough to readonly mode
                $implicitReadonly = true;
            }
        }

        if ($this->_readonly || $implicitReadonly) {
            if (!$this->_fh = @fopen($this->_filename, 'r')) {
                $this->error("could not open {$this->_filename}: " . join(' ', error_get_last()));
                return; // Always abort, even if not throwing fatal error
            }
        }

        Log::debug("db {$this->_name}", 'loading database');

        // Now read in the whole file using the Format object
        while (!feof($this->_fh)) {
            if ($ret = $this->_format->read($this->_fh)) {
                list($item, $cache) = $ret;
                $this->_cache[$item] = $cache;
            }
        }

        // Close only if we are not keeping a lock
        if ($this->_readonly || $implicitReadonly) {
            fclose($this->_fh);
        }
    }

    /**
     * Save the entire cache out to the file, then close the file
     *
     * @return bool TRUE on sucess, FALSE otherwise
     */
    public function shutdown()
    {
        if ($this->_readonly) {
            return false;
        }

        if (array_intersect_key($this->_cache, $this->_dirty)) {
            Log::debug("db {$this->_name}", 'saving database');
        } else {
            Log::debug("db {$this->_name}", 'no database changes, no saving');

            //if(is_resource($this->_fh)) {
            //    flock($this->_fh, LOCK_UN);
            //    fclose($this->_fh);
            //}
            $this->_fh = null;

            return true;
        }

        // Write out the file to -new, locking it as we go
        if (!$fh = @fopen($this->_filename . '-new', 'w')) {
            $this->error("could not write the {$this->_filename}-new file: " . join(' ', error_get_last()));
        } elseif (!@chmod($this->_filename . '-new', $this->_mode)) {
            $this->error("could not set mode of the {$this->_filename}-new file: " . join(' ', error_get_last()));
        }

        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            $this->error("{$this->_filename}-new is locked by another process");
        }

        $this->_format->beginFile($fh);

        $cacheKeys = array_keys($this->_cache);
        sort($cacheKeys);

        foreach ($cacheKeys as $item) {
            if (is_null($this->_cache[$item])) { // Skip items marked as deleted in the cache
                continue;
            }

            if (!$this->_format->write($fh, $this->_cache[$item], $item)) {
                $this->error("could not write the {$this->_filename}-new file");
            }
        }

        $this->_format->endFile($fh);

        // Ensure -new is flushed
        if (!fflush($fh)) {
            $this->error("could not flush the {$this->_filename}-new file");
        }

        // Now rename the old file to -old (if doing backups), and put -new in its place.
        if (file_exists($this->_filename) && $this->_backup) {
            if (!@rename($this->_filename, $this->_filename . '-old')) {
                Log::debug("db {$this->_name}", "rename failed: " . join(' ', error_get_last()));
            }
        }

        if (!@rename($this->_filename . '-new', $this->_filename)) {
            $this->error("rename failed: " . join(' ', error_get_last()));
        }

        // TODO check unlock issue here
        // Now drop the lock on -old (the lock on -new will be removed when this function returns and $fh goes out of
        // scope

        flock($this->_fh, LOCK_UN);
        fclose($this->_fh);

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
