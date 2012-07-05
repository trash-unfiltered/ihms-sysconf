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
 * @subpackage  Element_Dialog
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Element_Progress */
require_once 'iHMS/Sysconf/Element/Progress.php';

/**
 * iHMS_Sysconf_Element_Dialog_Progress class
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
class iHMS_Sysconf_Element_Dialog_Progress extends iHMS_Sysconf_Element_Progress
{
    /**
     * @var iHMS_Sysconf_Question
     */
    protected $_info = null;

    /**
     * @var int
     */
    protected $_lines = 0;

    /**
     * @var int
     */
    protected $_columns = 0;

    /**
     * Start progress bar
     *
     * @return void
     */
    public function start()
    {
        // Use the short description as the window title
        $this->frontend->setTitle($this->question->getDescription());

        if ($this->_info) {
            list($text, $lines, $columns) = $this->frontend->sizeText($this->_info->getDescription());
        } else {
            // Make sure dialog allocates a bit of extra space, to allow for later PROGRESS INFO commands
            list($text, $lines, $columns) = $this->frontend->sizeText(' ');
        }

        // Force progress bar to full available width, to avoid windows flashing
        if ($this->frontend->getScreenWidth() - $this->frontend->getColumnSpacer() > $columns) {
            $columns = $this->frontend->getScreenWidth() - $this->frontend->getColumnSpacer();
        }

        $params[] = '--gauge';

        if ($dashSeparator = $this->frontend->getDashSeparator()) {
            $params[] = $dashSeparator;
        }

        array_push($params, $text, $lines + $this->frontend->getSpacer(), $columns, $this->_percent());

        $this->frontend->startDialog($this->question, true, $params);

        $this->_lines = $lines;
        $this->_columns = $columns;
    }

    /**
     * Set progress bar value
     *
     * @param int $value Value
     * @return bool
     */
    public function set($value)
    {
        $this->progressCur = $value;
        $this->_communicate($this->_percent() . "\n");

        return true;
    }

    /**
     * Set informational message to be displayed along with the progress bar
     *
     * @param iHMS_Sysconf_Question $question
     * @return bool
     */
    public function info(iHMS_Sysconf_Question $question)
    {
        $this->_info = $question;
        list($text, $lines, $columns) = $this->frontend->sizeText($question->getDescription());

        if ($lines > $this->_lines or $columns > $this->_columns) {
            // Start a new, bigger dialog if this won't fit
            $this->stop();
            $this->start();
        }

        // TODO: escape the "XXX" marker required by dialog somehow?

        // The line immediately following the marker shhould be a new percentage, but whiptail (as of 0.51.6-17) looks
        // for a percentage in the wrong buffer and fails to refrech the display as result.
        // To work around this bug, we give it the current percentage again afterwards to force a refresh.
        $this->_communicate(sprintf("XXX\n%d\n%s\nXXX\n%d\n", $this->_percent(), $text, $this->_percent()));

        return true;
    }

    /**
     * Stop progress bar
     *
     * @return void
     */
    public function stop()
    {
        $this->frontend->waitDialog();
        $this->frontend->setTitle($this->frontend->getRequestedTitle());
    }

    /**
     * Returns percent
     *
     * @return int
     */
    protected function _percent()
    {
        return (($this->progressCur - $this->progressMin) * 100 / ($this->progressMax - $this->progressMin));
    }

    /**
     * Communicate with dialog
     *
     * @param string $data data to sent to dialog
     * @return void
     */
    protected function _communicate($data)
    {
        fwrite($this->frontend->getChildIn(), $data);
    }
}
