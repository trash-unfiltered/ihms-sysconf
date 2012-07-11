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
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Element_Select */
require_once 'iHMS/Sysconf/Element/Select.php';

/**
 * iHMS_Sysconf_Element_Dialog_Select class
 *
 * This is an input element that can display a dialog box with a list of choices on it.
 *
 * @property iHMS_Sysconf_Frontend_Dialog $frontend
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element_Dialog
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Element_Dialog_Select extends iHMS_Sysconf_Element_Select
{

    /**
     * Show an select input element
     */
    public function show()
    {
        // Figure out how much space in the dialog box the prompt will take.
        // The -2 tells {@link iHMS_Sysconf_Frontend_Dialog::makePrompt()) to leave at least two lines to use to
        // display the list.
        list($text, $lines, $columns) = $this->frontend->makePrompt($this->question, -2);

        $screenLines = $this->frontend->getScreenHeight() - $this->frontend->getSpacer();
        $default = $this->translateDefault();
        $params = array();
        $choices = $this->question->choicesSplit();

        // Figure out how many lines of the screen should be used to scroll the list. Look at how much free screen real
        // estate we have after putting the description at the top. If there's too little, the list will need to scroll
        $menuHeight = sizeof($choices);
        if ($lines + sizeof($choices) + 1 >= $screenLines) {
            $menuHeight = $screenLines - $lines - 4;
        }

        $lines = $lines + $menuHeight + $this->frontend->getSpacer();
        $selectSpacer = $this->frontend->getSelectSpacer();

        foreach ($choices as $choice) {
            array_push($params, $choice, '');

            // Choices wider than the description text? (Only needed for whiptail BTW)
            if ($columns < iconv_strlen($choice, 'UTF-8') + $selectSpacer) {
                $columns = iconv_strlen($choice, 'UTF-8') + $selectSpacer;
            }
        }

        if (($dashSeparator = $this->frontend->getDashSeparator()) != '') {
            array_unshift($params, $dashSeparator);
        }

        $params = array_merge(
            array('--default-item', $default, '--menu', $text, $lines, $columns, $menuHeight), $params
        );

        list(, $value) = $this->frontend->showDialog($this->question, $params);

        if (!is_null($value)) {
            $this->_value = $this->translateToC($value);
        } else {
            $default = '';

            if (!is_null($value = $this->question->getValue())) {
                $default = $value;
            }

            $this->_value = $default;
        }
    }
}
