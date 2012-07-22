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

namespace iHMS\Sysconf\Element\Dialog;

use iHMS\Sysconf\Frontend\Dialog;
use iHMS\Sysconf\Element;

/**
 * iHMS_Sysconf_Element_Dialog_String class
 *
 * This is an input element that can display a dialog box with a text input field on it.
 *
 * @property Dialog $frontend
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element_Dialog
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
class String extends Element
{
    /**
     * Show a string input element
     *
     * @return void
     */
    public function show()
    {
        list($text, $lines, $columns) = $this->frontend->makePrompt($this->question);

        $default = '';
        if (!is_null($value = $this->question->getValue())) {
            $default = $value;
        }

        $params[] = '--inputbox';

        if (($dashSeparator = $this->frontend->getDashSeparator()) != '') {
            $params[] = $dashSeparator;
        }

        array_push($params, $text, $lines + $this->frontend->getSpacer(), $columns, $default);

        list(, $value) = $this->frontend->showDialog($this->question, $params);

        if (!is_null($value)) {
            $this->_value = $value;
        } else {
            $default = '';
            if (!is_null($value = $this->question->getValue())) {
                $default = $value;
            }

            $this->_value = $default;
        }
    }
}
