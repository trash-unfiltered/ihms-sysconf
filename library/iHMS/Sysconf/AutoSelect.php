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
 * @subpackage  AutoSelect
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

namespace iHMS\Sysconf;

/**
 * AutoSelect class
 *
 * This library makes it easy to create FrontEnd and ConfModule objects. It starts with the desired type of object, and
 * tries to make it. If that fails, it progressively falls back to other types in the list.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  AutoSelect
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
class AutoSelect
{
    /**
     * @var array Map for prefered frontend to their fallback
     */
    protected static $fallback = array(
        'Web' => array('Dialog', 'Readline', 'Teletype'),
        'Dialog' => array('Readline', 'Teletype'),
        'Readline' => array('Teletype', 'Dialog'),
        'Editor' => array('Readline', 'Teletype'),
    );

    /**
     * @var Frontend
     */
    static public $frontend = null;

    /**
     * @var string Frontend type
     */
    static public $type = null;

    /**
     * Create and returns a FrontEnd object
     *
     * The type of FrontEnd used varies. It will try the preferred type first, and if that fails, fall back through
     * other types, all the way to a Noninteractive frontend if all else fails.
     *
     * @static
     * @throws \ErrorException in case frontend cannot be started
     * @return Frontend
     */
    public static function frontendFactory()
    {
        if (!is_null(self::$type)) {
            $starttype = ucfirst(self::$type);
        }

        if (!isset($starttype) || $starttype == '') {
            $starttype = Config::getInstance()->frontend;

            if (preg_match('/^[A-Z]/', $starttype)) {
                Log::warn(_('Please do not capitalize the first letter of the sysconf frontend.'));
            }

            $starttype = ucfirst($starttype);
        }

        $showfallback = false;

        if (array_key_exists($starttype, self::$fallback)) {
            $frontendTypes = array($starttype) + self::$fallback[$starttype];
        } else {
            $frontendTypes[] = $starttype;
        }

        $frontendTypes[] = 'Noninteractive';

        foreach ($frontendTypes as $type) {
            if (!$showfallback) {
                Log::debug('user', 'Trying frontend ' . $type);
            } else {
                Log::warn(sprintf(_('Falling back to frontend %s'), $type));
            }

            try {
                $frontend = '\\iHMS\\Sysconf\\Frontend\\' . $type;
                @\Zend_Loader::loadClass($frontend);
                return self::$frontend = new $frontend();
            } catch (\Zend_Exception $e) {
                Log::warn(sprintf(_('Unable to initialize frontend %s'), $type));
                Log::warn($e->getMessage());
            }

            $showfallback = true;
        }

        /** @var $e \Zend_Exception */
        throw new \ErrorException(sprintf(_('Unable to start a frontend %s') . $e->getMessage()) . "\n");
    }

    /**
     * Create and returns a confModule object
     *
     * Pass the script (if any) the ConfModule will start up, (and optional arguments to pass to it) and this creates
     * and returns a ConfModule
     *
     * @static
     * @param mixed $parameters,... OPTIONAL Arguments to pass to the confmodule
     * @return ConfModule
     */
    public static function confModuleFactory($parameters = null)
    {
        $confmodule = new ConfModule(array('frontend' => self::$frontend));

        if (!is_null($parameters)) {
            if (is_array($parameters)) {
                $parameters = func_get_arg(0);
            } else {
                $parameters = func_get_args();
            }

            call_user_func_array(array($confmodule, 'startup'), $parameters);
        }

        return $confmodule;
    }
}
