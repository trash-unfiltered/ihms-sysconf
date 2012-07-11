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
 * @subpackage  Config
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/** @see iHMS_Sysconf_Log */
require_once 'iHMS/Sysconf/Log.php';

/**
 * iHMS_Sysconf_Config class
 *
 * This class holds configuration values for sysconf. It supplies defaults, and allows them to be overridden by values
 * from the command line, the  environment, the config file, and values pulled out of the sysconf database.
 *
 * @property string $config
 * @property string $templates
 * @property string $frontend
 * @property string $frontendForced Tells if frontend was forced
 * @property string $priority Question priority
 * @property string $terse
 * @property string $reShow
 * @property string $adminEmail Admin Email
 * @property string $log Value used as regexp to filter log messages
 * @property string $debug Value used as regexp to filter log messages
 * @property string $noWarnings (yes|no) Tells whethers or not warnings must be hidden
 * @property string $nonInteractiveSeen
 * @property string $cValues
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Config
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Config
{
    /**
     * @var iHMS_Sysconf_Config
     */
    protected static $_instance = null;

    /**
     * @var array Holds sysconf config file paths
     */
    protected static $_configFiles = array(
        '/usr/local/etc/ihms/sysconf.conf',
        '/usr/local/share/ihms/sysconf/sysconf.conf'
    );

    /**
     * @var array Holds configuration values
     */
    protected $_config = array(
        'config' => '',
        'templates' => '',
        'frontend' => '',
        'frontendForced' => '',
        'priority' => '',
        'terse' => '',
        'reShow' => '',
        'adminEmail' => '',
        'log' => '',
        'debug' => '',
        'noWarnings' => '',
        'nonInteractiveSeen' => '',
        'cValues' => '',
    );

    /**
     * Make new unavailable
     */
    protected function __construct()
    {
    }

    /**
     * Make clone unavailable
     */
    protected function __clone()
    {
    }

    /**
     * Implements singleton design pattern
     *
     * @return iHMS_Sysconf_Config
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            if ($env = getenv('SYSCONF_SYSTEMRC')) {
                array_unshift(self::$_configFiles, $env);
            }

            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Reads and parses a config file.
     *
     * The config file format is a series of stanzas; the first stanza configures sysconf as a whole, and then each of
     * the rest sets up a database driver. It will load from a set of standard locations unless a file to load is
     * specified as the first parameter. If an associative array of parameters are passed, those parameters are used as
     * the defaults for *every* database driver that is loaded up. Practically, setting  (readonly => "true") is the
     * only use of this.
     *
     * @throws InvalidArgumentException in case no configuration is found or setting database failed
     * @throws RuntimeException in case configuration file cannot be opened
     * @throws DomainException in case config or template database is not specified in configuration file
     * @param string $configFile Configuration file path
     * @param array $defaults Default parameter
     * @return iHMS_Sysconf_Config Provides fluent interface, return self
     */
    public function load($configFile, array $defaults = array())
    {
        if (!$configFile) {
            foreach (self::$_configFiles as $file) {
                $configFile = $file;
                if (file_exists($file)) {
                    break;
                }
            }
        }

        if (!$configFile) {
            throw new InvalidArgumentException("No configuration file found.\n");
            //fwrite(STDERR, "No configuration file found.\n");
            //exit(1);
        }

        if (!$fhSysconfConfig = @fopen($configFile, 'r')) {
            throw new RuntimeException("$php_errormsg\n");
            //fwrite(STDERR, "$php_errormsg\n");
            //exit(1);
        }

        // Read global options stanza
        fseek($fhSysconfConfig, 0, SEEK_END);
        $length = ftell($fhSysconfConfig);
        rewind($fhSysconfConfig);
        $stanza = 1;

        // TODO try flip condionnal statement here (feof first)
        while (!self::_toArray(stream_get_line($fhSysconfConfig, $length, "\n\n"), $this->_config) && !feof($fhSysconfConfig)) {
            $stanza++;
        }

        # Verify that all options are sane
        if ($this->_config['config'] == '') {
            throw new DomainException("sysconf: Config database not specified in config file.\n");
            //fwrite(STDERR, "sysconf: Config database not specified in config file.\n");
            //exit(1);
        }
        if ($this->_config['templates'] == '') {
            throw new DomainException("sysconf: Templates database not specified in config file.\n");
            //fwrite(STDERR, "sysconf: Templates database not specified in config file.\n");
            //exit(1);
        }

        // Now read in each database driver, and set it up
        while (!feof($fhSysconfConfig)) {
            $config = $defaults;

            if (!self::_toArray(stream_get_line($fhSysconfConfig, $length, "\n\n"), $config)) {
                continue;
            }

            try {
                iHMS_Sysconf_Db::makeDriver($config);
            } catch (InvalidArgumentException $e) {
                fwrite(
                    STDERR,
                    sprintf(
                        'sysconf: Problem setting up the database defined by configuration stanza %d in %s : %s.',
                        $stanza, $configFile
                    ) . "\n"
                );

                throw new $e;
            }

            $stanza++;
        }

        fclose($fhSysconfConfig);

        return $this;
    }

    /**
     * Parse commnand line options in $_SERVER['argv'] with iHMS_Sysconf_Getopt
     *
     * Many meta configuration items can be overridden with command line options.
     *
     * The first parameter should be basic usage text for the program in question. Usage text for the globally supported
     * options will be prepended to this if usage help must be printed.
     *
     * If any additonal rules are passed to this function, they are also passed to Getopt. This can be used to handle
     * additional options.
     *
     * @param string $usage Basic usage text for the program in question.
     * @param array $rules OPTIONAL Additional rules to pass to Getopt
     * @return iHMS_Sysconf_Config Provides fluent interface, returns self
     */
    public function getopt($usage, array $rules = array())
    {
        /** @see Zend_Console_Getopt */
        require_once 'iHMS/Sysconf/Getopt.php';

        $getOpt = new iHMS_Sysconf_Getopt(array(), null, array('parseAll' => false));

        $options = array(
            'frontend|f=s' => array(function($p)
            {
                iHMS_Sysconf_Config::getInstance()->frontend($p);
                iHMS_Sysconf_Config::getInstance()->frontendForced(true);
            }, 'Specify sysconf frontend to use.'),
            'priority|p=s' => array(function($p)
            {
                iHMS_Sysconf_Config::getInstance()->priority($p);
            }, 'Specify minimum priority question to show.'),
            'help|h' => array(function() use($getOpt, $usage)
            {
                /** @var $getOpt iHMS_Sysconf_Getopt */
                fwrite(STDERR, "$usage\n" . $getOpt->getUsageMessage());
                exit(0);
            }, 'Show this help.'),
        );

        $options = $options + $rules;

        // Build rules for iHMS_Sysconf_Getopt
        foreach ($options as $k => $v) {
            if (is_array($v)) {
                $option = array($k => array_pop($v));
            } else {
                $option = array($k => $v);
            }

            $getOpt->addRules($option);
        }

        try {
            /** @var $option string */
            foreach (array_keys($options) as $option) {
                $loption = preg_replace('/^(\w+)(?:[\|=].*)?/', '$1', $option);

                if ($value = $getOpt->getOption($loption)) {
                    // Handle extended behavior (no part of iHMS_Sysconf_Getopt)
                    // TODO move that part into the iHMS_Sysconf_Getopt class
                    if (is_array($options[$option])) {
                        if ($options[$option][0] instanceof Closure) {
                            $options[$option][0]($value);
                        } else {
                            $options[$option][0] = $value;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            fwrite(STDERR, $e->getMessage() . "\n" . "$usage\n" . $getOpt->getUsageMessage() . "\n");
            exit(1);
        }

        return $this;
    }

    /**
     * Frontend to use
     *
     * Looks at first the value of SYSCONF_FRONTEND, second the config file, third the database, and if all of those
     * fail, defaults to the dialog frontend.
     *
     * If a value is passed to this method, it changes it temporarily (for the lifetime of the program) to override
     * what's in the database or config file.
     *
     * @param string $frontend Frontend to use
     * @return string Frontend to use
     */
    public function frontend($frontend = '')
    {
        if (($env = getenv('SYSCONF_FRONTEND')) !== false) {
            return $env;
        }

        if (!is_null($frontend)) {
            $this->_config['frontend'] = $frontend;
        }

        if ($this->_config['frontend'] != '') {
            return $this->_config['frontend'];
        }

        $ret = 'dialog';

        if (!is_null($question = iHMS_Sysconf_Question::get('sysconf/question'))) {
            $ret = lcfirst($question->getValue()) ? : $ret;
        }

        return $ret;
    }

    /**
     * Whether the frontend was forced set on the command line or in the environment
     *
     * @param bool $value
     * @return bool
     */
    public function frontendForced($value = null)
    {
        if (isset($value) || getenv('SYSCONF_FRONTEND') !== false) {
            $this->_config['frontendForced'] = $value;
        }

        return $this->_config['frontendForced'] ? true : false;
    }

    /**
     * The lowest priority of questions you want to see
     *
     * Looks at first the value of SYSCONF_PRIORITY, second the config file, third the database, and if all of those
     * fail, defaults to "high".
     *
     * If a value is passed to this function, it changes it temporarily (for the lifetime of the program) to override
     * what's in the database or config file.
     *
     * @param string $priority priority value which will override what's in the database or config file
     * @return string Current priority
     */
    public function priority($priority = null)
    {
        if (($env = getenv('SYSCONF_PRIORITY')) !== false) {
            return $env;
        }

        if (!is_null($priority)) {
            if (!iHMS_Sysconf_Priority::isValidPriority($priority)) {
                iHMS_Sysconf_Log::warn("Ignoring invalid priority {$priority}");
                iHMS_Sysconf_Log::warn(sprintf('Valid priorities are "%s"' . "\n", join(' ', iHMS_Sysconf_Priority::getPriorityList())));
            } else {
                $this->_config['priority'] = $priority;
            }
        }

        if ($this->_config['priority'] != '') {
            return $this->_config['priority'];
        }

        $ret = 'high';

        if ($question = iHMS_Sysconf_Question::get('sysconf/priority')) {
            $ret = $question->getValue() ? : $ret;
        }

        return $ret;
    }

    /**
     * Sets terse mode
     *
     * The behavior in terse mode varies by frontend. Changes to terse mode are not persistant across sysconf
     * invocations.
     *
     * @param string $value
     * @return string
     */
    public function terse($value = null)
    {
        if (($env = getenv('SYSCONF_TERSE')) !== false) {
            return $env;
        }

        if (!is_null($value)) {
            $this->_config['terse'] = $value;
        }

        if ($this->_config['terse'] != '') {
            return $this->_config['terse'];
        }

        return 'false';
    }

    /**
     * Set to disable warnings
     *
     * @param string $value
     * @return string
     */
    public function noWarnings($value = null)
    {
        if (($env = getenv('SYSCONF_NOWARNINGS')) !== false) {
            return $env;
        }

        if (!is_null($value)) {
            $this->_config['noWarnings'] = $value;
        }

        if ($this->_config['noWarnings'] != '') {
            return $this->_config['noWarnings'];
        }

        return 'false';
    }


    /**
     * Returns sysconf's debug regexp
     *
     * This is pulled out of the config file, and may be overridden by SYSCONF_DEBUG in the environment
     *
     * @return string Debug regexp
     */
    public function debug()
    {
        if (($env = getenv('SYSCONF_DEBUG')) !== false) {
            return $env;
        }

        return $this->_config['debug'];
    }

    /**
     * Returns an email address to use to send notes to.
     *
     * This is pulled out of the config file, and may be overridden by the SYSCONF_ADMIN_MAIL environment variable.
     * If neither is set, it defaults to root.
     *
     * @return string Admin email
     */
    public function adminEmail()
    {
        if (($env = getenv('SYSCONF_ADMIN_EMAIL')) !== false) {
            return $env;
        }

        if ($this->_config['adminEmail'] != '') {
            return $this->_config['adminEmail'];
        }

        return 'root';
    }

    /**
     * Set to cause the seen flag to be set for questions asked in the noninteractive frontend
     */
    public function nonInteractiveSeen()
    {
        if (($env = getenv('SYSCONF_NOINTERACTIVE_SEEN')) !== false) {
            return $env;
        }

        if ($this->_config['nonInteractiveSeen'] != '') {
            return $this->_config['nonInteractiveSeen'];
        }

        return 'false';
    }

    /**
     * Set to 'true' to display "coded" values from Choices-C fields instead of the descriptive values from other
     * fields for select and multiselect templates
     *
     * @return string
     */
    public function cValues()
    {
        if (($env = getenv('SYSCONF_C_VALUES')) !== false) {
            return $env;
        }

        if ($this->_config['cValues'] != '') {
            return $this->_config['cValues'];
        }

        return 'false';
    }

    /**
     * Returns value of the given field
     *
     * @throws InvalidArgumentException in case property is unknown
     * @param string $field Field name
     * @return string Field value
     */
    public function __get($field)
    {
        if (method_exists($this, $field)) {
            $ret = $this->{$field}();
        } elseif (isset($this->_config[$field])) {
            $ret = $this->_config[$field];
        } else {
            throw new InvalidArgumentException("Attempt to access unknown property '{$field}' at " . __FILE__ . ' line ' . __LINE__ . "\n");
            //fwrite(STDERR, "Attempt to access unknown property '{$field}' at " . __FILE__ . ' line ' . __LINE__ . "\n");
            //exit(1);
        }

        return $ret;
    }

    /**
     * Sets value of the given field
     *
     * @param string $field Field name
     * @param string $value Field value
     * @return string Value set
     */
    public function __set($field, $value)
    {
        if (method_exists($this, $field)) {
            return $this->{$field}($value);
        } else {
            return $this->_config[$field] = $value;
        }
    }

    /**
     * Turns a chunk of text into an array
     *
     * Returns number of fields that were processed. Also handles environment variable expansion.
     *
     * @throws DomainException in case error is encountered during parsing
     * @param string $text Chunk of text
     * @param array &$config Reference to config array
     * @return int Number of fields that were processed
     */
    protected function _toArray($text, &$config = null)
    {
        // handles env variable expansion
        $text = preg_replace_callback(
            '/\$\{([^}]+)\}/', function($m)
            {
                return (($env = getenv($m[1])) !== false) ? $env : $m[0];
            }, $text
        );

        $i = 0;

        require_once 'Zend/Filter/Word/UnderscoreToCamelCase.php';
        $filter = new Zend_Filter_Word_UnderscoreToCamelCase();

        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^\s*#|^\s*$/', $line)) {
                continue; // comment or blank
            }

            $line = preg_replace(array('/^\s+/', '/\s+$/'), '', $line);
            $i++;

            list($key, $value) = preg_split('/\s*:\s*/', $line, 2);
            $key = str_replace('-', '_', $key);

            if (!$key) {
                throw new DomainException("Error while parsing configuration file.\n");
                //fwrite(STDERR, "Error while parsing configuration file.\n");
                //exit(1);
            }

            $config[lcfirst($filter->filter($key))] = $value;
        }

        return $i;
    }
}
