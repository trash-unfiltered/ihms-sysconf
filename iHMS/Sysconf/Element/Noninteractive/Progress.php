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
 * @subpackage  Element_Noninteractive
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Element_Noninteractive */
require_once 'iHMS/Sysconf/Element/Noninteractive.php';

/** @see iHMS_Sysconf_Element_Progress */
require_once 'iHMS/Sysconf/Element/Progress.php';

/**
 * iHMS_Sysconf_Element_Noninteractive_Progress class
 *
 * This is a dummy progress element.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element_Noninteractive
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
class iHMS_Sysconf_Element_Noninteractive_Progress extends iHMS_Sysconf_Element_Progress
{
    /**
     * Is visible element?
     *
     * This type of element is not visible.
     *
     * @return bool True if element is visible, FALSE otherwise
     */
    public function isVisible()
    {
        return false;
    }

    /**
     * Set the value to the default, or blank if no default is available
     *
     * @return void
     */
    public function show()
    {
        $this->_value = (string)$this->question->getValue();
    }

    /**
     * Start progress bar
     *
     * @return void
     */
    public function start()
    {
        // Nothing to do here
    }

    /**
     * Set progress bar value
     *
     * @param int $value
     * @return bool
     */
    public function set($value)
    {
        return true;
    }

    /**
     * Show info about progress bar
     *
     * @param iHMS_Sysconf_Question $question
     * @return bool
     */
    public function info(iHMS_Sysconf_Question $question)
    {
        return true;
    }

    /**
     * Stop progress bar
     *
     * @return void
     */
    public function stop()
    {
        // Nothing to do here
    }
}
