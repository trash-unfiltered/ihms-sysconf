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
 * @subpackage  Frontend
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Frontend */
require_once 'iHMS/Sysconf/Frontend.php';

/**
 * iHMS_Sysconf_Frontend_ScreenSize class
 *
 * This class serves as a base for frontends that have an user interface that runs on a resizable tty.
 * The _screenHeight property is always set to the current height of the tty, while the _screenWidth property is always
 * set to its width.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Frontend
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
abstract class iHMS_Sysconf_Frontend_ScreenSize extends iHMS_Sysconf_Frontend
{
    /**
     * @var int The height of the screen
     */
    protected $_screenHeight;

    /**
     * @var int The width of the screen
     */
    protected $_screenWidth;

    /**
     * @var bool Is screen hight guessed to be 25
     */
    protected $_isScreenHeightGuessed = false;

    /**
     * Initialize Frontend
     *
     * @return void
     */
    protected function _init()
    {
        parent::_init();

        // Get current screen size
        $this->resize();

        declare(ticks = 1) ;
        pcntl_signal(SIGWINCH, array($this, 'signalHandler'));
    }

    /**
     * Method is called whenever the tty is resized, and probes to determine the new screen size
     *
     * @return void
     */
    public function resize()
    {
        $ret = shell_exec('stty -a 2>/dev/null');

        if ($ret && preg_match('/rows (\d+); columns (\d+)/s', $ret, $match)) {
            $this->_screenHeight = (int)$match[1];
            $this->_isScreenHeightGuessed = false;
            $this->_screenWidth = (int)$match[2];
        } else {
            $this->_screenHeight = 25;
            $this->_isScreenHeightGuessed = true;
            $this->_screenWidth = 80;
        }
    }

    /**
     * Returns the current screen width
     *
     * @return int Current screen width
     */
    public function getScreenWidth()
    {
        return $this->_screenWidth;
    }

    /**
     * Returns the current screen height
     *
     * @return int Current screen heigh
     */
    public function getScreenHeight()
    {
        return $this->_screenHeight;
    }

    /**
     * Is screen hight guessed?
     *
     * @return bool TRUE if screen hight is guessed, FALSE otherwise
     */
    public function isScreenHeightGuessed()
    {
        return $this->_isScreenHeightGuessed;
    }

    /**
     * Handle the SIGWINCH *nix signal
     *
     * @return void
     */
    public function signalHandler()
    {
        $this->resize();
    }
}
