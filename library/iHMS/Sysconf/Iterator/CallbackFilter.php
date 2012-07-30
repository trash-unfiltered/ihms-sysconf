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
 * @subpackage  Iterator
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

namespace iHMS\Sysconf\Iterator;

use FilterIterator;
use Iterator;
use Traversable;
use Closure;

/**
 * CallbackFilter class
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Iterator
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @link        https://github.com/i-HMS/sysconf Sysconf Home Site
 * @version     0.0.1
 */
class CallbackFilter extends FilterIterator
{
    /**
     * @var Closure
     */
    protected $_callback;

    /**
     * @param Iterator|Traversable $iterator
     * @param Closure $callback
     */
    public function __construct(Iterator $iterator, Closure $callback)
    {
        $this->_callback = $callback;
        parent::__construct($iterator);
    }

    /**
     * Check whether the current element of the iterator is acceptable
     *
     * @return bool TRUE if the current element is acceptable, FALSE otherwise
     */
    public function accept()
    {
        return call_user_func($this->_callback, $this->current(), $this->key(), $this->getInnerIterator());
    }
}
