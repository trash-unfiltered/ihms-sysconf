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
 * @subpackage  Frontend_Dialog
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Frontend_ScreenSize */
require_once 'iHMS/Sysconf/Frontend/ScreenSize.php';

/** @see Zend_Text_MultiByte */
require_once 'Zend/Text/MultiByte.php';

/**
 * iHMS_Sysconf_Frontend_Dialog class
 *
 * This FrontEnd is for an user interface based on dialog or whiptail. It will use whichever is available, but prefers
 * to use whiptail if available. It handles all the messy communication with these programs.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Frontend_Dialog
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Frontend_Dialog extends iHMS_Sysconf_Frontend_ScreenSize
{
    /**
     * @var string Dialog program to use
     */
    protected $_program = 'whiptail';

    /**
     * @var string Dialog program path
     */
    protected $_programPath = '';

    /**
     * @var string|null Dash separator
     */
    protected $_dashSeparator = '--';

    /**
     * @var int Border width
     */
    protected $_borderWidth = 5;

    /**
     * @var int Border heitgh
     */
    protected $_borderHeight = 6;

    /**
     * @var int spacer
     */
    protected $_spacer = 1;

    /**
     * @var int Title spacer
     */
    protected $_titleSpacer = 10;

    /**
     * @var int Column spacer
     */
    protected $_columnSpacer = 3;

    /**
     * @var int Select spacer
     */
    protected $_selectSpacer = 13;

    /**
     * @var resource dialog process
     */
    protected $_dialogProcess = null;

    /**
     * @var resource dup of stding passed to dialog
     */
    protected $_childIn = null;

    /**
     * @var resource dup of stdout passed to dialog
     */
    protected $_childOut = null;

    /**
     * @var resource dialog output
     */
    protected $_dialogOutput = null;

    /**
     * @var resource dialog error
     */
    protected $_dialogError = null;

    /**
     * Init Dialog Frontend
     *
     * Checks to see if whiptail, or dialog are available, in that order. To make it use dialog, set
     * SYSCONF_FORCE_DIALOG in the environment.
     *
     * @return void
     */
    protected function _init()
    {
        parent::_init();

        // These environment variable screws up at least whiptail with the way we call it. Posix does not allow safe arg
        // passing like whiptail needs
        if (getenv('POSIXLY_CORRECT')) putenv('POSIXLY_CORRECT');
        if (getenv('POSIX_ME_HARDER')) putenv('POSIX_ME_HARDER');

        // Detect all the ways people have managed to screw up their terminals (so far...)
        if (!isset($_SERVER['TERM']) || $_SERVER['TERM'] == '') {
            fwrite(STDERR, "TERM is not set, so dialog frontend is not usable.\n");
            exit(1);
        } elseif (preg_match('/emacs/i', $_SERVER['TERM'])) {
            fwrite(STDERR, "Dialog frontend is incompatible with emacs shell buffers.\n");
            exit(1);
        } elseif ($_SERVER['TERM'] == 'dumb' || $_SERVER['TERM'] == 'unknown') {
            fwrite(STDERR, "Dialog frontend will not work on a dumb terminal, an emacs shell buffer or without a controlling terminal.\n");
            exit(1);
        }

        $this->_interactive = true;
        $this->_capb = 'backup';

        // Autodetect if whiptail or dialog is available and set magic numbers.
        if (
            ($programPath = $this->_findProgramPath('whiptail')) &&
            (!getenv('SYSCONF_FORCE_DIALOG') || !$this->_findProgramPath('dialog'))
        ) {
            $this->_programPath = $programPath;
        } elseif (($programPath = $this->_findProgramPath('dialog'))) {
            $this->_program = 'dialog';
            $this->_programPath = $programPath;
            $this->_dashSeparator = null; // Dialog do not need (or support) double-dash separation
            $this->_borderWidth = 7;
            $this->_borderHeight = 6;
            $this->_spacer = 0;
            $this->_titleSpacer = 4;
            $this->_columnSpacer = 2;
            $this->_selectSpacer = 0;
        } else {
            fwrite(STDERR, "Not usable dialog-like program is installed, so the dialog based frontend cannot be used.\n");
            exit(1);
        }

        // Whiptail and dialog can't deal with very small screens. Detect this and fail, forcing use of some other
        // frontend. The numbers were arrived at by experimentation.
        if ($this->_screenHeight < 13 || $this->_screenWidth < 31) {
            fwrite(STDERR, "Dialog frontend requires a screen at least 13 lines tall and 31 columns wide.\n");
            exit(1);
        }
    } // end-init()

    /**
     * Returns dash separator
     *
     * @return string|null Dash separator or NULL if not defined for the current dialog program
     */
    public function getDashSeparator()
    {
        return $this->_dashSeparator;
    }

    /**
     * Returns border width
     *
     * @return int Border width
     */
    public function getBorderWidth()
    {
        return $this->_borderWidth;
    }

    /**
     * Return border height
     *
     * @return int Border height
     */
    public function getBorderHeight()
    {
        return $this->_borderHeight;
    }

    /**
     * Returns spacer
     *
     * @return int spacer
     */
    public function getSpacer()
    {
        return $this->_spacer;
    }

    /**
     * Returns title spacer
     *
     * @return int Title spacer
     */
    public function getTitleSpacer()
    {
        return $this->_titleSpacer;
    }

    /**
     * Returns column spacer
     *
     * @return int Column spacer
     */
    public function getColumnSpacer()
    {
        return $this->_columnSpacer;
    }

    /**
     * Return select spacer
     *
     * @return int Select spacer
     */
    public function getSelectSpacer()
    {
        return $this->_selectSpacer;
    }

    /**
     * Return childIn
     *
     * @return resource
     */
    public function getChildIn()
    {
        return $this->_childIn;
    }

    /**
     * Size text
     *
     * Dialog and whiptail have an annoying field of requiring you specify their dimensions explicitly. This function
     * handles doing that. Just pass in the text that will be displayed in the dialog, and it will split out new text,
     * formatted nicely, then the height and width for the dialog.
     *
     * @param string $text Text
     * @return array An array that holds the formated text, the height and width for the dialog
     */
    public function sizeText($text)
    {
        // Try to guess how many lines the text will take up in the dialog. This is difficult because long lines are
        // wrapped. So what I'll do is pre-wrap the text and then just look at the number of lines it takes up
        $columns = $this->_screenWidth - $this->_borderWidth - $this->_columnSpacer;

        // Dialog do not add litteral newline at text begin like whiptail do, so we must add it manually
        //if ($this->_program == 'dialog') {
        //    $text = "\n$text";
        //}

        // TODO support for CJK characters
        $text = Zend_Text_MultiByte::wordWrap($text, $columns, "\n", true, 'UTF-8');

        $lines = explode("\n", $text);

        // Now figure out what's the longest line.
        $windowColumns = iconv_strlen($this->_title + $this->_titleSpacer, 'UTF-8');

        array_map(
            function($_) use(&$windowColumns)
            {
                $w = iconv_strlen($_, 'UTF-8');

                if ($w > $windowColumns) {
                    $windowColumns = $w;
                }
            },

            $lines
        );

        return array($text, sizeof($lines) + $this->_borderHeight, $windowColumns + $this->_borderWidth);
    }

    /**
     * Hide escaped characters in input text from processing by dialog
     *
     * @param string $line line
     * @return string
     */
    public function hideEscape($line)
    {
        // dialog will display "\n" as a literal newline; use zero-width utf-8 characters (WORD JOINER) to avoid this
        return $line = preg_replace("/\\\\n/", "\\\xe2\x81\xa0n", $line);
    }

    /**
     * Show text
     *
     * Pass this some text and it will display the text to the user in a dialog. If the text is too long to fit in one
     * dialog, it will use a scrollable dialog.
     *
     * @param iHMS_Sysconf_Question $question $question Question
     * @param string $inText
     * @return void
     */
    public function showText($question, $inText)
    {
        $lines = $this->_screenHeight;

        list($text, , $width) = $this->sizeText($inText);

        $linesArray = explode("\n", $text);

        $args = array('--msgbox', join("\n", $linesArray));

        if ($lines - 4 - $this->_borderHeight < sizeof($linesArray)) {
            $num = $lines - 4 - $this->_borderHeight;

            if ($this->_program == 'whiptail') {
                // Whiptail can scroll text easily
                array_push($args, '--scrolltext');
            } else {
                // Dialog has to use temporary file
                $fh = iHMS_Sysconf_TmpFile::open();
                fwrite($fh, join("\n", array_map(array($this, 'hideEscape'), $linesArray)));
                fclose($fh);
                $args = array('--textbox', iHMS_Sysconf_TmpFile::getFilename());
            }
        } else {
            $num = sizeof($linesArray);
        }

        // Add height and width
        array_push($args, $num + $this->_borderHeight, $width);

        $this->showDialog($question, $args);

        if ($args[0] == '--textbox') {
            iHMS_Sysconf_TmpFile::cleanup();
        }
    }

    /**
     * Generate a prompt for the given question
     *
     * This is a helper method used by some dialog elements. Pass it the question that is going to be displayed.
     * It will use this to generate a prompt, using both the short and long descriptions of the question.
     *
     * You can optionally pass in a second parameter: a number. This can be used to tune how many lines are free on the
     * screen.
     *
     * If the prompt is too large to fit on the screen, it will instead be displayed immediatly, and the prompt will be
     * changed to just the short description.
     *
     * The return value is identical to the return value of sizetext() run on the generated prompt
     *
     * @param iHMS_Sysconf_Question $question Question
     * @param int $freelines Free lines
     * @return array An array that holds the formated text, the height and width for the dialog
     */
    public function makePrompt(iHMS_Sysconf_Question $question, $freelines = 0)
    {
        $freelines = $this->_screenHeight - $this->_borderHeight + 1 + $freelines;

        list($text, $lines, $columns) = $this->sizeText(
            $question->getExtendedDescription() . "\n\n" . $question->getDescription()
        );

        if ($lines > $freelines) {
            $this->showText($question, $question->getExtendedDescription());
            list($text, $lines, $columns) = $this->sizeText($question->getDescription());
        }

        return array($text, $lines, $columns);
    }

    /**
     * Start dialog
     *
     * @param iHMS_Sysconf_Question $question Question
     * @param bool $wantInputFd
     * @param array $args Arguments
     * @return void
     * @todo remove $question parameter?
     */
    public function startDialog(iHMS_Sysconf_Question $question, $wantInputFd, $args)
    {
        iHMS_Sysconf_Log::debug(
            'debug', "preparing to run dialog. Params are: {$this->_program}, " . join(', ', $args)
        );

        // Do not add cancel button either if backup is not available nor
        if (!($this->_capbBackup || in_array('--defaultno', $args))) { // TODO Behavior to be checked
            array_unshift($args, '--nocancel');
        }

        // Allow separation of errors from the expected output.
        // Default dialog behavior is to send any output on stderr
        //
        // Add the title
        array_unshift($args, '--output-fd 3', "--title {$this->_title}");

        // Set dialog backtitle
        if ($this->_info) {
            array_unshift($args, '--backtitle ' . $this->_info->getDescription());
        } else {
            array_unshift($args, "--backtitle Module configuration");
        }

        if ($this->_program == 'whiptail') { // Add ponctuation space before backtitle
            $args[0] = str_replace('--backtitle ', "--backtitle \xe2\x80\x88", $args[0]);
        }

        if(!$wantInputFd) {
            if (!$childIn = @fopen('php://stdin', 'r')) {
                fwrite(STDERR, "Unable to make dup of stdin\n");
                exit(1);
            }
        }

        if (!$chidOut = @fopen('php://stdout', 'w')) {
            fwrite(STDERR, "Unable to make dup of stdout\n");
            exit(1);
        }

        // Escape args
        $escapeshellarg = function($_)
        {
            if (preg_match('/^(--[^\s]+\s+)(.*)/', $_, $m)) {
                return $m[1] . escapeshellarg($m[2]);
            } elseif ($_ != '--') {
                return escapeshellarg($_);
            } else {
                return $_;
            }
        };

        $pipes = array(null, null, null);

        $this->_dialogProcess = @proc_open(
            "{$this->_programPath} " . join(' ', array_map($escapeshellarg, $args)),
            array(
                0 => isset($childIn) ? $childIn : array('pipe', 'r'),
                1 => $chidOut,
                2 => array('pipe', 'w'), // dialog errors
                3 => array('pipe', 'w') // dialog output (see option --output-fd set above)
            ),
            $pipes
        );

        if (!is_resource($this->_dialogProcess)) {
            fwrite(STDERR, sprintf('Unable to spawn %s: %s', $this->_program, $php_errormsg));
            exit(1);
        }

        $this->_childIn = isset($childIn) ? $childIn : $pipes[0];
        $this->_childOut = $chidOut;
        $this->_dialogError = $pipes[2];
        $this->_dialogOutput = $pipes[3];
    }

    /**
     * Wait dialog
     *
     * @param array $args Arguments that have been passed to dialog
     * @return array
     */
    public function waitDialog(array $args = array())
    {
        fclose($this->_childIn);

        $output = '';
        while ($_ = fgets($this->_dialogOutput)) {
            $output .= $_;
        }

        $errors = 0;
        while ($_ = fgets($this->_dialogError)) {
            fwrite(STDERR, $_);
            $errors++;
        }

        if ($errors) {
            fwrite(STDERR, sprintf('sysconf: %s output the above erors, giving up!', $this->_program) . "\n");
            exit(1);
        }

        chop($output);

        fclose($this->_childOut);
        fclose($this->_dialogOutput);
        fclose($this->_dialogError);

        // proc_close make call of system waitpid(3) here
        $ret = proc_close($this->_dialogProcess);

        // Now check dialog's return code to see if escape (255 (really -1)) or Cancel (1) were hit. If so, make
        // a note that we should back up.
        //
        // To complicate things, a return code of 1 also mean that yes was selected from a yes/no dialog, so we must
        // parse the parameters to see if such a dialog was displayed.
        if ($ret == 255 || ($ret == 1 && preg_match('/--yesno\s/', join(' ', $args)))) {
            $this->_backup = true;
            return null;
        } else {
            return array($ret, $output);
        }
    }

    /**
     * Display a dialog
     *
     * After the first parameters which should point to the question being displayed, all remaining parameters are
     * passed to whiptail/dialog.
     *
     * Returns an array that holds the return code of dialog, then the output if any.
     *
     * Note that the return code of dialog is examined, and if the user hit escape or cancel, this frontend will assume
     * they wanted to back up. In that case, this method will return null
     *
     * @param iHMS_Sysconf_Question $question Question
     * @param array $args Dialog arguments
     * @return array|null Array that hold dialog return code and the output if any or NULL in case the user hit
     *                    escape or cancel
     */
    public function showDialog(iHMS_Sysconf_Question $question, array $args)
    {
        $args = array_map(array($this, 'hideEscape'), $args);

        // It's possible to ask question in the middle of a progress bar. However, whiptail doesn't like having two
        // instances of itself trying to talk to the same terminal, so we need to shut the progress bar down temporarily
        if ($this->_progressBar) {
            $this->_progressBar->stop();
        }

        $this->startDialog($question, false, $args);

        $ret = $this->waitDialog($args);

        // Restart the progress bar if needed
        if ($this->_progressBar) {
            $this->_progressBar->start();
        }

        return $ret;
    }

    /**
     * Find a system program
     *
     * This is helper method to find program path in safe way.
     *
     * @param string $program Program to find
     * @return bool|string $program path or FALSE if $program is not found/available
     */
    protected function _findProgramPath($program)
    {
        $paths = array('/bin', '/usr/bin', '/usr/local/bin');

        // If open_basedir defined, fill the $openBasedir array with authorized paths.
        if ((bool)ini_get('open_basedir')) {
            $openBasedir = preg_split('/:/', ini_get('open_basedir'), -1, PREG_SPLIT_NO_EMPTY);
        }

        foreach ($paths as $path) {
            // To avoid "open_basedir restriction in effect" error when testing paths if restriction is enabled.
            if ((isset($openBasedir) && !in_array($path, $openBasedir)) || !is_dir($path)) {
                continue;
            }

            $programPath = $path . '/' . $program;

            if (is_executable($programPath)) {
                return $programPath;
            }
        }

        return false;
    }
}
