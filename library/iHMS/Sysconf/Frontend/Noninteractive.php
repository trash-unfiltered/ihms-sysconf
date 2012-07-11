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
 * @subpackage  Frontend_Noninteractive
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Frontend */
require_once 'iHMS/Sysconf/Frontend.php';

/**
 * iHMS_Sysconf_Frontend_Noninteractive class
 *
 * This FrontEnd is completly non-interactive.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Frontend_Noninteractive
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Frontend_Noninteractive extends iHMS_Sysconf_Frontend
{
    /**
     * Initialize the frontend
     *
     * This method is called by the constructor.
     *
     * @return void
     */
    protected function _init()
    {
        parent::_init();
        $this->_needTTY = false;
    }
}
