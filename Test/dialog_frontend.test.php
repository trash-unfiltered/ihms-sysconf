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

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('track_errors', 1);

set_include_path(
    dirname(__DIR__) . '/library' . PATH_SEPARATOR .
        //'/usr/share/php/libzend-framework-php' . PATH_SEPARATOR .
        get_include_path()
);

spl_autoload_register(
    function($className)
    {
        require_once str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
    }
);

/** @see iHMS_Sysconf_Config */
require_once 'iHMS/Sysconf/Config.php';

/** @see iHMS_Sysconf_Frontend */
require_once 'iHMS/Sysconf/Frontend.php';

/** @see iHMS_Sysconf_Frontend_Dialog  */
require_once 'iHMS/Sysconf/Frontend/Dialog.php';

/** @see iHMS_Sysconf_Question  */
require_once 'iHMS/Sysconf/Question.php';

putenv('SYSCONF_SYSTEMRC=/home/nuxwin/Bureau/working/sysconf/library/iHMS/Sysconf/sysconf.conf');

// Load config
iHMS_Sysconf_Config::getInstance()->load('');


/**
 * Question_Mock class
 */
class Question_Mock extends iHMS_Sysconf_Question
{
    public static function factory($name, $owner, $type)
    {
        $self = new self();
        $self->_name = $name;
        return self::$_questions[$name] = $self;
    }

    public function addOwner($owner, $type)
    {
        return $owner;
    }
}

$text =
    "English - Debian is a free operating system (OS) for your computer. An operating system is the set of basic programs and utilities that make your computer run.\n\n" .
        "Greek - \xce\xa4\xce\xbf Debian \xce\xb5\xce\xaf\xce\xbd\xce\xb1\xce\xb9 \xce\xad\xce\xbd\xce\xb1 \xce\xb5\xce\xbb\xce\xb5\xcf\x8d\xce\xb8\xce\xb5\xcf\x81\xce\xbf \xce\xbb\xce\xb5\xce\xb9\xcf\x84\xce\xbf\xcf\x85\xcf\x81\xce\xb3\xce\xb9\xce\xba\xcf\x8c \xcf\x83\xcf\x8d\xcf\x83\xcf\x84\xce\xb7\xce\xbc\xce\xb1 (\xce\x9b\xce\xa3) \xce\xb3\xce\xb9\xce\xb1 \xcf\x84\xce\xbf\xce\xbd \xcf\x85\xcf\x80\xce\xbf\xce\xbb\xce\xbf\xce\xb3\xce\xb9\xcf\x83\xcf\x84\xce\xae \xcf\x83\xce\xb1\xcf\x82. \xce\xa4\xce\xbf \xce\xbb\xce\xb5\xce\xb9\xcf\x84\xce\xbf\xcf\x85\xcf\x81\xce\xb3\xce\xb9\xce\xba\xcf\x8c \xcf\x83\xcf\x8d\xcf\x83\xcf\x84\xce\xb7\xce\xbc\xce\xb1 \xce\xb5\xce\xaf\xce\xbd\xce\xb1\xce\xb9 \xce\xbc\xce\xb9\xce\xb1 \xcf\x83\xcf\x85\xce\xbb\xce\xbb\xce\xbf\xce\xb3\xce\xae \xce\xb1\xcf\x80\xce\xbf \xcf\x84\xce\xb1 \xce\xb1\xcf\x80\xce\xb1\xcf\x81\xce\xb1\xce\xaf\xcf\x84\xce\xb7\xcf\x84\xce\xb1 \xcf\x80\xcf\x81\xce\xbf\xce\xb3\xcf\x81\xce\xac\xce\xbc\xce\xbc\xce\xb1\xcf\x84\xce\xb1 \xce\xba\xce\xb1\xce\xb9 \xce\xb2\xce\xbf\xce\xb7\xce\xb8\xce\xae\xce\xbc\xce\xb1\xcf\x84\xce\xb1 \xcf\x80\xce\xbf\xcf\x85 \xce\xb1\xcf\x80\xce\xb1\xce\xb9\xcf\x84\xce\xb5\xce\xaf \xce\xbf \xcf\x85\xcf\x80\xce\xbf\xce\xbb\xce\xbf\xce\xb3\xce\xb9\xcf\x83\xcf\x84\xce\xae\xcf\x82 \xcf\x83\xce\xb1\xcf\x82 \xce\xb3\xce\xb9\xce\xb1 \xce\xbd\xce\xb1 \xce\xbb\xce\xb5\xce\xb9\xcf\x84\xce\xbf\xcf\x85\xcf\x81\xce\xb3\xce\xae\xcf\x83\xce\xb5\xce\xb9.\n\n" .
        "Japanese - Debian \xe3\x81\xaf\xe3\x81\x82\xe3\x81\xaa\xe3\x81\x9f\xe3\x81\xae\xe3\x82\xb3\xe3\x83\xb3\xe3\x83\x94\xe3\x83\xa5\xe3\x83\xbc\xe3\x82\xbf\xe3\x81\xae\xe3\x81\x9f\xe3\x82\x81\xe3\x81\xae\xe3\x83\x95\xe3\x83\xaa\xe3\x83\xbc\xe3\x81\xaa\xe3\x82\xaa\xe3\x83\x9a\xe3\x83\xac\xe3\x83\xbc\xe3\x83\x86\xe3\x82\xa3\xe3\x83\xb3\xe3\x82\xb0\xe3\x82\xb7\xe3\x82\xb9\xe3\x83\x86\xe3\x83\xa0 (OS) \xe3\x81\xa7\xe3\x81\x99\xe3\x80\x82\xe3\x82\xaa\xe3\x83\x9a\xe3\x83\xac\xe3\x83\xbc\xe3\x83\x86\xe3\x82\xa3\xe3\x83\xb3\xe3\x82\xb0\xe3\x82\xb7\xe3\x82\xb9\xe3\x83\x86\xe3\x83\xa0\xe3\x81\xaf\xe3\x80\x81\xe3\x82\xb3\xe3\x83\xb3\xe3\x83\x94\xe3\x83\xa5\xe3\x83\xbc\xe3\x82\xbf\xe3\x82\x92\xe5\x8b\x95\xe4\xbd\x9c\xe3\x81\x95\xe3\x81\x9b\xe3\x82\x8b\xe3\x81\x9f\xe3\x82\x81\xe3\x81\xab\xe5\xbf\x85\xe8\xa6\x81\xe3\x81\xaa\xe5\x9f\xba\xe6\x9c\xac\xe3\x83\x97\xe3\x83\xad\xe3\x82\xb0\xe3\x83\xa9\xe3\x83\xa0\xe3\x81\xa8\xe3\x83\xa6\xe3\x83\xbc\xe3\x83\x86\xe3\x82\xa3\xe3\x83\xaa\xe3\x83\x86\xe3\x82\xa3\xe3\x81\xae\xe9\x9b\x86\xe5\x90\x88\xe4\xbd\x93\xe3\x81\xa7\xe3\x81\x99\xe3\x80\x82\n\n" .
        "Korean - \xeb\x8d\xb0\xeb\xb9\x84\xec\x95\x88\xec\x9d\x80 \xec\x97\xac\xeb\x9f\xac\xeb\xb6\x84\xec\x9d\x98 \xec\xbb\xb4\xed\x93\xa8\xed\x84\xb0\xeb\xa5\xbc \xec\x9c\x84\xed\x95\x9c \xec\x9e\x90\xec\x9c\xa0 \xec\x9a\xb4\xec\x98\x81\xec\xb2\xb4\xec\xa0\x9c\xec\x9e\x85\xeb\x8b\x88\xeb\x8b\xa4. \xec\x9a\xb4\xec\x98\x81\xec\xb2\xb4\xec\xa0\x9c\xeb\x9e\x80 \xec\xbb\xb4\xed\x93\xa8\xed\x84\xb0\xeb\xa5\xbc \xeb\x8f\x99\xec\x9e\x91\xec\x8b\x9c\xed\x82\xa4\xeb\x8a\x94 \xea\xb8\xb0\xeb\xb3\xb8\xec\xa0\x81\xec\x9d\xb8 \xed\x94\x84\xeb\xa1\x9c\xea\xb7\xb8\xeb\x9e\xa8\xea\xb3\xbc \xec\x9c\xa0\xed\x8b\xb8\xeb\xa6\xac\xed\x8b\xb0\xeb\xa5\xbc \xed\x95\x98\xeb\x82\x98\xeb\xa1\x9c \xeb\xac\xb6\xec\x9d\x80 \xea\xb2\x83\xec\x9e\x85\xeb\x8b\x88\xeb\x8b\xa4.\n\n" .
        "Russian - Debian -- \xd1\x8d\xd1\x82\xd0\xbe \xd1\x81\xd0\xb2\xd0\xbe\xd0\xb1\xd0\xbe\xd0\xb4\xd0\xbd\xd0\xb0\xd1\x8f \xd0\xbe\xd0\xbf\xd0\xb5\xd1\x80\xd0\xb0\xd1\x86\xd0\xb8\xd0\xbe\xd0\xbd\xd0\xbd\xd0\xb0\xd1\x8f \xd1\x81\xd0\xb8\xd1\x81\xd1\x82\xd0\xb5\xd0\xbc\xd0\xb0 (\xd0\x9e\xd0\xa1) \xd0\xb8 \xd0\xbd\xd0\xb0\xd0\xb1\xd0\xbe\xd1\x80 \xd0\xbf\xd1\x80\xd0\xb8\xd0\xba\xd0\xbb\xd0\xb0\xd0\xb4\xd0\xbd\xd1\x8b\xd1\x85 \xd0\xbf\xd1\x80\xd0\xbe\xd0\xb3\xd1\x80\xd0\xb0\xd0\xbc\xd0\xbc \xd0\xb4\xd0\xbb\xd1\x8f \xd0\xb2\xd0\xb0\xd1\x88\xd0\xb5\xd0\xb3\xd0\xbe \xd0\xba\xd0\xbe\xd0\xbc\xd0\xbf\xd1\x8c\xd1\x8e\xd1\x82\xd0\xb5\xd1\x80\xd0\xb0.\n\n";


// Create question mock object
$question = Question_Mock::factory('Test', 'test', 'text');

// Using whiptail (if available)
putenv('SYSCONF_FORCE_DIALOG');

$frontend = new iHMS_Sysconf_Frontend_Dialog();
$frontend->defaultTitle('Test using whiptail');
$frontend->showText($question, "This is a little test to show a text using whiptail frontend.\n\n{$text}");

// Using dialog (if available)
putenv('SYSCONF_FORCE_DIALOG=1');
$frontend = new iHMS_Sysconf_Frontend_Dialog();
$frontend->defaultTitle('Test using dialog');
$frontend->showText($question, "This is a little test to show a text using whiptail frontend.\n\n{$text}");

exit(0);