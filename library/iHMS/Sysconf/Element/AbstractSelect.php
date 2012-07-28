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

namespace iHMS\Sysconf\Element;

use iHMS\Sysconf\Element;
use iHMS\Sysconf\Log;

/**
 * AbstractSelect class
 *
 * Base class for input select element.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
abstract class AbstractSelect extends Element
{
    /**
     * Is visible element?
     *
     * Select elements are not really visible if there are less than two choices for them.
     *
     * @return bool TRUE if element is visible, FALSE otherwise
     */
    public function isVisible()
    {
        $choices = $this->question->choicesSplit();

        if (sizeof($choices) > 1) {
            return true;
        } else {
            $countChoices = sizeof($choices);
            Log::debug(
                'developer',
                'Not displaying select list ' . $this->question->getName() . ' with ' . ($countChoices) . ' choice' .
                    (($countChoices == 0) ? 's' : '')
            );

            return false;
        }
    }

    /**
     * This method returns a default value, in the user's language, suitable for displaying to the user.
     * Defaults are stored internally in the C locale; this method does any necessary translation to the current locale
     *
     * @return string
     */
    public function translateDefault()
    {
        $choices = $this->question->choicesSplit();
        $this->question->getTemplate()->setI18n(false);
        $choicesC = $this->question->choicesSplit();
        $this->question->getTemplate()->setI18n(true);

        // Get the C default
        $cDefault = '';
        if ($value = $this->question->getValue()) {
            $cDefault = $value;
        }

        // Translate it
        for ($x = 0; $x < sizeof($choices); $x++) {
            if ($choicesC[$x] == $cDefault) {
                return $choices[$x];
            }
        }

        // If it's not in the list of choice, just ignore it
        return '';
    }

    /**
     * Pass a value in the current locale in to this function, and it will look it up in the list of choices, convert it
     * back to the C locale, and return it
     *
     * @param string $value Value to translate
     * @return string
     */
    public function translateToC($value)
    {
        // Need both the translated and the non-translated choices
        $choices = $this->question->choicesSplit();
        $this->question->getTemplate()->setI18n(false);
        $choicesC = $this->question->choicesSplit();
        $this->question->getTemplate()->setI18n(true);

        for ($x = 0; $x < sizeof($choices); $x++) {
            if ($choices[$x] == $value) {
                return $choicesC[$x];
            }
        }

        Log::debug(
            'developer',
            sprintf("Input value \"%s\" not found in C choices! This should never happend. Perhap the template were incorrectly localized.", $value)
        );

        return '';
    }
}
