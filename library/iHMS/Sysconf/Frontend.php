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

/** @see iHMS_Sysconf_Log */
require_once 'iHMS/Sysconf/Log.php';

/**
 * iHMS_Sysconf_Frontend_Abstract class
 *
 * This is the base of the Frontend classes. Each FrontEnd presents a user interface of some kind to the user, and
 * handles generating and communicating with elements to form that FrontEnd.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Frontend
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
abstract class iHMS_Sysconf_Frontend
{
    /**
     * Array that contains all the elements that the FrontEnd needs to show to the user
     *
     * @var iHMS_Sysconf_Element[]
     */
    protected $_elements = array();

    /**
     * @var bool Is this an interactive FrontEnd?
     */
    protected $_interactive = false;

    /**
     * @var string Holds any special capabilities the frontend supports (for now, only backup capability)
     */
    protected $_capb = '';

    /**
     * @var string The title of the FrontEnd
     */
    protected $_title = '';

    /**
     * The title last explicitly requested for the FrontEnd. May be temporarily overridden by another title,
     * e.g. for progress bars
     *
     * @var string
     */
    protected $_requestedTitle = '';

    /**
     * A question containing an informative message to be displayed, without requiring any acknowledgement from the user.
     * Frontends may choose not to implement this. If they do implement it, they should display the info persistently
     * until some other info comes along
     *
     * @var iHMS_Sysconf_Question
     */
    protected $_info = null;

    /**
     * A flag that elements can set when they are displayed, to tell the frontend that the user has indicated they want
     * to back up
     *
     * @var bool
     */
    protected $_backup = false;

    /**
     * @var bool This will be set to TRUE if the confmodule states it has the backup capability
     */
    protected $_capbBackup = false;

    /**
     * The element used for the currently running progress bar, if any
     *
     * @var iHMS_Sysconf_Element_Progress
     */
    protected $_progressBar;

    /**
     * Set to true if the frontend needs a tty. Defaults to true.
     *
     * Note that setting this to true does not ensure that the frontend actually gets a tty. It does let sysconf
     * abort in cases where the selected frontend cannot work due to it being impossible to get a tty for it.
     *
     * @var bool
     */
    protected $_needTTY = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize the frontend
        $this->_init();
    }

    /**
     * Initialize the frontend
     *
     * This method is called by the constructor.
     *
     * @return void
     */
    protected function _init()
    {
    }

    /**
     * What type of elements this frontend uses
     *
     * Defaults to returning the same name as the frontend, but tightly-linked frontends might want to share elements;
     * if so, one can override this with a method that returns the name of the other.
     *
     * Note: This method is callable in both static and object context
     *
     * @return string Element type
     */
    public static function getElementType()
    {
        preg_match('/iHMS_Sysconf_Frontend_(.*)$/s', get_called_class(), $type);
        return $type[1];
    }

    /**
     * Creates an Element of the type used by this FrontEnd. Pass in the question that will be bound to the Element
     *
     * It returns the generated Element, or false if it was unable to make an element of the given type. This may be
     * called as either a class or an object method.
     *
     * Normally, it outputs debug codes if creating the Element fails. If failure is expected, a second parameter can
     * be passed with a true value to turn off those debug messages.
     *
     * Note: This method is callable in both static and object context
     *
     * @param iHMS_Sysconf_Question $question Question bound to the element.
     * @param bool $noDebug Whether or not debug information must be show
     * @return iHMS_Sysconf_Element
     */
    public static function makeElement($question, $noDebug = false)
    {
        // Figure out what type of frontend this is (eg. Dialog_Boolean)
        $type = self::getElementType() . '_' . ucfirst($question->type);
        $type = preg_replace('/_$/', '', $type); // In case the question has no type..

        self::_loadElementClass($type, $noDebug);

        $element = "iHMS_Sysconf_Element_{$type}";
        $element = new $element(array('question' => $question));

        // TODO must return false if it was unable to create element of the given type

        return $element;
    }

    /**
     * Add an element to the list to be displayed to the user
     *
     * Just pass the element to add. Note that it detects multiple Elements that point to the same Question and only
     * adds the first.
     *
     * @param iHMS_Sysconf_Element $element
     * @return void
     */
    public function add($element)
    {
        if (!in_array($element, $this->_elements, true)) {
            $element->frontend = $this;
            $this->_elements[] = $element;
        }
    }

    /**
     * Display accumulated element to the user
     *
     * This will normally return TRUE, but if the user indicates they want to back up, it returns FALSE
     *
     * @return bool
     */
    public function go()
    {
        $this->_backup = '';

        foreach ($this->_elements as $element) {
            $element->show();

            if ($this->_backup && $this->_capbBackup) {
                return false;
            }
        }

        return true;
    }

    /**
     * Start progress bar
     *
     * @param int $min
     * @param int $max
     * @param iHMS_Sysconf_Question $question Question
     */
    public function progressStart($min, $max, $question)
    {
        $type = $this->getElementType() . '_Progress';
        self::_loadElementClass($type);

        $element = "iHMS_Sysconf_Element_{$type}";

        /** @var $element iHMS_Sysconf_Element_Progress */
        $element = new $element(array('question' => $question));

        $element->frontend = $this;
        $element->progressMin = $min;
        $element->progressMax = $max;
        $element->progressCur = $min;

        $element->start();

        $this->_progressBar = $element;
    }

    /**
     * Set the value of a progress bar, within the minimum and maximum values passed when starting it.
     *
     * Returns true unless the progress bar was canceled by the user. Cancelation is indicated by the progress bar
     * object's set method returning false
     *
     * @param string $value Value of progress bar
     * @return bool
     */
    public function progressSet($value)
    {
        return $this->_progressBar->set($value);
    }

    /**
     * Step a progress bar by the given amount
     *
     * Returns true unless the progress bar was canceled by the user. Cancelation is indicated by the progress bar
     * object's set method returning false
     *
     * @param int $inc
     * @return bool
     */
    public function progressStep($inc)
    {
        return $this->progressSet($this->_progressBar->progressCur + $inc);
    }

    /**
     * Set an informational message to be displayed along with the progress bar
     *
     * Returns true unless the progress bar was canceled by the user. Cancelation is indicated by the progress bar
     * object's info method returning false
     *
     * @param iHMS_Sysconf_Question $question Question
     * @return bool
     */
    public function progressInfo(iHMS_Sysconf_Question $question)
    {
        return $this->_progressBar->info($question);
    }

    /**
     * Tear down a progress bar
     *
     * @return void
     */
    public function progressStop()
    {
        $this->_progressBar->stop();
        $this->_progressBar = null;
    }

    /**
     * Clear out the accumulated elements
     *
     * @return void
     */
    public function clear()
    {
        $this->_elements = array();
    }

    /**
     * Is interactive frontend?
     *
     * @return bool TRUE if frontend is interactive, FALSE otherwise
     */
    public function isInteractive()
    {
        return $this->_interactive;
    }

    /**
     * Returns elements
     *
     * @return iHMS_Sysconf_Element[]
     */
    public function getElements()
    {
        return $this->_elements;
    }

    /**
     * Returns requested title
     *
     * @return string
     */
    public function getRequestedTitle()
    {
        return $this->_requestedTitle;
    }

    /**
     * Sets requested title
     *
     * @param string $requestedTitle RequestedTitle
     * @return void
     */
    public function setRequestedTitle($requestedTitle)
    {
        $this->_requestedTitle = $requestedTitle;
    }

    /**
     * Sets the title field to a default
     *
     * @param string $title Name of the module that is being configured
     * @return void
     */
    public function defaultTitle($title)
    {
        $this->_title = sprintf('Configuring %s', $title);
        $this->_requestedTitle = $this->_title;
    }

    /**
     * Returns title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->_title;
    }

    /**
     * Sets title
     *
     * @param string $title
     * @return void
     */
    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * Sets info
     *
     * @param iHMS_Sysconf_Question $info
     */
    public function setInfo(iHMS_Sysconf_Question $info = null)
    {
        $this->_info = $info;
    }

    /**
     * Returns capabilitie supported by frontend
     *
     * @return string that hold frontend capabilities
     */
    public function getCapb()
    {
        return $this->_capb;
    }

    /**
     * Set backup capability
     *
     * @param bool $backup Whether confmodule has the backup capability
     */
    public function setCapbBackup($backup)
    {
        $this->_capbBackup = $backup;
    }

    /**
     * This method should be called before a frontend is shut down
     *
     * @return void
     */
    public function shutdown()
    {

    }

    /**
     * Load given element class
     *
     * @static
     * @param string $type Element type
     * @param bool $noDebug Whether or not debug information must be show
     * @return null
     */
    protected static function _loadElementClass($type, $noDebug = false)
    {
        static $noUse = array();

        if (!class_exists("iHMS_Sysconf_Element_{$type}")) {
            if (isset($noUse[$type])) return;

            try {
                /** @see Zend_Loader */
                require_once 'Zend/Loader.php';
                @Zend_Loader::loadClass("iHMS_Sysconf_Element_{$type}");
            } catch (Exception $e) {
                if (!$noDebug) {
                    iHMS_Sysconf_Log::warn(sprintf('Unable to load iHMS_Sysconf_Element_%s. Failed because %s', $type, $e->getMessage()));
                }

                $noUse[$type] = 1;
                return;
            }
        }
    }
}
