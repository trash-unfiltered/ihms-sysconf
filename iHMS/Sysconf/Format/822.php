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
 * @subpackage  Format
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Format_Interface */
require_once 'iHMS/Sysconf/Format/Interface.php';

/**
 * iHMS_Sysconf_Format_822 class
 *
 * This formats data in a vaguely RFC-822-ish way
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Format
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Format_822 implements iHMS_Sysconf_Format_Interface
{
    /**
     * Read one record from the filehandle, parse it, and return a list with two elements
     *
     * The first is the name of the item that was read, and the second is a structure as required by
     * {@link iHMS_Sysconf_DbDriver_Cache}. Note that the filehandle may contain multiple records, so
     * it must be able to recognize an end-of-record delimiter of some kind and stop reading after it.
     *
     * @param resource $fileHandle
     * @return array|null list of two elements
     */
    public function read($fileHandle)
    {
        $name = null;
        $ret = array(
            'owners' => array(),
            'fields' => array(),
            'variables' => array(),
            'flags' => array(),
        );

        $invar = 0;

        while ($line = fgets($fileHandle)) {
            $line = rtrim($line, "\n");

            if ($line == '') { // blank line is our record delimiter
                break;
            }

            // Process variables
            if ($invar) {
                if (preg_match('/^\s/', $line)) {
                    $line = preg_replace('/^\s+/', '', $line);
                    list($var, $value) = preg_split('/\s*=\s?/', $line, 2);
                    // Replace any \\n by \n
                    $value = preg_replace('/\\\\n/', "\n", $value);
                    $ret['variables'][$var] = $value;
                    continue;
                } else {
                    $invar = 0;
                }
            }

            // Process the main structure
            list($key, $value) = preg_split('/:\s?/', $line, 2);
            $key = strtolower($key);

            if ($key == 'owners') {
                foreach (preg_split('/,\s+/', $value) as $owner) {
                    $ret['owners'][$owner] = true;
                }
            } elseif ($key == 'flags') {
                foreach (preg_split('/,\s+/', $value) as $flag) {
                    $ret['flags'][$flag] = 'true';
                }
            } elseif ($key == 'variables') {
                $invar = 1;
            } elseif ($key == 'name') {
                $name = $value;
            } elseif (!empty($key)) { // Any other entry is treated as flag
                // Replace any \\n by \n
                $value = preg_replace('/\\\\n/', "\n", $value);
                $ret['fields'][$key] = $value;
            }
        } // end-while();

        if (!$name) {
            return null;
        }

        return array($name, $ret);
    } // end-read();

    /**
     * Called at the beginning of each file that is written, before write() is called
     *
     * @param resource $filehandle Filehandle
     * @return void
     */
    public function beginFile($filehandle)
    {
        // No needed
    }

    /**
     * Format a record and write it out to the filehandle. Should include an end-of-record marker of some sort that can
     * be recognized by the parse function.
     *
     * $data is the same structure {@link iHMS_Sysconf_FormatInterface::read()} should return.
     *
     * @param resource $filehandle Filehandle
     * @param array $data Data to write
     * @param string $itemName Item name
     * @return bool TRUE on sucess, FALSE otherwise
     */
    public function write($filehandle, $data, $itemName)
    {
        // Write Name
        if (!@fwrite($filehandle, "Name: $itemName\n")) {
            return false;
        }

        // Write fields
        $fields = array_keys($data['fields']);
        sort($fields);

        foreach ($fields as $field) {
            // Replace any \n by \\n
            $value = preg_replace('/\n/', '\\n', $data['fields'][$field]);

            if (!fwrite($filehandle, ucfirst($field) . ": $value\n")) {
                return false;
            }
        }

        // Write owners
        if ($owners = array_keys($data['owners'])) {
            sort($owners);

            if (!@fwrite($filehandle, 'Owners: ' . join(', ', $owners) . "\n")) {
                return false;
            }
        }

        // Write flags
        if ($flagsKeys = array_keys($data['flags'], 'true')) {
            sort($flagsKeys);

            if (!@fwrite($filehandle, 'Flags: ' . join(', ', $flagsKeys) . "\n")) {
                return false;
            }
        }

        // Write variables
        if ($variablesKeys = array_keys($data['variables'])) {
            if (!@fwrite($filehandle, "Variables:\n")) {
                return false;
            }

            sort($variablesKeys);

            foreach ($variablesKeys as $variableKey) {
                // Replace any \n by \\n
                $value = preg_replace('/\n/', '\\n', $data['variables'][$variableKey]);

                if (!@fwrite($filehandle, " $variableKey = $value\n")) {
                    return false;
                }
            }
        }

        // Write end-of-record marker
        if (!@fwrite($filehandle, "\n")) {
            return false;
        }

        return true;
    }

    /**
     * Called at the end of each file that is written
     *
     * @param resource $filehandle Filehandle
     * @return void
     */
    public function endFile($filehandle)
    {
        // // No needed
    }
}
