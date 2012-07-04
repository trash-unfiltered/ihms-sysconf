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
 * iHMS_Sysconf_Element_ProgressInterface abstract class
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
abstract class iHMS_Sysconf_Element_Progress extends iHMS_Sysconf_Element
{
    /**
     * @var int Minimum value
     */
    public $progressMin;

    /**
     * @var int Max value
     */
    public $progressMax;

    /**
     * @var int Current value
     */
    public $progressCur;

    /**
     * Start progress bar
     *
     * @abstract
     * @return void
     */
    abstract public function start();

    /**
     * Set progress bar value
     *
     * @abstract
     * @param int $value Value
     * @return bool
     */
    abstract public function set($value);

    /**
     * Set informational message to be displayed along with the progress bar
     *
     * @abstract
     * @param iHMS_Sysconf_Question $question Question
     * @return bool
     */
    abstract public function info(iHMS_Sysconf_Question $question);

    /**
     * Stop progress bar
     *
     * @abstract
     * @return void
     */
    abstract public function stop();
}