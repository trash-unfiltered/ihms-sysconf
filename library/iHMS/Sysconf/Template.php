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
 * @subpackage  Template
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Db */
require_once 'iHMS/Sysconf/Db.php';

/** @see iHMS_Sysconf_Question */
require_once 'iHMS/Sysconf/Question.php';

/** @see iHMS_Sysconf_Log */
require_once 'iHMS/Sysconf/Log.php';

/**
 * iHMS_Sysconf_Template class
 *
 * This is an object that represents a Template. Each Template has some associated data, the fields of the template
 * structure. To get at this data, just use $template->fieldname to read a field, and $template->fieldname = 'value' to
 * write a field. Any field names at all can be used, the convention is to lower-case their names.
 *
 * Note: For fields that don't match with PHP field syntax, use curly brakets around them (eg . $template->{'fieldname'}).
 *
 * Common fields are "default", "type", and "description". The field named "extended_description" holds the extended
 * description, if any.
 *
 * Templates support internationalization. If LANG or a related environment variable is set, and you request a field
 * from a template, it will see if fieldname-$LANG" exists, and if so return that instead
 *
 * @property string description
 * @property string extended_description
 * @property string choices
 * @property string default
 * @property string type
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Template
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @todo stringify (callable in both static and object context)
 */
class iHMS_Sysconf_Template
{
    /**
     * @var iHMS_Sysconf_Template[]
     */
    protected static $_templateInstances = array();

    /**
     * @var bool Whether internationalization is enabled for all templates
     * TODO No implemented yet (FALSE for now as default)
     */
    protected static $_i18n = false;

    /**
     * @var string Template name
     */
    protected $_templateName = null;

    /**
     * @var array An array of known template fields. Others are warned about
     */
    protected static $_kwnowTemplateFields = array(
        'template', 'description', 'choices', 'default', 'type'
    );

    /**
     * Factory for iHMS_Sysconf_Template objects
     *
     * The name of the template to create must be passed to this function. When a new template is created, a question is
     * created with the same name as the template. This is to ensure that the template has at least one owner -- the
     * question, and to make life easier for sysconf users -- so they don't have to manually register that question.
     *
     * The owner field, then, is actually used to set the owner of the question.
     *
     * @static
     * @param string $templateName Template name
     * @param string $owner Template owner
     * @param string $type Template type
     * @return null|iHMS_Sysconf_Template
     */
    public static function factory($templateName, $owner, $type)
    {
        if ($owner == '') {
            $owner = 'unknown';
        }

        // See if we can use an existing template
        if (
            iHMS_Sysconf_Db::getTemplates()->exists($templateName) &&
            !is_null(iHMS_Sysconf_Db::getTemplates()->getOwners($templateName))
        ) {
            // If a question matching this template already exists in the db, add the owner to it. This handles shared
            // owner questions
            if (!is_null($question = iHMS_Sysconf_Question::get($templateName))) {
                $question->addOwner($owner, $type);
            }

            // See if the template claims to own any questions that cannot be found. If so, the database is corrupted;
            // attempt to recover
            $owners = iHMS_Sysconf_Db::getTemplates()->getOwners($templateName);

            foreach ($owners as $question) {
                if (is_null($q = iHMS_Sysconf_Question::get($question))) {
                    iHMS_Sysconf_Log::warn(sprintf('warning: possible database corruption. Will attempt to repair by adding back missing question %s', $question));
                    $newQuestion = iHMS_Sysconf_Question::factory($question, $owner, $type);
                    $newQuestion->setTemplate($templateName);
                }
            }

            $self = new self();
            $self->_templateName = $templateName;

            return self::$_templateInstances[$templateName] = $self;
        }

        // Really making a new template
        $self = new self();
        $self->_templateName = $templateName;

        // Create a question in the database to go with it, unless one with the same name already exists. If one with
        // the same name exists, it may be a shared question so we add the current owner to it.
        if (iHMS_Sysconf_Db::getConfig()->exists($templateName)) {
            if (!is_null($q = iHMS_Sysconf_Question::get($templateName))) {
                $q->addowner($owner, $type);
            }
        } else {
            $q = iHMS_Sysconf_Question::factory($templateName, $owner, $type);
            $q->setTemplate($templateName);
        }


        // This is what actually creates the template in the database
        if (is_null(iHMS_Sysconf_Db::getTemplates()->addOwner($templateName, $templateName, $type))) {
            return null;
        }

        iHMS_Sysconf_Db::getTemplates()->setField($templateName, 'type', $type);

        return self::$_templateInstances[$templateName] = $self;
    }

    /**
     * Get an existing template (it may be pulled out of the database, etc)
     *
     * @static
     * @param string $templateName Template name
     * @return iHMS_Sysconf_Template|null
     */
    public static function get($templateName)
    {
        if (isset(self::$_templateInstances[$templateName])) {
            return self::$_templateInstances[$templateName];
        } elseif (iHMS_Sysconf_Db::getTemplates()->exists($templateName)) {
            $self = new self();
            $self->_templateName = $templateName;
            return self::$_templateInstances[$templateName] = $self;
        } else {
            return null;
        }
    }

    /**
     * Enable or disable templates internationalization
     *
     * This class method controls whether internationalization is enabled for all templates. Sometimes it may be
     * necessary to get at the C values of fields, bypassing internationalization. To enable this, set i18n to a false
     * value. This is only for when you explicitly want an untranslated version (which may not be suitable for display),
     * not merely for when a C locale is in use
     *
     * @static
     * @param bool $mode If TRUE, enable internationalization for all templates
     */
    public static function setI18n($mode)
    {
        self::$_i18n = (bool)$mode;
    }

    /**
     * Load templates from the given templates file/resource
     *
     * This class method reads a templates file, instantiates a template for each item in it, and returns all the
     * instantiated templates. Pass it the file to load (or an already open FileHandle) and the template owner.
     *
     * @static
     * @throws InvalidArgumentException in case templates file cannot be opened
     * @throws DomainException in case templates file doesn't not fit with expected syntax
     * @param string|resource $templatesFile Either a string representing a templates file or a resource of templates
     *                                      file already opened
     * @param string $templateOwner Templates owner (eg: The module name that the templates file belongs)
     * @return iHMS_Sysconf_Template[]
     */
    public static function load($templatesFile, $templateOwner)
    {
        $ret = array();

        if (is_resource($templatesFile)) {
            $fh = $templatesFile;
        } elseif (!$fh = @fopen($templatesFile, 'r')) {
            throw new InvalidArgumentException("{$templatesFile}: {$php_errormsg}");
            //fwrite(STDERR, "{$templateFile}: {$php_errormsg}");
            //exit(1);
        }

        fseek($fh, 0, SEEK_END);
        $length = ftell($fh);
        rewind($fh);

        $stanza = 1;

        while ($line = stream_get_line($fh, $length, "\n\n")) {
            // Parse the data into an array structure
            $data = array();

            // Sets a field to a value in the array, with sanity checking
            $save = function($field, $value, $extended, $templateFile) use(&$data, $stanza)
            {
                // Make sure there are no blank lines at the end of the extended field, as that causes problems when
                // stringifying and elsewhere, and is pointless anyway
                $extended = rtrim($extended, "\n");

                if ($field != '') {
                    if (isset($data[$field])) {
                        throw new DomainException(
                            sprintf("Template %s in %s has a duplicate field \"%s\" with new value \"%s\". Probably two templates are not properly separated by a lone newline.\n", $stanza, $templateFile, $field, $value)
                        );
                        //fwrite(STDERR, sprintf("Template %s in %s has a duplicate field \"%s\" with new value \"%s\". Probably two templates are not properly separated by a lone newline.\n", $stanza, $templateFile, $field, $value));
                        //exit(1);
                    }

                    $data[$field] = $value;

                    if (!empty($extended)) {
                        $data['extended_' . $field] = $extended;
                    }
                }
            };

            // Ignore any number of leading and trailing newlines
            $line = trim($line, "\n");

            $field = $value = $extended = '';

            foreach (explode("\n", $line) as $line) {
                $line = rtrim($line, "\n");

                if (preg_match('/^([-_@.A-Za-z0-9]*):\s?(.*)/', $line, $match)) {
                    // Beginning of new field. First, save the old one
                    $save($field, $value, $extended, $templatesFile);

                    $field = strtolower($match[1]);
                    $value = preg_replace('/\s*$/', '', $match[2]); // TODO (PO) rtrim() should be sufficient here
                    $extended = '';
                    $basefield = preg_replace('/-.+$/', '', $field);

                    if (!in_array($basefield, self::$_kwnowTemplateFields)) {
                        iHMS_Sysconf_Log::warn(sprintf("warning: Unknown template field %s in stanza %d of %s\n", $stanza, $field, $templatesFile));
                    }
                } elseif (preg_match('/^\s\.$/', $line)) { // TODO (PO) ltrim($line) == '.'
                    // Continuation of field that contains only a blank line
                    $extended .= "\n\n";
                } elseif (preg_match('/^\s(\s+.*)/', $line, $match)) {
                    // Continuation of field, with a doubly indented bit that should not be wrapped
                    $bit = preg_replace('/\s*$/', '', $match[1]);

                    // If extended field part do not hold line feed or space at end, add line feed
                    if (!empty($extended) && !preg_match('/[\n ]$/', $extended)) {
                        $extended .= "\n";
                    }

                    $extended .= $bit . "\n";
                } elseif (preg_match('/^\s(.*)/', $line, $match)) {
                    // Continuation of field
                    $bit = preg_replace('/\s*$/', '', $match[1]);

                    // If extended field part do not hold line feed or space at end, add line feed
                    if (!empty($extended) && !preg_match('/[\n ]$/', $extended)) {
                        $extended .= ' ';
                    }

                    $extended .= $bit;
                } else {
                    throw new DomainException(sprintf("Template parse error near `%s', in stanza %d of %s\n", $stanza, $line, $templatesFile));
                    //fwrite(STDERR, sprintf("Template parse error near `%s', in stanza %d of %s\n", $stanza, $line, $templatesFile));
                    //exit(1);
                }
            } // end-foreach();

            $save($field, $value, $extended, $templatesFile);

            // Sanity checks
            if (!isset($data['template'])) {
                throw new DomainException(sprintf("Template %d in %s does not contain a 'Template:' line\n", $stanza, $templatesFile));
                //fwrite(STDERR, sprintf("Template %d in %s does not contain a 'Template:' line\n", $stanza, $templatesFile));
                //exit(1);
            }

            // Create and populate template from the array
            $template = self::factory($data['template'], $templateOwner, $data['type']);

            // Ensure template is empty, then fill with new data
            $template->clearAll();

            foreach ($data as $key => $value) {
                if ($key == 'template') {
                    continue;
                }

                $template->{$key} = $value;
            }

            $ret[] = $template;
            $stanza++;
        } // end-while();

        return $ret;
    } // End load();

    /**
     * Returns template name
     *
     * @return string Template name
     * TODO check if we must rename that method to template and make it callable via common setter
     */
    public function getName()
    {
        return $this->_templateName;
    }

    /**
     * Returns a list of all fields that are present in the object
     *
     * @return array|null List of all fields that are present in the object or NULL if the item is not found
     */
    public function getFields()
    {
        return iHMS_Sysconf_Db::getTemplates()->getFields($this->_templateName);
    }

    /**
     * Clears all the fields of the object
     */
    public function clearAll()
    {
        if (!is_null($fields = $this->getFields())) {
            foreach ($fields as $field) {
                iHMS_Sysconf_Db::getTemplates()->removeField($this->_templateName, $field);
            }
        }
    }

    /**
     * Provides accessors (getters) for templates fields
     *
     * @param string $fieldName
     * @return string
     */
    public function __get($fieldName)
    {
        $ret = null;
        $wanti18n = self::$_i18n && iHMS_Sysconf_Config::getInstance()->cValues != true;
        $langs = array(); // Todo get languages list;

        # Check to see if i18n and/or charset encoding should be used.
        if ($wanti18n && !empty($lang)) {
            foreach ($langs as $lang) {
                # Avoid displaying Choices-C values
                if ($lang == 'c') {
                    $lang = 'en';
                }

                // First check for a field that matches the language and the encoding. No charset conversion is needed.
                // This also takes care of the old case where encoding is not specified
                if (!is_null($ret = iHMS_Sysconf_Db::getTemplates()->getField($this->_templateName, $fieldName))) {
                    return $ret;
                }

                // Failing that, look for a field that matches the language, and do charset conversion
                // TODO

                // For en, force the default template if no language-specific template was found, since English text is
                // usually found in a plain field rather than something like Choices-en.UTF-8. This allows you to
                // override other locale variables for a different language with LANGUAGE=en.
                if ($lang == 'en') {
                    break;
                }
            }
        } elseif (!$wanti18n && !preg_match('/-c$/i', $fieldName)) {
            // If i18n is turned off, try *-C first
            if (!is_null($ret = iHMS_Sysconf_Db::getTemplates()->getField($this->_templateName, $fieldName . '-c'))) {
                return $ret;
            }
        }

        if (!is_null($ret = iHMS_Sysconf_Db::getTemplates()->getField($this->_templateName, $fieldName))) {
            return $ret;
        }

        // If the user asked for a language-specific field, fall back to the unadorned field. This allows *-C to be used
        // to force untranslated data, and *-* to fall back to untranslated data if no translation is available
        if (strpos($fieldName, '-') !== false) {
            $plainfield = preg_replace('/-.*/', '', $fieldName);

            if (!is_null($ret = iHMS_Sysconf_Db::getTemplates()->getField($this->_templateName, $plainfield))) {
                return $ret;
            }

            return '';
        }

        return '';
    }

    /**
     * Provides setters for templates fields
     *
     * @param string $fieldName
     * @param string $value
     * @return string|null Value set or NULL if setting failed
     */
    public function __set($fieldName, $value)
    {
        return iHMS_Sysconf_Db::getTemplates()->setField($this->_templateName, $fieldName, $value);
    }

    /**
     * Return template name
     *
     * @return null|string
     */
    public function __toString()
    {
        return $this->_templateName;
    }
}
