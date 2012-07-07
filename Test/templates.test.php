#!/usr/bin/env php
<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('track_errors', 1);

set_include_path(dirname(__DIR__) . '/library' . PATH_SEPARATOR . get_include_path());

// Classes loader
spl_autoload_register(
    function($className)
    {
        $classPath = str_replace('_', '/', $className);
        require_once "$classPath.php";
    }
);

// An array that hold the capabilities the confmodule reports
$clientCapb = array();

$codes = array(
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

// An array that hold a list of all questions that have ever been shown to the user.
$seen = array();

// An array that hold a list of named of question that are "busy" -- in the process of being shown,
// that cannot be unregistered right now
$busy = array();

/**
 * Escape backslashes and newlines for output via the sysconf protocol
 *
 * @param string $text Text to escape
 * @return string
 */
function escape($text)
{
    return addcslashes($text, "\\\n");
}

/**
 * Creates an Element to stand for the question that is to be asked and adds it to the list of elements in our
 * associated FrontEnd.
 *
 * @param string $priority
 * @param string $questionName
 * @return array
 */
function input($priority, $questionName)
{
    global $frontend, $codes;

    if (($question = iHMS_Sysconf_Question::get($questionName)) == null) {
        return array($codes['badparams'], "\"{$questionName}\" doesn't exist");
    }

    if (!iHMS_Sysconf_Priority::isValidPriority($priority)) {
        return array($codes['syntaxerror'], "\"$priority\" is not a valid priority");
    }

    $question->setPriority($priority);

    // Figure out if the question should be displayed to the user or not.
    $visible = true;

    // Error questions are always shown even if they're asked at a low priority or have already been seen.
    if ($question->type != 'error') {
        // Don't show items that are unimportant.
        if (!iHMS_Sysconf_Priority::highEnough($priority)) {
            $visible = '';
        }

        // Don't re-show already seen questions, unless reconfiguring.
        if (!iHMS_Sysconf_Config::getInstance()->reShow && $question->getFlag('seen') == 'true') {
            $visible = false;
        }
    }

    // We may want to set the seen flag on noninteractive questions even though they aren't shown.
    $markseen = $visible;

    // Noninteractive frontends never show anything.
    if ($visible && !$frontend->isInteractive()) {
        $visible = false;

        if (iHMS_Sysconf_Config::getInstance()->nonInteractiveSeen != 'true') {
            $markseen = '';
        }
    }

    $element = null;

    if ($visible) {
        // Create an input element of the type associated with the frontend
        $element = $frontend->makeElement($question);

        // If that failed, quit now. This should never happen.
        if (!$element) {
            return array($codes['internalerror'], "unable to make an input element");
        }

        // Ask the Element if it thinks it is visible. If not, fall back below to making a noninteractive element.
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
            return array($codes['input_invisible'], "question skipped");
        }
    }

    $element->markseen = $markseen;
    $busy[] = $questionName;
    $frontend->add($element);

    if ($element->isVisible()) {
        return array($codes['success'], "question will be asked");
    } else {
        return array($codes['input_invisible'], "question skipped");
    }
}

/**
 * Uses the short description of a question as the title, with automatic i18n
 *
 * @param string $questionName Question name
 * @return array
 */
function settitle($questionName)
{
    global $frontend, $codes;

    if (($question = iHMS_Sysconf_Question::get('test/title')) === null) {
        return array($codes['badparams'], "\"$questionName\" doesn't exist");
    }

    $frontend->setTitle($question->getDescription());
    $frontend->setRequestedTitle($frontend->getTitle());

    return array($codes['success'], null);
}

/**
 * Pass this a question name. It displays the given template as an out-of-band informative message. Unlike inputting a
 * note, this doesn't require an acknowledgement from the user, and depending on the frontend it may not even be
 * displayed at all. Frontends should display the info persistently until some other info comes along.
 *
 * With no arguments, this resets the info message to a default value.
 *
 * @param string|null $questionName
 * @return array
 */
function info($questionName = null)
{
    global $frontend, $codes;

    if (!$questionName) {
        $frontend->setInfo();
    } else {
        if (($question = iHMS_Sysconf_Question::get($questionName)) === null) {
            return array($codes['badparams'], "\"$questionName\" doesn't exist");
        }

        $frontend->setInfo($question);
    }

    return array($codes['success'], null);
}

/**
 * Substitute the given variable linked to the given question with the given value
 *
 * This must be passed a question name, a key, and a value. It sets up variable substitutions on the questions
 * description so all instances of the key (wrapped in "${}") are replaced with the value
 */
function subst($questionName, $key, $value)
{
    global $codes;

    if (is_array($value)) {
        $value = join(' ', $value);
    }

    if (($question = iHMS_Sysconf_Question::get($questionName)) === null) {
        return array($codes['badparams'], "\"{$questionName}\" doesn't exist");
    }

    $result = $question->setVariable($key, $value);

    if (!isset($result)) {
        return array($codes['internalerror'], "Substitution failed");
    }

    return array($codes['success'], null);
}

/**
 * Progress commmand
 *
 * @param string $subcommand
 * @internal param mixed $_ ... Subcommand arguments(s)
 * @return array
 */
function progress($subcommand)
{
    global $frontend, $codes;
    $subcommand = strtolower($subcommand);

    if ($subcommand == 'start') {
        if (func_num_args() != 4) {
            return array($codes['syntaxerror'], "Incorrect number of arguments");
        }

        $min = func_get_arg(1);
        $max = func_get_arg(2);
        $questionName = func_get_arg(3);

        if ($min > $max) {
            return array($codes['syntaxerror'], "min ($min) > max ($max)");
        }

        if (($question = iHMS_Sysconf_Question::get($questionName)) === null) {
            return array($codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        $frontend->progressStart($min, $max, $question);
        $ret = true;
    } elseif ($subcommand == 'set') {
        if (func_num_args() != 2) {
            return array($codes['syntaxerror'], "Incorrect number of arguments");
        }

        $value = func_get_arg(1);
        $ret = $frontend->progressSet($value);
    } elseif ($subcommand == 'step') {
        if (func_num_args() != 2) {
            return array($codes['syntaxerror'], "Incorrect number of arguments");
        }

        $inc = func_get_arg(1);
        $ret = $frontend->progressStep($inc);
    } elseif ($subcommand == 'info') {
        if (func_num_args() != 2) {
            return array($codes['syntaxerror'], "Incorrect number of arguments");
        }

        $questionName = func_get_arg(1);

        if (($question = iHMS_Sysconf_Question::get($questionName)) === null) {
            return array($codes['badparams'], "\"{$questionName}\" doesn't exist");
        }

        $ret = $frontend->progressInfo($question);
    } elseif ($subcommand == 'stop') {
        if (func_num_args() != 1) {
            return array($codes['syntaxerror'], "Incorrect number of arguments");
        }
        $frontend->progressStop();
        $ret = true;
    } else {
        return array($codes['syntaxerror'], 'unknown subcommand');
    }

    if ($ret) {
        return array($codes['success'], 'OK');
    } else {
        return array($codes['progresscancel'], "CANCELED");
    }
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
    global $codes, $clientCapb;

    if (($question = iHMS_Sysconf_Question::get($questionName)) === null) {
        return array($codes['badparams'], "\"{$questionName}\" doesn't exist");
    }

    $lcfield = strtolower($fieldName);
    $fieldVal = $question->{$lcfield};

    if (!isset($fieldVal)) {
        return array($codes['badparams'], "{$fieldName} does not exist");
    }

    if (in_array('escape', $clientCapb)) {
        return array($codes['escaped_data'], escape($fieldVal));
    }

    return array($codes['success'], $fieldVal);
}

/**
 * Returns the value set in the given question
 *
 * This must be passed a question name. It queries the question for the value set in it and returns that to the
 * confmodule
 *
 * @param $questionName
 * @return array
 */
function get($questionName)
{
    global $codes, $clientCapb;

    if (($question = iHMS_Sysconf_Question::get($questionName)) === null) {
        return array($codes['badparams'], "\"{$questionName}\" doesn't exist");
    }

    $value = $question->getValue();

    if (isset($value)) {
        if (in_array('escape', $clientCapb)) {
            return array($codes['escaped_data'], escape($value));
        } else {
            return array($codes['success'], $value);
        }
    } else {
        return array($codes['success'], '');
    }
}

/**
 * This is just stub method
 *
 * @return int
 */
function beginblock()
{
    global $codes;
    return $codes['success'];
}

/**
 * This is just stub method
 *
 * @return int
 */
function endblock()
{
    global $codes;
    return $codes['success'];
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
    global $clientCapb, $frontend, $codes;

    $capabilities = (array)$capabilities;
    $clientCapb = $capabilities;

    // Set capb_backup on the frontend if the client can backup.
    if (in_array('backup', $capabilities)) {
        $frontend->setCapbBackup(true);
    }

    $capb = array('escape');

    if($frontend->getCapb()) {
        $capb[] = $frontend->getCapb();
    }

    return array($codes['success'], $capb);
}

$backedUp = false;

/**
 * Tells the associated FrontEnd to display items to the user, by calling its go method. That method should return FALSE
 * if the user asked to back up, and TRUE otherwise. If it returns true, then all of the questions that were displayed
 * are added to the seen array
 *
 * @return bool
 */
function go()
{
    global $frontend, $backedUp, $busy, $seen, $codes;

    $ret = $frontend->go();

    // If no elements were shown, and we backed up last time, back up again even if the user didn't indicate they want
    // to back up. This causes invisible elements to be skipped over in multi-stage backups.
    if ($ret && (!$backedUp || array_filter($frontend->getElements(), function($_)
        {
            /** @var $_ iHMS_Sysconf_Element */
            return $_->isVisible();
        }
    ))
    ) {
        foreach ($frontend->getElements() as $_) {

            $_->question->setValue($_->getValue());

            if ($_->markseen && $_->question) {
                $seen[] = $_->question;
            }
        }

        $frontend->clear();
        $busy = array();
        $backedUp = false;

        return array($codes['success'], 'ok');
    } else {
        $frontend->clear();
        $busy = array();
        $backedUp = true;
        return array($codes['go_back'], "backup");
    }
}

/**
 * Marks all questions that were shown as seen
 *
 * @return string
 */
function stop()
{
    global $seen;

    /** @var $_ iHMS_Sysconf_Question */
    foreach ($seen as $_) {
        // Try to get the question again, because it's possinle ot was show,, and then unregistered.
        if ($question = iHMS_Sysconf_Question::get($_->getName())) {
            $_->setFlag('seen', 'true');
        }
    }

    $seen = array();

    return '';
}

/***********************************************************************************************************************
 * Main
 */

putenv('SYSCONF_SYSTEMRC=../library/iHMS/Sysconf/sysconf.conf');

// Load sysconf database
iHMS_Sysconf_Db::load();

// Load all templates
iHMS_Sysconf_Template::load(__DIR__ . '/test.templates', 'test');

// Make frontend
$frontend = new iHMS_Sysconf_Frontend_Dialog();
$frontend->defaultTitle('Test');
$frontend->clear();

//db_version('2.0');
$ret = capb('backup');
fwrite(STDERR, "Frontend capabilities are: " . join(', ', $ret[1]) . "\n");

// capb('escape'); No relevant for now

// Set title
settitle('test/title');

// This implements a simple state machine so the back button can be handled (backup capability)

$state = 1;
while ($state != 0 && $state != 10) {
    switch ($state) {
        case 1:
            input('high', 'test/boolean');
            break;
        case 2:
            input('high', 'test/multiselect');
            break;
        case 3:
            info('test/info');
            input('critical', 'test/string');
            input('low', 'test/password');
            input('low', 'test/text');
            subst('test/select', 'colors', 'red, Yellow, green');
            input('high', 'test/select');
            break;
        case 4:
            beginblock();
            input('low', 'test/boolean');
            input('low', 'test/boolean');
            endblock();
            input('low', 'test/note');
            break;
        case 5:
            // Will be displayed again
            input('high', 'test/password');
            break;
        case 6:
            progress('START', 0, 10, 'test/progress/title');
            sleep(1);
            progress('SET', 2);
            sleep(1);
            progress('INFO', 'test/progress/info');
            progress('STEP', 3);
            sleep(1);
            progress('STEP', 1);
            sleep(1);
            progress('STOP');
            break;
        case 7:
            subst('test/subst', 'user', "joeuser\nanotheruser");
            list(, $value) = metaget('test/subst', 'extended_description');
            fwrite(STDERR, "test/subst extended_description: {$value}\n");
            input('high', 'test/subst');
            break;
        case 8:
            input('high', 'test/error');
            break;
        case 9:
            input('low', 'test/error');
            break;
    }

    $ret = go();
    ($ret[0] == 30) ? $state-- : $state++;
}

list(, $value) = get('test/string');
echo "$value\n";

list(, $value) = get('test/boolean');
echo $value, "\n";

list(, $value) = get('test/multiselect');
echo $value, "\n";

stop();

// Save sysconf database
iHMS_Sysconf_Db::save();

exit(0);
