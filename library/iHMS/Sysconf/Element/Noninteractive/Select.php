<?php
/**
 * iHMS - internet Hosting Management System
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
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Element */
require_once 'iHMS/Sysconf/Element/Noninteractive.php';

/**
 * iHMS_Sysconf_Element_Noninteractive_Select class
 *
 * This is a dummy elect element.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element_Noninteractive
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Element_Noninteractive_Select extends iHMS_Sysconf_Element_Noninteractive
{
    /**
     * The show method does not display anything. However, if the value of the Question associated with it is not set,
     * or is not one of the available choices, then it will be set to the first item in the select list. This is for
     * consistency with the behavior of other select Elements
     */
    public function show()
    {
        // Make sure the choices list in in the C locale, not localized
        $this->question->getTemplate()->setI18n(false);
        $choices = $this->question->choicesSplit();
        $this->question->getTemplate()->setI18n(true);
        $value = $this->question->choicesSplit();

        if (!$value) {
            $value = '';
        }

        $inlist = 0;
        if (in_array($value, $choices)) {
            $inlist = 1;
        }

        if (!$inlist) {
            if (!empty($choices)) {
                $this->_value = $choices[0];
            } else {
                $this->_value = '';
            }
        } else {
            $this->_value = $value;
        }
    }
}

