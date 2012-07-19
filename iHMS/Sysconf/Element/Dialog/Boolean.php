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
 * @subpackage  Element_Dialog
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Element */
require_once 'iHMS/Sysconf/Element.php';

/**
 * iHMS_Sysconf_Element_Dialog_Boolean class
 *
 * This is an input element that can display a dialog box with Yes and No buttons on it.
 *
 * @property iHMS_Sysconf_Frontend_Dialog $frontend
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element_Dialog
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
class iHMS_Sysconf_Element_Dialog_Boolean extends iHMS_Sysconf_Element
{
    /**
     * Show the yesno input element
     *
     * @return void
     */
    public function show()
    {
        $params[] = '--yesno';

        if (($dashSeparator = $this->frontend->getDashSeparator()) != '') {
            $params[] = $dashSeparator;
        }

        // Note 1 is passed in, because we can squeeze on one or more line in a yesno dialog than in other types
        $params = array_merge($params, $this->frontend->makeprompt($this->question, 1));

        if ($this->question->getValue() == 'false') {
            // Put it at the start of the option list, where dialog like it
            array_unshift($params, '--defaultno');
        }

        list($ret) = $this->frontend->showDialog($this->question, $params);

        if (!is_null($ret)) {
            $this->_value = ($ret == 0) ? 'true' : 'false';
        } else {
            $default = '';

            if (!is_null($value = $this->question->getValue())) {
                $default = $value;
            }

            $this->_value = $default;
        }
    }
}
