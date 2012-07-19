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
 * @subpackage  Element
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Element_Select */
require_once 'iHMS/Sysconf/Element/Select.php';

/**
 * iHMS_Sysconf_Element_Multiselect abstract class
 *
 * Base class for multiselect input element.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
abstract class iHMS_Sysconf_Element_Multiselect extends iHMS_Sysconf_Element_Select
{
    /**
     * Given a set of values, reorders them to be in the same order as the choices field of the question's template,
     * and returns them
     *
     * @param array $values Value to be ordered
     * @return array
     */
    public function orderValues($values)
    {
        // Make sure that the choies are in the C locale, like the values are
        $this->question->getTemplate()->setI18n(false);
        $ret = array_intersect($this->question->choicesSplit(), $values); // TODO check behavior
        $this->question->getTemplate()->setI18n(true);

        return $ret;
    }

    /**
     * Is visible element?
     *
     * Unlike select lists, multiselect questions are visible if there is just one choice
     *
     * @return bool TRUE if element is visible, FALSE otherwise
     */
    public function isVisible()
    {
        $choices = $this->question->choicesSplit();
        return !empty($choices);
    }

    /**
     * This method returns default value(s), in the user's language, suitable for displaying to the user. Defaults are
     * stored internally in the C locale; this method does any necessary translation to the current locale.
     *
     * @return array
     */
    public function translateDefault()
    {
        // Need both the translated and the non-translated choices
        $choices = $this->question->choicesSplit();
        $this->question->getTemplate()->setI18n(false);
        $choicesC = $this->question->choicesSplit();
        $this->question->getTemplate()->setI18n(true);

        $ret = array();

        // Translate each default
        foreach ($this->question->valueSplit() as $cDefault) {
            for ($x = 0; $x < sizeof($choices); $x++) {
                if ($choicesC[$x] == $cDefault) {
                    $ret[] = $choices[$x];
                }
            }
        }

        return $ret;
    }
}
