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
 * @subpackage  Encoding
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 * @link        http://www.i-mscp.net i-MSCP Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

/**
 * iHMS_Sysconf_Encoding class
 *
 * This class provides facilities to convert between character encodings for sysconf, as well as other functions to
 * operate on characters.
 *
 * @category    iHMS
 * @package     iHMS_Sysconf
 * @subpackage  Encoding
 * @copyright   2012 by iHMS Team
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     0.0.1
 */
class iHMS_Sysconf_Encoding
{
    /**
     * @var string olds the user's charmap
     */
    protected static $_charmaps;

    /**
     * Wrap the given string
     *
     * This method is a word-wrapping function, with the same interface as the PHP wordwrap function
     * (except it doesn't gratuitously unexpand tabs). It will be used for proper wrapping of multibyte encodings,
     * combining and fullwidth characters, and languages that do not use whitespace between words.
     *
     * Note: Function borrowed to the Zend Framework project.
     *
     * @static
     * @param string $string input string
     * @param int $width column width
     * @param string $break line is broken using the optional break parameter
     * @param bool $cut if the cut is set to TRUE, the string is always wrapped at or before the specified width
     * @param string $charset Charset
     * @return string
     */
    public static function wordWrap($string, $width = 76, $break = "\n", $cut = false, $charset = 'UTF-8')
    {
        $result = array();
        $breakWidth = iconv_strlen($break, $charset);

        while (($stringLength = iconv_strlen($string, $charset)) > 0) {
            $breakPos = iconv_strpos($string, $break, 0, $charset);

            if ($breakPos !== false && $breakPos < $width) {
                if ($breakPos === $stringLength - $breakWidth) {
                    $subString = $string;
                    $cutLength = null;
                } else {
                    $subString = iconv_substr($string, 0, $breakPos, $charset);
                    $cutLength = $breakPos + $breakWidth;
                }
            } else {
                $subString = iconv_substr($string, 0, $width, $charset);

                if ($subString === $string) {
                    $cutLength = null;
                } else {
                    $nextChar = iconv_substr($string, $width, 1, $charset);

                    if ($breakWidth === 1) {
                        $nextBreak = $nextChar;
                    } else {
                        $nextBreak = iconv_substr($string, $breakWidth, 1, $charset);
                    }

                    if ($nextChar === ' ' || $nextBreak === $break) {
                        $afterNextChar = iconv_substr($string, $width + 1, 1, $charset);

                        if ($afterNextChar === false) {
                            $subString .= $nextChar;
                        }

                        $cutLength = iconv_strlen($subString, $charset) + 1;
                    } else {
                        $spacePos = iconv_strrpos($subString, ' ', $charset);

                        if ($spacePos !== false) {
                            $subString = iconv_substr($subString, 0, $spacePos, $charset);
                            $cutLength = $spacePos + 1;
                        } else if ($cut === false) {
                            $spacePos = iconv_strpos($string, ' ', 0, $charset);

                            if ($spacePos !== false) {
                                $subString = iconv_substr($string, 0, $spacePos, $charset);
                                $cutLength = $spacePos + 1;
                            } else {
                                $subString = $string;
                                $cutLength = null;
                            }
                        } else {
                            $subString = iconv_substr($subString, 0, $width, $charset);
                            $cutLength = $width;
                        }
                    }
                }
            }

            $result[] = $subString;

            if ($cutLength !== null) {
                $string = iconv_substr($string, $cutLength, ($stringLength - $cutLength), $charset);
            } else {
                break;
            }
        }

        return implode($break, $result);
    }

    /**
     * Takes a charmap and a string encoded in that charmap, and converts it to the user's charmap
     *
     * @param string $inputCharmap Input charmap
     * @param $string string String to convert
     * @return string Converted string
     */
    public static function convert($inputCharmap, $string)
    {
        if (!($userCharmap = self::getCharmap())) {
            return $string;
        }

        return iconv($inputCharmap, $userCharmap, $string);
    }

    /**
     * Returns charmap
     *
     * @static
     * @return string charmap
     */
    public static function getCharmap()
    {
        if (is_null(self::$_charmaps)) {
            self::$_charmaps = `locale charmap 2>/dev/null`;
        }

        return self::$_charmaps;
    }
}
