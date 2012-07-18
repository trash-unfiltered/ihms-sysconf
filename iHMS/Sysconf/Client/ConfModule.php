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
 * @subpackage  Client_ConfModule
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/***********************************************************************************************************************
 * SYNOPSIS
 *
 * require_once 'iHMS/Sysconf/Client/ConfModule.php';
 * version('1.0');
 * $capb = capb('backup');
 * input('medium', 'foo/bar');
 * $ret=go();
 * if ($ret[0] == 30) {
 *     // Back button pressed (ESC or CANCEL button).
 *     ...
 * }
 * ...
 */

/***********************************************************************************************************************
 * DESCRIPTION
 *
 * This is a module to ease writing ConfModules for the Sysconf configuration management system. It can communicate with
 * a FrontEnd via the sysconf protocol.
 *
 * The design is that each command in the protocol is represented by one function in this module (with the name normalized).
 * Call the function and pass in any parameters you want to follow the command. It will return an array consisting of
 * the numeric return code and the textual return code (if any).
 */

// Ensure that a FrontEnd is running.  It's a little hackish. If SYSCONF_HAS_FRONTEND is set, a FrontEnd is assumed to
// be running. If not, one is started up automatically and stdin and out are connected to it. Note that this statement
// is always run when the module is loaded in the usual way.
if (getenv('SYSCONF_HAS_FRONTEND') === false) {
    pcntl_exec(dirname(dirname(dirname(__DIR__))) . '/frontend', $argv); // TODO review path
}

/**
 * iHMS_Sysconf_Client_ConfModule class
 *
 * @method static array version($version)
 * @method static array capb($capabilities)
 * @method static string stop()
 * @method static array qreset($questionName)
 * @method static array title($title)
 * @method static array input($priority, $questionName)
 * @method static array beginBlock()
 * @method static array endBlock()
 * @method static array go()
 * @method static array set($questionName, $value)
 * @method static array get($questionName)
 * @method static array register($template, $questionName)
 * @method static array unregister($questionName)
 * @method static array clear()
 * @method static array fset($questionName, $flagName, $value)
 * @method static array fget($questionName, $flagName)
 * @method static array subst($questionName, $key, $value)
 * @method static array metaget($questionName, $fieldName)
 * @method static array setTitle($questionName)
 * @method static array info($questionName)
 * @method static array progress($subcommand)
 * @method static array data($template, $item, $value)
 * @method static array purge()
 * @method static array xLoadTemplateFile($templatePath, $owner)
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Client_ConfModule
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */
final class iHMS_Sysconf_Client_ConfModule
{
    /**
     * @var array List of valid commands
     */
    protected static $_validCommands = array(
        'VERSION', 'CAPB', 'STOP', 'QRESET', 'TITLE', 'INPUT', 'BEGINBLOCK', 'ENDBLOCK', 'GO', 'SET', 'GET', 'REGISTER',
        'UNREGISTER', 'CLEAR', 'FSET', 'FGET', 'SUBST', 'PURGE', 'METAGET', 'SETTITLE', 'INFO', 'PROGRESS', 'DATA',
        'XLOADTEMPLATEFILE'
    );

    /**
     * Run the given command
     *
     * @static
     * @throws InvalidArgumentException in case Command is not supported
     * @param string $command Command name to run
     * @param array $args Command arguments
     * @return array
     */
    public static function __callStatic($command, $args)
    {
        // Normalise command name
        $command = strtoupper($command);

        if (!in_array($command, self::$_validCommands)) {
            throw new InvalidArgumentException("Unsupported command `{$command}.\n");
        }

        $command = $command . ' ' . join(' ', $args);

        // Newlines in input can really badly confuse the protocol, so detect and warn
        if (preg_match('/\n/', $command)) {
            fwrite(STDERR, "Warning: Newline present in parameters passed to sysconf\n");
            fwrite(STDERR, "This will probably cause strange things to happend!\n");
        }

        // Print out the command
        print "{$command}\n";

        $ret = fgets(STDIN);
        $ret = chop($ret, "\n");

        $ret = preg_split('/\s/', $ret, 2);

        if ($ret[0] == '1') {
            // Escaped data
            $unescaped = '';
            foreach (preg_split('/(\\.)/', $ret[1], null, PREG_SPLIT_DELIM_CAPTURE) as $val) {
                $unescaped .= preg_replace('/\\\\(.)/e', '"$1" == "n" ? "\n" : "$1"', $val);
            }

            $ret[0] = '0';
            $ret[1] = $unescaped;
        }

        return $ret;
    }
}

/**
 * Compares protocol versions with the confmodule. The version field of the ConfModule is sent to the client
 *
 * @param string $version Version to compare
 * @return array
 */
function version($version = null)
{
    return iHMS_Sysconf_Client_ConfModule::version($version);
}

/**
 * Sets the client_capb field to the confmodules's capabilities, and also sets the capb_backup field of the
 * ConfModules associated FrontEnd if the confmodule can backup. Sends the capb field of the associated FrontEnd
 * to the confmodule.
 *
 * @param string|array capabilitie(s)
 * @return array
 */
function capb($capabilities)
{
    return iHMS_Sysconf_Client_ConfModule::capb($capabilities);
}

/**
 * Tells syconf that you’re done talking to it. Often sysconf  can detect termination of your program and this command
 * is not necessary.
 *
 * @return string
 */
function stop()
{
    return iHMS_Sysconf_Client_ConfModule::stop();
}

/**
 * Reset a question to its default value
 *
 * @param string $questionName Question name
 * @return array
 */
function qreset($questionName)
{
    return iHMS_Sysconf_Client_ConfModule::qreset($questionName);
}

/**
 * Stores the specified title in the associated FrontEnds title field
 *
 * @param string $title Title
 * @return array
 */
function title($title)
{
    return iHMS_Sysconf_Client_ConfModule::title($title);
}

/**
 * Creates an Element to stand for the question that is to be asked and adds it to the list of elements in our
 * associated FrontEnd.
 *
 * @param string $priority Priority
 * @param string $questionName Question name
 * @return array
 */
function input($priority, $questionName)
{
    return iHMS_Sysconf_Client_ConfModule::input($priority, $questionName);
}

/**
 * This is just stub method
 *
 * @return int
 */
function beginBlock()
{
    return iHMS_Sysconf_Client_ConfModule::beginBlock();
}

/**
 * This is just stub method
 *
 * @return int
 */
function endBlock()
{
    return iHMS_Sysconf_Client_ConfModule::endBlock();
}

/**
 * Display items to the user
 *
 * Tells the associated FrontEnd to display items to the user, by calling its go method. That method should return FALSE
 * if the user asked to back up, and TRUE otherwise. If it returns true, then all of the questions that were displayed
 * are added to the seen array
 *
 * @return array
 */
function go()
{
    return iHMS_Sysconf_Client_ConfModule::go();
}

/**
 * This must be passed a question name and a value. It sets the question's value
 *
 * @param string $questionName Question name
 * @param string $value Value
 * @return array
 */
function set($questionName, $value)
{
    return iHMS_Sysconf_Client_ConfModule::set($questionName, $value);
}

/**
 * Returns the value set in the given question
 *
 * This must be passed a question name. It queries the question for the value set in it and returns that to the
 * confmodule
 *
 * @param string $questionName Question name
 * @return array
 */
function get($questionName)
{
    return iHMS_Sysconf_Client_ConfModule::get($questionName);
}

/**
 * This must be passed a question name, a key, and a value. It sets up variable substitutions on the questions
 * description so all instances of the key (wrapped in "${}") are replaced with the value
 *
 * @param string $questionName Question name
 * @param string $key Key
 * @param string $value Value
 * @return array
 */
function subst($questionName, $key, $value)
{
    return iHMS_Sysconf_Client_ConfModule::subst($questionName, $key, $value);
}

/**
 * This should be passed a template name and a question name. Registers a question to use the template
 *
 * @param string $templateName Template name
 * @param string $questionName Question name
 * @return array
 */
function register($templateName, $questionName)
{
    return iHMS_Sysconf_Client_ConfModule::register($templateName, $questionName);
}

/**
 * Pass this a question name, and it will give up ownership of the question, which typically causes it to be
 * removed
 *
 * @param string $questionName Question name
 * @return array
 */
function unregister($questionName)
{
    return iHMS_Sysconf_Client_ConfModule::unregister($questionName);
}

/**
 * Clears out the list of elements in our accociated FrontEnd
 *
 * @return array
 */
function clear()
{
    return iHMS_Sysconf_Client_ConfModule::clear();
}

/**
 * Pass this a question name, a flag name, and a value. It sets the value of the specified flag in the specified
 * question
 *
 * @param string $questionName Question name
 * @param string $flagName Flag name
 * @param string $value Value
 * @return array
 */
function fset($questionName, $flagName, $value)
{
    return iHMS_Sysconf_Client_ConfModule::fset($questionName, $flagName, $value);
}

/**
 * Pass this a question name and a flag name. It returns the value of the specified flag on the question
 *
 * @param string $questionName Question name
 * @param string $flagName Flag name
 * @return array
 */
function fget($questionName, $flagName)
{
    return iHMS_Sysconf_Client_ConfModule::fget($questionName, $flagName);
}

/**
 * This will give up ownership of all questions a confmodule owns
 *
 * @return array
 */
function purge()
{
    return iHMS_Sysconf_Client_ConfModule::purge();
}

/**
 * Returns value of the given field for the given qusestion
 *
 * Pass this a question name and a field name. It returns the value of the specified field of the question.
 *
 * @param string $questionName Question name
 * @param string $fieldName Field name
 * @return array
 */
function metaget($questionName, $fieldName)
{
    return iHMS_Sysconf_Client_ConfModule::metaget($questionName, $fieldName);
}

/**
 * Uses the short description of a question as the title, with automatic i18n
 *
 * @param string $questionName Question name
 * @return array
 */
function setTitle($questionName)
{
    return iHMS_Sysconf_Client_ConfModule::setTitle($questionName);
}

/**
 * Pass this a question name. It displays the given template as an out-of-band informative message. Unlike inputting
 * a note, this doesn't require an acknowledgement from the user, and depending on the frontend it may not even be
 * displayed at all. Frontends should display the info persistently until some other info comes along.
 *
 * With no arguments, this resets the info message to a default value.
 *
 * @param string $questionName Question name
 * @return array
 */
function info($questionName)
{
    return iHMS_Sysconf_Client_ConfModule::info($questionName);
}

/**
 * Progress bar handling
 *
 * Pass this a subcommand name followed by any arguments required by the subcommand, as follows:
 *
 * START
 *  Pass this a minimum value, a maximum value, and a question name. It creates a progress bar with the specified
 *  range and the description of the specified question as the title.
 * SET
 *  Pass this a value. It sets the current position of the progress bar to the specified value.
 * STEP
 *  Pass this an increment. It increments the current position of the progress bar by the specified amount.
 * INFO
 *  Pass this a template name. It displays the specified template as an informational message in the progress bar.
 * STOP
 *  This subcommand takes no arguments. It destroys the progress bar.
 *
 * Note that the frontend's progressSet, progressStep, and progressInfo  functions should return true, unless
 * the progress bar was canceled.
 *
 * @internal string $subcommand Subcommand name
 * @internal mixed $argv Argument(s)
 * @return array
 */
function progress()
{
    return call_user_func_array('iHMS_Sysconf_Client_ConfModule::progress', func_get_args());
}

/**
 * Accept template data from the client, for use on the UI agent side of the passthrough frontend.
 *
 * @param string $template Template
 * @param string $item Item
 * @param string $value Value
 * @return array
 */
function data($template, $item, $value)
{
    return iHMS_Sysconf_Client_ConfModule::data($template, $item, $value);
}

/**
 * Load the given template file
 *
 * @param string $file File to load
 * @param string $owner OPTIONAL Owner
 * @return array
 */
function xLoadTemplateFile($file, $owner = null)
{
    return iHMS_Sysconf_Client_ConfModule::xloadTemplateFile($file, $owner);
}
