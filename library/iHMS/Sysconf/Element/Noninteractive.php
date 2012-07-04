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
require_once 'iHMS/Sysconf/Element.php';

/**
 * iHMS_Sysconf_Element_Noninteractive class
 *
 * This is noninteractive dummy element. When told to display itself, it does nothing
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element_Noninteractive
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Element_Noninteractive extends iHMS_Sysconf_Element
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
}
