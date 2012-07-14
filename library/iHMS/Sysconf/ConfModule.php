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
 * @subpackage  ConfModule
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** iHMS_Sysconf_Log */
require_once 'iHMS/Sysconf/Log.php';

/**
 * iHMS_Sysconf_ConfModule class
 *
 * This is a configuration module communication class for the Sysconf configuration management system. It can launch a
 * configuration module script (hereafter called a "confmodule") and communicate with it. Each instance of a ConfModule
 * is connected to a separate, running confmodule.
 *
 * There are a number of methods that are called in response to commands from the client. Each has the same name as the
 * command, with "command" prepended, and is fed in the parameters given after the command (split on whitespace), and
 * whatever it returns is passed back to the configuration module. Each of them are described below.
 *
 * @property string _confmodule
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  ConfModule
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_ConfModule
{
    /**
     * @var iHMS_Sysconf_Frontend The frontend object that is used to interact with the user
     */
    protected $_frontend = null;

    /**
     * @var string The protocol version spoken
     */
    protected $_version = '1.0';

    /**
     * @var resource The process of the confmodule that is running and talking to this object, if any
     */
    protected $_process = null;

    /**
     * @var resource Write to this handle are sent to the confmodule
     */
    protected $_writeHandle = null;

    /**
     * @var resource Reads from this handle read from the confmodule
     */
    protected $_readHandle = null;

    /**
     * Set if we have caught a SIGPIPE signal. If it is set, the value of the field should be returned rather than the
     * normal exit code
     *
     * @var int
     */
    public $_caughtSigpipe = null;

    /**
     * @var array Hold the capabilities the confmodule reports
     */
    protected $_clientCapb = array();

    /**
     * If set, it will hold a list of all questions that have ever been shown to the user in this confmodule run
     *
     * @var array
     */
    protected $_seen = array();

    /**
     * If set, it will hold a list of named of question that are  "busy" -- in the process of being shown, that cannot
     * be unregistered right now
     *
     * @var array
     */
    protected $_busy = array();

    /**
     * @var string Owner
     */
    protected $_owner = 'unknown';

    /**
     * @var bool Whether or not user asked for backup
     */
    protected $_backedUp = false;

    /**
     * @var int Confmodule exit code
     */
    protected $_exitCode;

    /**
     * @var array All numeric result codes that are used
     */
    protected $_codes = array(
        'success' => 0,
        'escaped_data' => 1,
        'badparams' => 10,
        'syntaxerror' => 20,
        'input_invisible' => 30,
        'version_bad' => 30,
        'go_back' => 30,
        'progresscancel' => 30,
        'internalerror' => 100,
    );

    /**
     * Constructor
     *
     * @param array $params Named parameters
     */
    public function __construct(array $params = array())
    {
        $this->_setParams($params);

        // If the frontend thought the client confmodule could backup
        // (eg, because it was dealing earlier with a confmodule that could),
        // tell it otherwise.
        $this->_frontend->setCapbBackup(false); // TODO Check

        putenv('SYSCONF_HAS_FRONTEND=1');
    }

    /**
     * Set named parameters
     *
     * @param array $params
     * @return void
     */
    protected function _setParams(array $params)
    {
        foreach ($params as $param => $value) {
            $this->{'_' . $param} = $value;
        }
    }

    /**
     * Sets owner
     *
     * @param string $owner Owner
     */
    public function setOwner($owner)
    {
        $this->_owner = $owner;
    }

    /**
     * Returns owner
     *
     * @return string Owner
     */
    public function getOwner()
    {
        return $this->_owner;
    }

    /**
     * Returns confmodule exit code
     *
     * @return int Exit code
     */
    public function getExitCode()
    {
        return $this->_exitCode;
    }

    /**
     * Startup confmodule
     *
     * Pass this the name of a confmodule program, and it is started up. Any further options are parameters to pass to
     * the confmodule. You generally need to do this before trying to use any of the rest of this object. The
     * alternative is to launch a confmodule manually, and connect the read_handle and write_handle fields of this
     * object to it.
     *
     * @throws Exception in case confmodule cannot be started
     * @param string $confmodule confmodule
     * @internal mixed $params,... Parameter to pass to the confmodule
     */
    public function startup($confmodule)
    {
        // There is an implicit clearing of any previously pending questions when a new confmodule is run
        $this->_frontend->clear();
        $this->_busy = array();

        $this->_confmodule = $confmodule;
        $args = func_get_args();

        iHMS_Sysconf_Log::debug('developer', 'starting ' . join(' ', $args));

        $this->_process = @proc_open('/usr/bin/env php ' . join(' ', $args), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w')), $pipes);

        if (!is_resource($this->_process)) {
            throw new Exception($php_errormsg);
        }

        $this->_writeHandle = $pipes[0];
        $this->_readHandle = $pipes[1];

        // Catch sigpipes so they don't kill us, and return 128 for them
        declare(ticks = 1) ;
        $thisRef = $this;
        pcntl_signal(SIGPIPE, function() use($thisRef)
        {
            $thisRef->_caughtSigpipe = 128;
        });
    }

    /**
     * Read one command from the confmodule, process it, and respond to it. Returns TRUE unless there were no more
     * commands to read. This is typically called in a loop. It in turn calls various command* methods.
     *
     * @return bool TRUE as long we have commands to read, FALSE otherwise
     */
    public function communicate()
    {
        if (($_ = fgets($this->_readHandle)) === false) {
            return $this->finish();
        }

        $_ = chop($_, "\n");
        $ret = $this->processCommand($_);

        fwrite($this->_writeHandle, "{$ret}\n");

        if (!strlen($ret)) {
            return false;
        }

        return true;
    }

    /**
     * Escape backslashes and newlines for output via the sysconf protocol
     *
     * @param string $text Text to escape
     * @return string
     */
    public function escape($text)
    {
        return addcslashes($text, "\\\n");
    }

    /**
     * Unescape text received via the sysconf protocol, and split by unescaped whitespace
     *
     * @param string $text Text to unescape
     * @return array
     */
    public function unescapSplit($text)
    {
        $words = array();
        $word = '';

        foreach (preg_split('/(\\\\.|\s+)/', $text, null, PREG_SPLIT_DELIM_CAPTURE) as $chunk) {
            if ($chunk == '\n') {
                $word .= "\n";
            } elseif (preg_match('/^\\\\(.)$/', $chunk, $m)) {
                $word .= $m[1];
            } elseif (preg_match('/^\s+$/', $chunk)) { // TODO (PO) ctype_space()
                $words[] = $word;
                $word = '';
            } else {
                $word .= $chunk;
            }
        }

        if ($word != '') {
            $words[] = $word;
        }

        return $words;
    }

    /**
     * Pass in a raw command, and it will be processed and handled
     *
     * @param string $rawCommand Raw command to process
     * @return bool|string
     */
    public function processCommand($rawCommand)
    {
        iHMS_Sysconf_Log::debug('developer', "<-- {$rawCommand}");

        if (preg_match('/^\s*#/', $rawCommand)) { // Skip blank lines, comments.
            return 1;
        }

        $rawCommand = chop($rawCommand, "\n");

        if (in_array('escape', $this->_clientCapb)) {
            $params = $this->unescapSplit($rawCommand);
        } else {
            $params = explode(' ', $rawCommand);
        }

        list($command, $params) = array($params[0], array_slice($params, 1));

        $command = strtolower($command);

        // This command cannot be handle by a method
        if ($command == 'stop') {
            return $this->finish();
        }

        // Make sure that the command is valid
        if (!method_exists($this, "command{$command}")) {
            return $this->_codes['syntaxerror'] . ' ' .
                "Unsupported command \"{$command}\" (full line was \"{$rawCommand}\") received from confmodule";
        }

        // Now call the method for the command
        $command = "command{$command}";

        $ret = join(' ', call_user_func_array(array($this, $command), $params));

        iHMS_Sysconf_Log::debug('developer', "--> {$ret}");

        if (preg_match("/\n/", $ret)) { // TODO (PO) strpos()
            iHMS_Sysconf_Log::debug(
                'developer',
                "Warning: return value is multiline, and would break the sysconf protocol. Truncating to first line."
            );

            $ret = preg_replace("/\n.*/", '', $ret);
        }

        return $ret;
    }

    /**
     * Waits for the child process (if any) to finish so its return code can be examined.  The return code is stored in
     * the exitcode field of the object. It also marks all questions that were shown as seen
     *
     * @return string
     */
    public function finish()
    {
        fclose($this->_readHandle);
        $this->_readHandle = null;

        fclose($this->_writeHandle);
        $this->_writeHandle = fopen('/dev/null', 'w'); // Needed since in communicate() method we write after closing in some cases

        do {
            usleep(2000);
            $status = proc_get_status($this->_process);
        } while ($status['running']);

        // proc_close make call of system waitpid(3) here
        proc_close($this->_process);
        $this->_process = null;
        $ret = $status['exitcode'];

        $this->_exitCode = ($this->_caughtSigpipe) ? $this->_caughtSigpipe : $ret;

        // Stop catching SIGPIPE now.
        // IGNORE (SIG_IGN) and DEFAULT (SIG_DFL) both cause obscure failures, BTW
        // TODO check if problem occur with PHP since problems were encountered with Perl
        pcntl_signal(SIGPIPE, function()
        {
        });

        /** @var $_ iHMS_Sysconf_Question */
        foreach ($this->_seen as $_) {
            // Try to get the question again, because it's possible it was show, and then unregistered.
            if (!is_null(iHMS_Sysconf_Question::get($_->getName()))) {
                $_->setFlag('seen', 'true');
            }
        }

        $this->_seen = array();

        return false;
    }

    /**
     * Creates an element to stand for the question that is to be asked and adds it to the list of elements in our
     * associated FrontEnd.
     *
     * @param string $priority Priority
     * @param string $questionName Question name
     * @return array
     */
    public function commandInput($priority, $questionName)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        if (!iHMS_Sysconf_Priority::isValidPriority($priority)) {
            return array($this->_codes['syntaxerror'], "\"$priority\" is not a valid priority");
        }

        $question->setPriority($priority);

        // Figure out if the question should be displayed to the user or not
        $visible = true;

        // Error questions are always shown even if they're asked at a low priority or have already been seen.
        if ($question->type != 'error') {
            // Don't show items that are unimportant
            if (!iHMS_Sysconf_Priority::highEnough($priority)) {
                $visible = false;
            }

            // Don't re-show already seen questions, unless reconfiguring
            if (iHMS_Sysconf_Config::getInstance()->reShow == '' && $question->getFlag('seen') == 'true') {
                $visible = false;
            }
        }

        // We may want to set the seen flag on noninteractive questions even though they aren't shown.
        $markseen = $visible;

        // Noninteractive frontends never show anything.
        if ($visible && !$this->_frontend->isInteractive()) {
            $visible = false;

            if (iHMS_Sysconf_Config::getInstance()->nonInteractiveSeen != 'true') {
                $markseen = false;
            }
        }

        $element = null;

        if ($visible) {
            // Create an input element of the type associated with the frontend
            $element = $this->_frontend->makeElement($question);

            // If that failed, quit now. This should never happen.
            if (!$element) {
                return array($this->_codes['internalerror'], 'unable to make an input element');
            }

            // Ask the element if it thinks it is visible. If not, fall back below to making a noninteractive element.
            //
            // This last check is useful, because for example, select Elements are not really visible if they have less than
            // two choices.
            $visible = $element->isVisible();
        }

        if (!$visible) {
            // Create a noninteractive element. Supress debug messages because they generate FAQ's and are harmless.
            $element = iHMS_Sysconf_Frontend_Noninteractive::makeElement($question, true);

            // If that failed, the question is just not visible
            if (!$element) {
                return array($this->_codes['input_invisible'], 'question skipped');
            }
        }

        $element->markseen = $markseen;
        $this->_busy[] = $questionName;
        $this->_frontend->add($element);

        if ($element->isVisible()) {
            return array($this->_codes['success'], 'question will be asked');
        } else {
            return array($this->_codes['input_invisible'], 'question skipped');
        }
    }

    /**
     * Clears out the list of elements in our accociated FrontEnd
     *
     * @return array
     */
    public function commandClear()
    {
        $this->_frontend->clear();
        $this->_busy = array();
        return array($this->_codes['success']);
    }

    /**
     * Compares protocol versions with the confmodule. The version field of the ConfModule is sent to the client
     *
     * @param string $version Version to compare
     * @return array
     */
    public function commandVersion($version = null)
    {
        if (!is_null($version)) {
            if (version_compare($version, $this->_version, '<')) {
                return array($this->_codes['version_bad'], "Version too low ({$version}");
            } elseif (version_compare($version, $this->_version, '>')) {
                return array($this->_codes['version_bad'], "Version too high ({$version}");
            }
        }

        return array($this->_codes['success'], $this->_version);
    }

    /**
     * Sets the client_capb field to the confmodules's capabilities, and also sets the capb_backup field of the
     * ConfModules associated FrontEnd if the confmodule can backup. Sends the capb field of the associated FrontEnd
     * to the confmodule.
     *
     * @param string|array capabilitie(s)
     * @return array
     */
    public function commandCapb($capabilities)
    {
        $capabilities = (array)$capabilities;
        $this->_clientCapb = $capabilities;

        // Set capb_backup on the frontend if the client can backup.
        if (in_array('backup', $capabilities)) {
            $this->_frontend->setCapbBackup(true);
        }

        $capb = array('escape');

        if ($this->_frontend->getCapb()) {
            $capb[] = $this->_frontend->getCapb();
        }

        return array($this->_codes['success'], join(' ', $capb));
    }

    /**
     * Stores the specified title in the associated FrontEnds title field
     *
     * @param string $title Title
     * @return array
     */
    public function commandTitle($title)
    {
        $title = join(' ', func_get_args());
        $this->_frontend->setTitle($title);
        $this->_frontend->setRequestedTitle($title);

        return array($this->_codes);
    }

    /**
     * Uses the short description of a question as the title, with automatic i18n
     *
     * @param string $questionName Question name
     * @return array
     * // TODO review
     */
    public function commandSetTitle($questionName)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"$questionName\" doesn't exist");
        }

        $this->_frontend->setTitle($question->getDescription());
        $this->_frontend->setRequestedTitle($this->_frontend->getTitle());

        return array($this->_codes['success']);
    }

    /**
     * This is just stub method to be overridden by other modules
     *
     * @return int
     */
    public function commandBeginblock()
    {
        return array($this->_codes['success']);
    }

    /**
     * This is just stub method to be overridden by other modules
     *
     * @return int
     */
    public function commandEndblock()
    {
        return array($this->_codes['success']);
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
    public function commandGo()
    {
        $ret = $this->_frontend->go();

        // If no elements were shown, and we backed up last time, back up again even if the user didn't indicate they want
        // to back up. This causes invisible elements to be skipped over in multi-stage backups.
        if ($ret && (!$this->_backedUp or array_filter($this->_frontend->getElements(), function($_) // TODO check behavior
            {
                /** @var $_ iHMS_Sysconf_Element */
                return $_->isVisible();
            }
        ))
        ) {
            foreach ($this->_frontend->getElements() as $_) {
                $_->question->setValue($_->getValue());

                if ($_->markseen && $_->question) {
                    $this->_seen[] = $_->question;
                }
            }

            $this->_frontend->clear();
            $this->_busy = array();
            $this->_backedUp = false;

            return array($this->_codes['success'], 'ok');
        } else {
            $this->_frontend->clear();
            $this->_busy = array();
            $this->_backedUp = true;

            return array($this->_codes['go_back'], 'backup');
        }
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
    public function commandGet($questionName)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        $value = $question->getValue();

        if (!is_null($value)) {
            if (in_array('escape', $this->_clientCapb)) {
                return array($this->_codes['escaped_data'], $this->escape($value));
            } else {
                return array($this->_codes['success'], $value);
            }
        } else {
            return array($this->_codes['success'], '');
        }
    }

    /**
     * This must be passed a question name and a value. It sets the question's value
     *
     * @param string $questionName Question name
     * @param string $value Value
     * @return array
     */
    public function commandSet($questionName, $value)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        $value = join(' ', array_slice(func_get_args(), 1));

        $question->setValue($value);

        return array($this->_codes['success'], 'value set');
    }

    /**
     * Reset a question to its default value
     *
     * @param string $questionName Question name
     * @return array
     */
    public function commandQreset($questionName)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        $question->setValue($question->default);
        $question->setFlag('seen', 'false');

        return array($this->_codes['success']);
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
    public function commandSubst($questionName, $key, $value)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        $value = join(' ', array_slice(func_get_args(), 2));

        $result = $question->setVariable($key, $value);

        if (is_null($result)) {
            return array($this->_codes['internalerror'], 'Substitution failed');
        }

        return array($this->_codes['success']);
    }

    /**
     * This should be passed a template name and a question name. Registers a question to use the template
     *
     * @param string $templateName Template name
     * @param string $questionName Question name
     * @return array
     */
    public function commandRegister($templateName, $questionName)
    {
        $tempObj = iHMS_Sysconf_Question::get($templateName);

        if (is_null($tempObj)) {
            return array($this->_codes['badparams'], "No such template, \" {$templateName}\"");
        }

        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            $question = iHMS_Sysconf_Question::factory($questionName, $this->_owner, $tempObj->type);
        }

        if (is_null($question)) {
            return array($this->_codes['internalerror'], 'Internal error adding owner');
        }

        if (is_null($question->getTemplate())) {
            return array($this->_codes['internalerror'], 'Internal error setting template');
        }

        return array($this->_codes['success']);
    }

    /**
     * Pass this a question name, and it will give up ownership of the question, which typically causes it to be
     * removed
     *
     * @param string $questionName Question name
     * @return array
     */
    public function commandUnregister($questionName)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        if (in_array($questionName, $this->_busy)) {
            return array($this->_codes['badparams'], "{$questionName} is bussy, cannot unregister right now");
        }

        $question->removeOwner($this->_owner);

        return array($this->_codes['success']);
    }

    /**
     * This will give up ownership of all questions a confmodule owns
     *
     * @return array
     */
    public function commandPurge()
    {
        $iterator = iHMS_Sysconf_Question::getIterator();

        /** @var $question iHMS_Sysconf_Question */
        foreach ($iterator as $question) {
            $question->removeOwner($this->_owner);
        }

        return array($this->_codes['success']);
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
    public function commandMetaget($questionName, $fieldName)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        $lcfield = strtolower($fieldName);
        $fieldVal = $question->{$lcfield};

        if (is_null($fieldVal)) {
            return array($this->_codes['badparams'], "{$fieldName} does not exist");
        }

        if (in_array('escape', $this->_clientCapb)) {
            return array($this->_codes['escaped_data'], $this->escape($fieldVal));
        }

        return array($this->_codes['success'], $fieldVal);
    }

    /**
     * Pass this a question name and a flag name. It returns the value of the specified flag on the question
     *
     * @param string $questionName Question name
     * @param string $flagName Flag name
     * @return array
     */
    public function commandFget($questionName, $flagName)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        return array($this->_codes['success'], $question->getFlag($flagName));
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
    public function commandFset($questionName, $flagName, $value)
    {
        if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
            return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        if ($flagName == 'seen') {
            // If this question we're being asked to modify is one that was shown in the current session, it will be in
            // our seen cache, and changing its value here will not persist after this session, because the seen property
            // overwrites the values at the end of the session. Therefore, remove it from our seen cache.
            $this->_seen = array_filter($this->_seen, function($_) use($question)
            {
                return $_ !== $question;
            });
        }

        $value = join(' ', array_slice(func_get_args(), 2));

        return array($this->_codes['success'], $question->setFlag('seen', $value));
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
    public function commandInfo($questionName = null)
    {
        if (is_null($questionName)) {
            $this->_frontend->setInfo(); // Set to null
        } else {
            if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
                return array($this->_codes['badparams'], "\"$questionName\" doesn't exist");
            }

            $this->_frontend->setInfo($question);
        }

        return array($this->_codes['success']);
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
     * Note that the frontend's progressSet(), progressStep(), and progressInfo() methods should return TRUE, unless
     * the progress bar was canceled.
     *
     * @param string $subcommand
     * @internal mixed $argv Subcommand arguments(s)
     * @return array
     */
    public function commandProgress($subcommand)
    {
        $subcommand = strtolower($subcommand);

        if ($subcommand == 'start') {
            if (func_num_args() != 4) {
                return array($this->_codes['syntaxerror'], 'Incorrect number of arguments');
            }

            $min = func_get_arg(1);
            $max = func_get_arg(2);
            $questionName = func_get_arg(3);

            if ($min > $max) {
                return array($this->_codes['syntaxerror'], "min ($min) > max ($max)");
            }

            if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
                return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
            }

            $this->_frontend->progressStart($min, $max, $question);
            $ret = true;
        } elseif ($subcommand == 'set') {
            if (func_num_args() != 2) {
                return array($this->_codes['syntaxerror'], 'Incorrect number of arguments');
            }

            $value = func_get_arg(1);
            $ret = $this->_frontend->progressSet($value);
        } elseif ($subcommand == 'step') {
            if (func_num_args() != 2) {
                return array($this->_codes['syntaxerror'], 'Incorrect number of arguments');
            }

            $inc = func_get_arg(1);
            $ret = $this->_frontend->progressStep($inc);
        } elseif ($subcommand == 'info') {
            if (func_num_args() != 2) {
                return array($this->_codes['syntaxerror'], 'Incorrect number of arguments');
            }

            $questionName = func_get_arg(1);

            if (is_null($question = iHMS_Sysconf_Question::get($questionName))) {
                return array($this->_codes['badparams'], "\"{$questionName}\" doesn't exist");
            }

            $ret = $this->_frontend->progressInfo($question);
        } elseif ($subcommand == 'stop') {
            if (func_num_args() != 1) {
                return array($this->_codes['syntaxerror'], 'Incorrect number of arguments');
            }
            $this->_frontend->progressStop();
            $ret = true;
        } else {
            return array($this->_codes['syntaxerror'], 'unknown subcommand');
        }

        if ($ret) {
            return array($this->_codes['success'], 'OK');
        } else {
            return array($this->_codes['progresscancel'], 'CANCELED');
        }
    }

    /**
     * Accept template data from the client, for use on the UI agent side of the passthrough frontend.
     *
     *
     * @param string $templateName Template name
     * @param string $item Item
     * @param string $value Value
     * @return array
     * @TODO passthrough frontend
     */
    public function commandData($templateName, $item, $value)
    {
        $value = join(' ', array_slice(func_get_args(), 2));
        $value = preg_replace('/\\\\([n"\\\\])/e', '"$1" == "n" ? "\n" : "$1"', $value);

        $tempObj = iHMS_Sysconf_Template::get($templateName);

        if (!$tempObj) {
            if ($item != 'type') {
                return array($this->_codes['badparams'], "Template data field '{$item}' received before type field");
            }
            $tempObj = iHMS_Sysconf_Template::factory($templateName, $this->_owner, $value);

            if (!$tempObj) {
                return array($this->_codes['internalerror'], 'Internal error making template');
            }
        } else {
            if ($item == 'type') {
                return array($this->_codes['badparams'], 'Template type already set');
            }
            /** @see iHMS_Sysconf_Encoding */
            require_once 'iHMS/Sysconf/Encoding.php';
            $tempObj->{$item} = iHMS_Sysconf_Encoding::convert('UTF-8', $value);
        }

        return array($this->_codes['success']);

    }

    /**
     * Load the given template file
     *
     * @param string $file File to load
     * @param string $owner OPTIONAL Owner
     * @return array
     */
    public function commandXloadTemplateFile($file, $owner = null)
    {
        if (!($fh = @fopen($file, 'r'))) {
            return array($this->_codes['badparams'], "failed to open {$file}: $php_errormsg");
        }

        if (is_null($owner)) {
            $owner = $this->_owner;
        }

        try {
            iHMS_Sysconf_Template::load($fh, $owner);
        } catch (Exception $e) {
            $_ = preg_replace("\n", '\\n', $e->getMessage());
            return array($this->_codes['internalerror'], $_);
        }

        return array($this->_codes['success']);

    }

    /**
     * When the object is destroyed, the filehandles are closed and the confmodule script stopped. All questions that
     * have been displayed during the lifetime of the confmodule are marked as seen.
     */
    public function __destruct()
    {
        if (!is_null($this->_readHandle)) {
            fclose($this->_readHandle);
        }

        if (!is_null($this->_writeHandle)) {
            fclose($this->_writeHandle);
        }

        if (!is_null($this->_process)) {
            proc_terminate($this->_process, SIGTERM);
        }
    }
}
