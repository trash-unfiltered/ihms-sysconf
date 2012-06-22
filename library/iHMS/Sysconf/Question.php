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
 * @subpackage  Question
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/**
 * iHMS_Sysconf_Question class
 *
 * This is an object that represents a question. Each question has some associated data (which is stored in a backend
 * database). To get at this data, just use $question->fieldname to read a field, and $question->fieldname = 'value' to
 * write a field. Any field names at all can be used, the convention is to lowaer-case their names. If a field that is
 * not defined is read, and a field by the same name exists on the Template, the question is mapped to, the value of
 * that field will be returned instead.
 *
 * Note: For fields that don't match with PHP field syntax, use curly brakets around them (eg . $template->{'fieldname'}).
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Question
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Question
{
    /**
     * @var iHMS_Sysconf_Question[]
     */
    protected static $_questions = array();

    /**
     * @var string Hold the name of the question
     */
    protected $_name = null;

    /**
     * @var int Holds the priority of the question
     */
    protected $_priority = null;

    /**
     * Create a new question
     *
     * New questions default to having their seen flag set to false
     *
     * @static
     * @param string $name Question name
     * @param string $owner Question owner
     * @param string $type Question type
     * @return iHMS_Sysconf_Question|null
     */
    public static function factory($name, $owner, $type)
    {
        if (in_array($name, self::$_questions)) {
            fwrite(STDERR, "sysconf: A question named \"$name\" already exists.\n");
            exit(1);
        }

        $self = new self();
        $self->_name = $name;

        // This is what actually creates the question in the db
        if (!$self->addOwner($owner, $type)) {
            return null;
        }

        $self->setFlag('seen', 'false');

        return self::$_questions[$name] = $self;
    }

    /**
     * Get an existing question. It will be pulled out of the database if necessary
     *
     * @static
     * @param string $name Question name
     * @return iHMS_Sysconf_Question|null
     */
    public static function get($name)
    {
        if (isset(self::$_questions[$name])) {
            return self::$_questions[$name];
        }

        if (iHMS_Sysconf_Db::getConfig()->exists($name)) {
            $self = new self();
            $self->_name = $name;

            return self::$_questions[$name] = $self;
        }

        return null;
    }

    /**
     * Returns an iterator object that will iterate over all existing questions, returning a new question object each
     * time it is called
     *
     * @static
     * @return iHMS_Sysconf_Iterator_Callback
     */
    public function getIterator()
    {
        require_once 'iHMS/Sysconf/Iterator/Callback.php';

        return new iHMS_Sysconf_Iterator_Callback(
            iHMS_Sysconf_Db::getConfig()->getIterator(),
            function($name)
            {
                return iHMS_Sysconf_Question::get($name);
            }
        );
    }

    /**
     * Returns the description of this Question
     *
     * This value is taken from the Template to which this Question is associated, and then any substitution in the
     * description are expanded.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->_expandVars($this->getTemplate()->description);
    }

    /**
     * Returns the extended description of this Question
     *
     * This value is taken from the Template to which this Question is associated, and then any substitution in the
     * description are expanded.
     *
     * @return string
     */
    public function getExtendedDescription()
    {
        return $this->_expandVars($this->getTemplate()->extended_description);
    }

    /**
     * Returns the choices field of this Question
     *
     * This value is taken from the Template to which this Question is associated, and then any substitution in the
     * description are expanded.
     *
     * @return string
     */
    public function getChoices()
    {
        return $this->_expandVars($this->getTemplate()->choices);
    }

    /**
     * Returns list of individual choices
     *
     * This takes the result of the choices() method and simply splits it up into individual choices and returns them as
     * a list
     *
     * @return array
     */
    public function choicesSplit()
    {
        $items = array();
        $item = '';

        foreach (preg_split('/(\\[, ]|,\s+)/', $this->getChoices()) as $chunk) { // TODO check regexp
            if (preg_match('/^\\([, ])$/', $chunk, $m)) { // TODO check regexp
                $item .= $m[1];
            } elseif (preg_match('/^,\s+$/', $chunk)) {
                array_push($items, $item);
                $item = '';
            } else {
                $item .= $chunk;
            }
        }

        if ($item != '') {
            array_push($items, $item);
        }

        return $items;
    }

    /**
     * Returns value of the given variable
     *
     * @param $variable
     * @return null|string
     */
    public function getVariable($variable)
    {
        return iHMS_Sysconf_Db::getConfig()->getVariable($this->_name, $variable);
    }

    /**
     * Sets value of the given variable
     *
     * @param string $variable Variable name
     * @param string $value Variable value
     * @return null|string
     */
    public function setVariable($variable, $value)
    {
        return iHMS_Sysconf_Db::getConfig()->setVariable($this->_name, $variable, $value);
    }

    /**
     * Return value of the given flag
     *
     * Flag value can be either "true" or false"
     *
     * @param string $flag Flag  name
     * @return bool
     */
    public function getFlag($flag)
    {
        return iHMS_Sysconf_Db::getConfig()->getFlag($this->_name, $flag);
    }

    /**
     * Sets value of the given flag
     *
     * Flag value can be either "true" or false"
     *
     * @param string $flag Flag name
     * @param string $value Flag value
     * @return bool|null
     */
    public function setFlag($flag, $value)
    {
        return iHMS_Sysconf_Db::getConfig()->setFlag($this->_name, $flag, $value);
    }

    /**
     * Return current value of the this question.
     *
     * Will returns the default value from the template in case value is not set
     *
     * @return string
     */
    public function getValue()
    {
        if ($ret = iHMS_Sysconf_Db::getConfig()->getField($this->_name, 'value')) {
            return $ret;
        }

        if ($template = $this->getTemplate()) {
            return $template->default;
        }

        return null;
    }

    /**
     * Sets the value of the question
     *
     * @param $value
     * @return null|string
     */
    public function setValue($value)
    {
        return iHMS_Sysconf_Db::getConfig()->setField($this->_name, 'value', $value);
    }

    /**
     * Returns list of individual values
     *
     * This takes the result of the value method and simply splits it up into individual values and returns them as a
     * list
     *
     * @return array
     */
    public function valueSplit()
    {
        $value = $this->getValue();
        $items = array();
        $item = '';

        foreach (preg_split('/(\\[, ]|,\s+)/', $value) as $chunk) { // TODO check regexp
            if (preg_match('/^\\([, ])$/', $chunk, $m)) { // TODO check regexp
                $item .= $m[1];
            } elseif (preg_match('/^,\s+$/', $chunk)) {
                array_push($items, $item);
                $item = '';
            } else {
                $item .= $chunk;
            }
        }

        if ($item != '') {
            array_push($items, $item);
        }

        return $items;
    }

    /**
     * Add an owner to the list of owners of this Question. Pass the owner name and the type of the Question. Adding an
     * owner that is already listed has no effect
     *
     * @param string $owner Owner Name
     * @param string $type Question type
     * @return string|null
     */
    public function addOwner($owner, $type)
    {
        return iHMS_Sysconf_Db::getConfig()->addOwner($this->_name, $owner, $type);
    }

    /**
     * Remove an owner from the list of owners of this Question.
     *
     * @param string $owner Owner name
     * @return string|null the owner name that was removed, or NULL if removal failed
     */
    public function removeOwner($owner)
    {
        $template = iHMS_Sysconf_Db::getConfig()->getField($this->_name, 'template');

        if (!iHMS_Sysconf_Db::getConfig()->removeOwner($this->_name, $owner)) {
            return null;
        }

        // If that made the question go away, the question no longer owns the template, and remove this object from the
        // class's cache.
        if (!empty($template) && !iHMS_Sysconf_Db::getConfig()->exists($this->_name)) {
            iHMS_Sysconf_Db::getTemplates()->removeOwner($template, $this->_name);
            unset(self::$_questions[$this->_name]);
        }

        return $owner;
    }

    /**
     * Returns a single string listing all owners of this Question, separated by commas followed by spaces
     *
     *
     * @return string isting all owners of this Question
     */
    public function getOwners()
    {
        $owners = iHMS_Sysconf_Db::getConfig()->getOwners($this->_name);
        sort($owners);

        return join(', ', $owners);
    }

    /**
     * Returns the template associated to this object
     *
     * @return iHMS_Sysconf_Template
     */
    public function getTemplate()
    {
        return iHMS_Sysconf_Template::get(iHMS_Sysconf_Db::getConfig()->getField($this->_name, 'template'));
    }

    /**
     * Set the template associated to this object
     *
     * Returns a template object
     *
     * @param string $templateName Template name
     * @return iHMS_Sysconf_Template
     */
    public function setTemplate($templateName)
    {
        // If the template is not changed from the current one, do nothing. This avoids deleting the template entirely
        // by removing its last owner
        $oldTemplate = iHMS_Sysconf_Db::getConfig()->getField($this->_name, 'template');
        $newTemplate = $templateName;

        if (!$oldTemplate || $oldTemplate != $newTemplate) {
            // This question no longer owns the template it used to, if any
            if (!empty($oldTemplate)) {
                iHMS_Sysconf_Db::getTemplates()->removeOwner($oldTemplate, $this->_name);
            }

            iHMS_Sysconf_Db::getConfig()->setField($this->_name, 'template', $newTemplate);

            // Register this question as an owner of the template
            iHMS_Sysconf_Db::getTemplates()->addOwner(
                $newTemplate, $this->_name, iHMS_Sysconf_Db::getTemplates()->getField($newTemplate, 'type')
            );
        }

        return iHMS_Sysconf_Template::get(iHMS_Sysconf_Db::getConfig()->getField($this->_name, 'template'));
    }

    /**
     * Return question name
     *
     * @return string Question name
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Alias of getName()
     *
     * This method exists only to prevent change of question name. Will always return question name set by
     * {@link iHMS_Sysconf_Question::Factory()}
     *
     * @return string
     */
    public function setName()
    {
        return $this->_name;
    }

    /**
     * Returns question priority
     *
     * @return int Question priority
     */
    public function getPriority()
    {
        return $this->_priority;
    }

    /**
     * Sets question priority
     *
     * @param int $priority Priority
     */
    public function setPriority($priority)
    {
        $this->_priority = $priority;
    }

    /**
     * Returns value of the given field
     *
     * Fields are first looked for in the db, and failing that, the associated Template is queried for fields
     *
     * @param string $field Field name
     * @return mixed
     */
    public function __get($field)
    {
        if (method_exists($this, 'get' . $field)) {
            return $this->{'get' . $field}();
        }

        if (!$ret = iHMS_Sysconf_Db::getConfig()->getField($this->_name, $field)) {
            // Fall back to template values
            if ($template = $this->getTemplate()) {
                $ret = $template->{$field};
            }
        }

        if ($ret) {
            if (preg_match('/^(?:description|extended_description|choices)-/i', $field)) {
                return $this->_expandVars($ret);
            } else {
                return $ret;
            }
        }

        return null;
    }

    /**
     * Sets value of the given field
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @return null|string
     */
    public function __set($field, $value)
    {
        if (method_exists($this, 'set' . $field)) {
            return $this->{'set' . $field}($value);
        }

        return iHMS_Sysconf_Db::getConfig()->setField($this->_name, $field, $value);
    }

    /**
     * This is a helper function that expands variables in a string
     *
     * @param string $text Text
     * @return string
     */
    protected function _expandVars($text)
    {
        if (!$text) {
            return '';
        }

        $rest = $text;
        $result = '';

        while (preg_match_all('/^(.*?)(\\\\)?\${([^{}]+)}(.*)$/s', $rest, $m)) {
            $result .= $m[1][0]; // copy anything before the variable
            $escape = $m[2][0];
            $variable = $m[3][0];
            $rest = $m[4][0]; // continue trying to expand rest of text

            if ($escape) {
                // escaped variable is not changed, though the escape is removed
                $result .= "\${$variable}";
            } else {
                if ($varval = iHMS_Sysconf_Db::getConfig()->getVariable($this->_name, $variable)) {
                    $result .= $varval; // expand the variable
                }
            }

            if ($rest == '') {
                break;
            }
        }

        $result .= $rest; // add on anything that's left

        return $result;
    }
}
