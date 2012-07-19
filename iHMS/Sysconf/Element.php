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

/**
 * iHMS_Sysconf_Element_Abstract class
 *
 * This is the base object on which many different types of input elements are built. Each element represents one user
 * interface element in a Frontend.
 *
 * @property iHMS_Sysconf_Frontend $frontend
 * @property iHMS_Sysconf_Question $question
 * @property iHMS_Sysconf_Question $markseen
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
abstract class iHMS_Sysconf_Element
{
    /**
     * @var string The value the user entered into the element
     */
    protected $_value = '';

    /**
     * Constructor
     *
     * Create a new object of this class. Optionally, you can pass in named parameters that specify the values of
     * any fields in the class
     *
     * @param array $fields Named parameters
     */
    public function __construct(array $fields = array())
    {
        foreach ($fields as $field => $value) {
            $this->{$field} = $value;
        }
    }

    /**
     * Is visible element?
     *
     * Returns TRUE if an element is of a type that is displayed to the user. This is used to let confmodules know if
     * the elements they have caused to be displayed are really going to be displayed, or not, so they can avoid loops
     * and other nastiness.
     *
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }

    /**
     * Show the element
     *
     * Causes the element to be displayed, allows the user to interact with it. Typically causes the value field to be
     * set.
     *
     * @return void
     */
    public function show()
    {
    }

    /**
     * The value the user entered into the element
     *
     * @return string
     */
    public function getValue()
    {
        return $this->_value;
    }
}
