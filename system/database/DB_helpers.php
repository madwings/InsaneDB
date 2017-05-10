<?php
/**
 * InsaneDB
 *
 * PHP Database Library forked from CodeIgniter 3
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2015 - 2017, Stiliyan Ivanov
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	InsaneDB
 * @author	Stiliyan Ivanov
 * @copyright	Copyright (c) 2015, Stiliyan Ivanov (https://github.com/madwings/)
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @link	https://github.com/madwings/InsaneDB
 * @since	Version 1.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * InsaneDB Helpers
 *
 * @package		InsaneDB
 * @subpackage	Helpers
 * @category	Helpers
 * @author		Stiliyan Ivanov
 * @link		
 */

// --------------------------------------------------------------------

if ( ! function_exists('is_session_started'))
{
	/**
	* Check session status
	*
	* @return bool
	*/
	function is_session_started()
	{
		if ( ! is_cli())
		{
			$result = session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
		}
		else
		{
			$result = FALSE;
		}

		return $result;
	}
}
