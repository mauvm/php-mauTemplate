<?php

/**
 * mauTemplate
 * 
 * Flextendable Template Engine
 * 
 * No use or disclosure of this information in any form without
 * the written permission of Maurits van Mastrigt.
 * 
 * @package   
 * @author Maurits van Mastrigt
 * @copyright Copyright © 2010 Maurits van Mastrigt. All rights reserved.
 * @version 0.3
 * @access public
 */
class mauTemplate
{
	// ---------------------------------------- //
	//   Variables
	// ---------------------------------------- //

	protected $includeBlockPattern = "/[\\r\\n]{0,1}([\\t| ]*)?\<!--\s?\[\+(\w{3,32}+)\]\s?--\>/";
	//                               ------------1----------- -----2----- ----3---- ----2----
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get include block name (length 3-32)

	protected $blockPattern = "/[\\r\\n]{0,1}[\\t| ]*?\<!--[\\t| ]?\[(\w{3,32}+)\][\\t| ]?--\>(.+?)[\\r\\n]{0,1}[\\t| ]*?\<!--[\\t| ]?\[\/\\1\][\\t| ]?--\>/s";
	//                        -----------1---------- ------2------- ----3---- ------2------- -4- -----------1---------- ------2------- -3- ------2-------
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get block name (length 3-32)
	// 4. Get block data

	protected $variablePattern = "/\{(\w{2,32}+)\}/";
	//                           -1- ---2--- -1-
	// -- Legend
	// 1. Variable prefix/surfix
	// 2. Get variable name (length 2-32)

	protected $indexStr = '';
	protected $indexRef = null;

	public $data = array();

	/**
	 * mauTemplate::__construct()
	 * 
	 * @return
	 */
	public function __construct()
	{
		$this->indexRef = &$this->data;
	}

	// ---------------------------------------- //
	//   Configure functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::setIncludeBlockPattern()
	 * 
	 * Set include block preg_match_all pattern
	 * 
	 * @param string $pattern
	 * @return
	 */
	public function setIncludeBlockPattern( $pattern )
	{
		if ( empty( $pattern ) || ! is_string( $pattern ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		$this->includeBlockPattern = $pattern;
	}

	/**
	 * mauTemplate::setBlockPattern()
	 * 
	 * Set block preg_match_all pattern
	 * 
	 * @param string $pattern
	 * @return
	 */
	public function setBlockPattern( $pattern )
	{
		if ( empty( $pattern ) || ! is_string( $pattern ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		$this->blockPattern = $pattern;
	}

	/**
	 * mauTemplate::setVariablePattern()
	 * 
	 * Set variable preg_match_all pattern
	 * 
	 * @param string $pattern
	 * @return
	 */
	public function setVariablePattern( $pattern )
	{
		if ( empty( $pattern ) || ! is_string( $pattern ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		$this->variablePattern = $pattern;
	}

	// ---------------------------------------- //
	//   Data retrieval functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::getCleanIndex()
	 * 
	 * Returns a cleaned index string
	 * 
	 * @param string $index
	 * @return string
	 */
	public function getCleanIndex( $index )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		// Remove invalid traversing
		$indexStr = str_replace( '//', '/', $index );

		// No indexing needed
		if ( $indexStr == './' )
			return;

		// Remove initial dot
		if ( strpos( $indexStr, './' ) === 0 )
			$indexStr = substr( $indexStr, 1 );

		// Create index parts
		$indexParts = explode( '/', ( strpos( $indexStr, '/' ) === 0 ? $this->indexStr : '' ) . $indexStr );

		// Clear index string
		foreach ( $indexParts as $key => $part )
		{
			// Ignore index key
			if ( strpos( $part, ':' ) )
				list( $part ) = explode( ':', $part );

			// Parent
			if ( $part == '..' )
			{
				// Find previous key
				$t_key = $key - 1;
				while ( ! isset( $indexParts[$t_key] ) && $t_key > 0 )
					--$t_key;

				// Unset previous key
				unset( $indexParts[$key], $indexParts[$t_key], $t_key );
			}

			// Remove empty parts
			elseif ( empty( $part ) || $part == '.' )
				unset( $indexParts[$key] );
		}

		// Return cleaned index
		return implode( '/', $indexParts );
	}

	/**
	 * mauTemplate::getIndex()
	 * 
	 * Get string of current index
	 * 
	 * @return string
	 */
	public function getIndex()
	{
		return $this->indexStr;
	}

	/**
	 * mauTemplate::getBlockCount()
	 * 
	 * Get block count by index
	 * 
	 * @param string $index
	 * @return int
	 */
	public function getBlockCount( $index )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		// Save
		$currIndex = $this->indexStr;

		// Set to parent
		$this->setIndex( $index . '/..' );

		// Get block
		$block = '/' . substr( strrchr( $this->getCleanIndex( $index ), '/' ), 1 );

		// Remove index key
		if ( strpos( $block, ':' ) !== false )
			list( $block ) = explode( ':', $block );

		// Count
		$count = 0;
		if ( isset( $this->indexRef[$block] ) && is_array( $this->indexRef[$block] ) )
			$count = count( $this->indexRef[$block] );

		// Restore index
		$this->setIndex( $currIndex );

		// Return count
		return $count;
	}

	// ---------------------------------------- //
	//   Data assigning functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::setIndex()
	 * 
	 * (relatively) Set index to template data array
	 * 
	 * @param string $index
	 * @param bool $append
	 * @return
	 */
	public function setIndex( $index = '', $append = false )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );
		if ( ! is_bool( $append ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a boolean", E_USER_WARNING );

		// Save index string & set index reference to root
		$this->indexStr = $this->getCleanIndex( $index );
		$this->indexRef = &$this->data;

		// Traverse index string parts to set index reference
		$indexParts = explode( '/', $this->indexStr );
		$lastKey = end( array_keys( $indexParts ) );

		foreach ( $indexParts as $key => $part )
		{
			// Extract index key
			if ( strpos( $part, ':' ) )
			{
				list( $part, $_indexKey ) = explode( ':', $part );

				if ( is_numeric( $_indexKey ) )
					$indexKey = $_indexKey;
			}

			$part = '/' . $part;

			// Append new index key
			if ( $append && $key == $lastKey )
			{
				if ( ! is_array( $this->indexRef[$part] ) )
					$this->indexRef[$part] = array();

				// Loop through keys to find last empty one (filling space)
				$keyTotal = count( $this->indexRef[$part] );
				for ( $i = 0; $i < $keyTotal; ++$i )
				{
					if ( ! array_key_exists( $i, $this->indexRef[$part] ) )
					{
						$indexKey = $i;
						break;
					}
				}

				if ( ! isset( $indexKey ) )
					$indexKey = $keyTotal; // if index 0 is set keyTotal will return 1, so add to end
			}

			// Get last index key
			elseif ( ! isset( $indexKey ) )
			{
				// Check for index key
				if ( ! empty( $this->indexRef[$part] ) && is_array( $this->indexRef[$part] ) )
				{
					end( $this->indexRef[$part] );
					$indexKey = key( $this->indexRef[$part] );
				}
				else
					$indexKey = 0;
			}

			// Set index
			$this->indexRef = &$this->indexRef[$part][$indexKey];

			// Save index key
			$indexParts[$key] = substr( $part, 1 ) . ':' . $indexKey; // Strip off trailing slash and add index key

			unset( $indexKey );
		}
	}

	/**
	 * mauTemplate::newBlock()
	 * 
	 * Add new block, and assign optional data
	 * 
	 * @param string $block
	 * @param array $data
	 * @return
	 */
	public function newBlock( $block, array $data = null )
	{
		if ( empty( $block ) || ! is_string( $block ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		$this->setIndex( $block, true );

		if ( $data )
			$this->assignArray( $data );
	}

	/**
	 * mauTemplate::assign()
	 * 
	 * Assign value to a key in current index
	 * 
	 * @param string $key
	 * @param string $value
	 * @return
	 */
	public function assign( $key, $value )
	{
		if ( empty( $key ) || ! is_string( $key ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );
		if ( $key == '__GLOBALS' )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() does not allow parameter 1 to be '__GLOBALS'", E_USER_WARNING );
		if ( ! is_string( $value ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a string", E_USER_WARNING );

		$this->indexRef[$key] = $value;
	}

	/**
	 * mauTemplate::assignArray()
	 * 
	 * Assign array to current or given index
	 * 
	 * @param array $data
	 * @param string $index
	 * @return
	 */
	public function assignArray( array $data, $index = '' )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a string", E_USER_WARNING );

		if ( $index )
			$this->setIndex( $index );

		if ( $data )
			$this->indexRef = array_merge_recursive_distinct( $this->indexRef, $data );
	}

	/**
	 * mauTemplate::assignGlobal()
	 * 
	 * Assigns global variable
	 * 
	 * @param string $key
	 * @param string $value
	 * @return
	 */
	public function assignGlobal( $key, $value )
	{
		if ( empty( $key ) || ! is_string( $key ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );
		if ( ! is_string( $value ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a string", E_USER_WARNING );

		$this->data['__GLOBALS'][$key] = $value;
	}

	/**
	 * mauTemplate::assignInclude()
	 * 
	 * Assign include block (on current index)
	 * 
	 * @param string $key
	 * @param string $file
	 * @return
	 */
	public function assignInclude( $key, $file )
	{
		if ( empty( $key ) || ! is_string( $key ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );
		if ( ! is_string( $file ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a string", E_USER_WARNING );

		$this->indexRef['+' . $key][] = $file;
	}

	/**
	 * mauTemplate::clearData()
	 * 
	 * Clears (all, or from given index) assigned data
	 * 
	 * NOTE: On finish sets index to root
	 * 
	 * @param $index
	 * @return
	 */
	public function clearData( $index = '' )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		// Unset index
		if ( $index || $index == './' )
		{
			// Extract parent/child
			$lastSlash = strrpos( $index, '/' ) | 0;
			$parent = substr( $index, 0, $lastSlash );
			$part = substr( $index, $lastSlash + ( $lastSlash > 0 ? 1 : 0 ) );

			if ( ! $part )
				return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid index string", E_USER_WARNING );

			// Extract index key
			if ( strpos( $part, ':' ) )
			{
				list( $part, $indexKey ) = explode( ':', $part );

				if ( ! is_numeric( $indexKey ) )
					return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid index key '" . $indexKey . "' (must be numeric)", E_USER_WARNING );
			}

			// Set index to parent, to remove given child
			$this->setIndex( $parent );

			// Unset block
			if ( isset( $indexKey ) )
				unset( $this->indexRef['/' . $part][$indexKey] );
			else
				array_pop( $this->indexRef[$part] );

			// Set index to root
			$this->setIndex();
		}
		else
		{
			// Unset all data
			$this->data = array();
			$this->indexRef = &$this->data;
			$this->indexStr = '';
		}
	}

	// ---------------------------------------- //
	//   Parse functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::fetch()
	 * 
	 * Fetches template file and parses it (with optional data clearance)
	 * 
	 * @param string $file
	 * @param boolean $clearData
	 * @return string
	 */
	public function fetch( $file, $clearData = false )
	{
		if ( ! file_exists( $file ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() can not find file '" . $_blockFile . "'", E_USER_WARNING );
		if ( ! is_bool( $clearData ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a boolean", E_USER_WARNING );

		$template = $this->parse( file_get_contents( $file ), $this->data );

		// Clear data
		if ( $clearData )
			$this->clearData();

		return $template;
	}

	/**
	 * mauTemplate::parse()
	 * 
	 * Parses template string with data array
	 * 
	 * @param string $template
	 * @param array $data
	 * @return string
	 */
	public function parse( $template, array $data )
	{
		// Save data for GLOBAL variables
		if ( empty( $this->data ) )
			$this->data = $data;

		// Blocks
		if ( preg_match_all( $this->blockPattern, $template, $blocks, PREG_SET_ORDER ) )
		{
			foreach ( $blocks as $block )
			{
				$_replaceData = '';
				$_blockName = '/' . $block[1];

				// Block data assigned?
				if ( isset( $data[$_blockName] ) && is_array( $data[$_blockName] ) )
				{
					// Sort by key
					ksort( $data[$_blockName] );

					// Loop through block data
					foreach ( $data[$_blockName] as $_blockData )
					{
						$_replaceData .= $this->parse( $block[2], ( is_array( $_blockData ) ? $_blockData : array() ) );
					}
				}

				// Replace block with filled block(s)
				$template = str_replace( $block[0], $_replaceData, $template );
			}
		}

		// Variables
		if ( preg_match_all( $this->variablePattern, $template, $variables, PREG_SET_ORDER ) )
		{
			// Replace variables
			foreach ( $variables as $variable )
			{
				// Check for GLOBALS
				if ( isset( $this->data['__GLOBALS'][$variable[1]] ) )
					$value = $this->data['__GLOBALS'][$variable[1]];

				// Check for data variable
				elseif ( isset( $data[$variable[1]] ) )
					$value = $data[$variable[1]];

				// Verify value
				if ( ! isset( $value ) || ! is_string( $value ) )
					$value = '';

				$template = str_replace( $variable[0], $value, $template );

				// Clear value
				unset( $value );
			}
		}

		// Include blocks
		if ( preg_match_all( $this->includeBlockPattern, $template, $includeBlocks, PREG_SET_ORDER ) )
		{
			foreach ( $includeBlocks as $includeBlock )
			{
				$_replaceData = '';
				$_includeBlockName = '+' . $includeBlock[1];
				$_indentation = '';

				// If indentation is added
				if ( count( $includeBlock ) == 3 )
				{
					$_indentation = $includeBlock[1];
					$_includeBlockName = '+' . $includeBlock[2];
				}

				// Block data assigned?
				if ( isset( $data[$_includeBlockName] ) && is_array( $data[$_includeBlockName] ) )
				{
					// Sort by key
					ksort( $data[$_includeBlockName] );

					// Loop through block data
					foreach ( $data[$_includeBlockName] as $_blockFile )
					{
						if ( file_exists( $_blockFile ) )
						{
							// Allow single line include blocks & indent string
							if ( $_indentation )
								$_replaceData .= str_indent( $this->parse( "\r\n" . file_get_contents( $_blockFile ), $data ), $_indentation );
							else
								$_replaceData .= $this->parse( file_get_contents( $_blockFile ), $data );
						}
						else
							trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() can not find file '" . $_blockFile . "' for include block '[" . $_includeBlockName .
								"]'", E_USER_WARNING );
					}
				}

				// Replace block with filled block(s)
				$template = str_replace( $includeBlock[0], $_replaceData, $template );
			}
		}

		// Return parsed template (part)
		return $template;
	}
}

// ---------------------------------------- //
//   Dependency functions
// ---------------------------------------- //

if ( ! function_exists( 'add_delimiters' ) )
{
	/**
	 * add_delimiters()
	 * 
	 * Add delimiters to string or (each value in) array
	 * 
	 * @param mixed $value (Array or String)
	 * @param string $left
	 * @param string $right
	 * @return array or string (depends on the input value) with added delimiters
	 * @author mauvm@hotmail.com
	 */
	function add_delimiters( $value, $left = '{', $right = '}' )
	{
		if ( is_string( $value ) )
			return $left . $value . $right;
		elseif ( is_array( $value ) )
			return array_map( 'add_delimiters', $value, $left, $right );
		else
			return trigger_error( __FUNCTION__ . "() expects parameter 1 to be a string or array", E_USER_WARNING );
	}
}

if ( ! function_exists( 'array_merge_recursive_distinct' ) )
{
	/**
	 * Merge array recursively and distinct
	 * 
	 * Parameters are passed by reference, though only for performance reasons. They're not
	 * altered by this function.
	 * 
	 * Original source: http://nl2.php.net/manual/en/function.array-merge-recursive.php#89684
	 * 
	 * Improved by Maurits van Mastrigt
	 *   + second parameter always an array
	 *   + array_keys() in foreach (for performance in case of large arrays)
	 * 
	 * @param array $array1
	 * @param mixed $array2
	 * @return array
	 * @author daniel@danielsmedegaardbuus.dk
	 */
	function &array_merge_recursive_distinct( array & $array1, array & $array2 )
	{
		$merged = $array1;

		foreach ( array_keys( $array2 ) as $key )
		{
			if ( is_array( $array2[$key] ) )
				$merged[$key] = is_array( $merged[$key] ) ? array_merge_recursive_distinct( $merged[$key], $array2[$key] ) : $array2[$key];
			else
				$merged[$key] = $array2[$key];
		}

		return $merged;
	}
}

if ( ! function_exists( 'str_indent' ) )
{
	/**
	 * str_indent()
	 * 
	 * Indent string lines with tabs or string
	 * 
	 * @param string $string
	 * @param mixed $indentation (String or Integer) 
	 * @return string
	 * @author mauvm@hotmail.com
	 */
	function str_indent( $string, $indentation = "\t" )
	{
		if ( ! is_string( $string ) )
			return trigger_error( __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );
		if ( ! is_string( $indentation ) && ! is_int( $indentation ) )
			return trigger_error( __FUNCTION__ . "() expects parameter 2 to be a string or integer", E_USER_WARNING );

		return str_replace( "\n", "\n" . ( is_int( $indentation ) ? str_repeat( "\t", $indentation ) : $indentation ), $string );
	}
}

// EOF
