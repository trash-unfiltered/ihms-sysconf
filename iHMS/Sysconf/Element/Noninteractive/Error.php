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
 * @subpackage  Element_Noninteractive
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

namespace iHMS\Sysconf\Element\Noninteractive;

use iHMS\Sysconf\Element\AbstractNoninteractive;
use iHMS\Sysconf\Encoding;
use iHMS\Sysconf\Config;
use iHMS\Sysconf\Log;

/**
 * Error class
 *
 * This is a noninteractive error message element. Since we are running non-interactively, we can't pause to show the
 * error messages. Instead, they are mailed to someone
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Element_Noninteractive
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
class Error extends AbstractNoninteractive
{
    /**
     * Calls sendmail to mail the error, if the error has not been seen before
     *
     * @return void
     */
    public function show()
    {
        if ($this->question->getFlag('seen') != 'true') {
            $this->sendmail(_('Sysconf was not configured to display this error message, so it mailed it to you'));
        }

        $this->_value = '';
    }

    /**
     * The sendmail method mails the text to root. The external unix mail program is used to do this, if it is present.
     *
     * If the mail is successfully sent a true value is returned. Also, the question is marked as seen.
     * A footer may be passed as the first parameter; it is generally used to explain why the note was sent.
     *
     * @param string $footer
     * @return bool TRUE if mail was successfuly sent, FALSE otherwise
     */
    protected function sendmail($footer = '')
    {
        if (($adminEmail = Config::getInstance()->adminEmail) && is_executable('/usr/bin/mail')) {
            Log::debug('user', 'mailing a note');

            $title = 'Sysconf: ' . $this->frontend->getTitle() . ' -- ' . $this->question->getDescription();

            if (!($mailer = @popen(escapeshellcmd("/usr/bin/mail -s '$title' $adminEmail"), 'w'))) {
                return false;
            }

            if (($extendedDescription = $this->question->getExtendedDescription()) != '') {
                fwrite($mailer, Encoding::wordWrap($extendedDescription, 75, "\n", true, 'UTF-8'));
            } else {
                // Evil note!
                fwrite($mailer, Encoding::wordWrap($this->question->getDescription(), 75, "\n", true, 'UTF-8'));
            }

            fwrite($mailer, "\n\n");

            if (!($hostname = `hostname -f 2>/dev/null`)) {
                $hostname = _('unknown system');
            }

            fwrite($mailer, "-- \n" . sprintf(_('Sysconf, running at %s'), $hostname));

            if ($footer != '') {
                fwrite($mailer, "[ " . $footer . " ]\n");
            }

            fwrite($mailer, "\n\n");

            if (pclose($mailer) != 0) {
                return false;
            }

            // Mark this note as seen. YThe frontend doesn't do this for us, since we are marked as not visible.
            $this->question->setFlag('seen', 'true');
            return true;
        }

        return false;
    }
}
