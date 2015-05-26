<?php

/**
 * mauTemplate
 * 
 * Flexible Template Engine
 * 
 * No use or disclosure of this information in any form without
 * the written permission of Maurits van Mastrigt.
 * 
 * @package   
 * @author Maurits van Mastrigt
 * @copyright Copyright © 2010 Maurits van Mastrigt. All rights reserved.
 * @version 0.1
 * @access public
 */
final class mauTemplate
{
	// ---------------------------------------- //
	//   Variables
	// ---------------------------------------- //

	private $includeBlockPattern = "/[\\r\\n]{0,1}([\\t| ]*)?\<!--\s?\[\+(\w{3,32}+)\]\s?--\>/";
	//                               ------------1----------- -----2----- ----3---- ----2----
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get include block name (length 3-32)

	private $blockPattern = "/[\\r\\n]{0,1}[\\t| ]*?\<!--[\\t| ]?\[(\w{3,32}+)\][\\t| ]?--\>(.+?)[\\r\\n]{0,1}[\\t| ]*?\<!--[\\t| ]?\[\/\\1\][\\t| ]?--\>/s";
	//                        -----------1---------- ------2------- ----3---- ------2------- -4- -----------1---------- ------2------- -3- ------2-------
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get block name (length 3-32)
	// 4. Get block data

	private $variablePattern = "/\{(\w{3,32}+)\}/";
	//                           -1- ---2--- -1-
	// -- Legend
	// 1. Variable prefix/surfix
	// 2. Get variable name (length 3-32)

	private $indexStr = '';
	private $indexRef = null;
	private $indexKey = 0;

	private $data = array();

	/**
	 * mauTemplate::__construct()
	 * 
	 * @return
	 */
	public function __construct()
	{
		$this->indexRef = &$this->data;
		$this->indexKey = 0;
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
			return trigger_error( __CLASS__ . "::setIncludeBlockPattern() expects parameter 1 to be a string", E_USER_WARNING );

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
			return trigger_error( __CLASS__ . "::setBlockPattern() expects parameter 1 to be a string", E_USER_WARNING );

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
			return trigger_error( __CLASS__ . "::setVariablePattern() expects parameter 1 to be a string", E_USER_WARNING );

		$this->variablePattern = $pattern;
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
	 * @param bool $new
	 * @return
	 */
	public function setIndex( $index = '', $new = false )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::setIndex() expects parameter 1 to be a string", E_USER_WARNING );
		if ( ! is_bool( $new ) )
			return trigger_error( __CLASS__ . "::setIndex() expects parameter 2 to be a boolean", E_USER_WARNING );

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

		// Verify index
		while ( list( $key, $part ) = each( $indexParts ) )
		{
			// Parent
			if ( $part == '..' )
			{
				$t_key = $key - 1;
				while ( ! isset( $indexParts[$t_key] ) && $t_key > 0 )
					--$t_key;
		
				unset( $indexParts[$key], $indexParts[$t_key], $t_key );
				reset( $indexParts );
				
				continue;
			}

			// Ignore index key
			if ( strpos( $part, ':' ) )
				list( $part ) = explode( ':', $part );

			if ( $key > 0 )
			{
				if ( strpos( $indexParts[$key - 1], ':' ) )
					list( $prevPart ) = explode( ':', $indexParts[$key - 1] );
				else
					$prevPart = $indexParts[$key - 1];
			}
			else
				$prevPart = '';
				
			// Remove empty parts
			if ( empty( $part ) || $part == '.' )
			{
				unset( $indexParts[$key] );
				
				continue;
			}

			// Remove duplicate parts
			if ( $key > 0 && $part == $prevPart )
				unset( $indexParts[$key - 1] ); // Unset previous part
		}

		// Set index reference to root
		$this->indexRef = &$this->data;
		$this->indexKey = 0;

		// Traverse index string parts to set index reference
		$lastKey = end( array_keys( $indexParts ) );
		foreach ( $indexParts as $key => $part )
		{
			$part = '/' . $part;

			if ( $new && $key == $lastKey )
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
			else
			{
				// Check for index key
				if ( ! empty( $this->indexRef[$part] ) && is_array( $this->indexRef[$part] ) )
				{
					end( $this->indexRef[$part] );
					$indexKey = key( $this->indexRef[$part] );
				}
				else
					$indexKey = 0;

				if ( strpos( $part, ':' ) )
				{
					list( $part, $_indexKey ) = explode( ':', $part );

					if ( is_numeric( $_indexKey ) )
						$indexKey = $_indexKey;
				}
			}

			// Set index
			$this->indexRef = &$this->indexRef[$part][$indexKey];
			$this->indexKey = $indexKey;
		}

		// Save index string
		$this->indexStr = implode( '/', $indexParts );
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
			return trigger_error( __CLASS__ . "::newBlock() expects parameter 1 to be a string", E_USER_WARNING );

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
			return trigger_error( __CLASS__ . "::assign() expects parameter 1 to be a string", E_USER_WARNING );
		if ( $key == '__GLOBALS' )
			return trigger_error( __CLASS__ . "::assign() does not allow parameter 1 to be '__GLOBALS'", E_USER_WARNING );
		if ( ! is_string( $value ) )
			return trigger_error( __CLASS__ . "::assign() expects parameter 2 to be a string", E_USER_WARNING );

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
			return trigger_error( __CLASS__ . "::assignArray() expects parameter 2 to be a string", E_USER_WARNING );

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
			return trigger_error( __CLASS__ . "::assignGlobal() expects parameter 1 to be a string", E_USER_WARNING );
		if ( ! is_string( $value ) )
			return trigger_error( __CLASS__ . "::assignGlobal() expects parameter 2 to be a string", E_USER_WARNING );

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
			return trigger_error( __CLASS__ . "::assignInclude() expects parameter 1 to be a string", E_USER_WARNING );
		if ( ! is_string( $file ) )
			return trigger_error( __CLASS__ . "::assignInclude() expects parameter 2 to be a string", E_USER_WARNING );

		$this->indexRef['+' . $key][] = $file;
	}

	// ---------------------------------------- //
	//   Parse functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::fetch()
	 * 
	 * Fetches template file and parses it (with optional data)
	 * 
	 * @param string $file
	 * @param array $data
	 * @param string $index
	 * @return string
	 */
	public function fetch( $file, array $data = null, $index = '' )
	{
		if ( ! file_exists( $file ) )
			return trigger_error( "File '" . $file . "' not found [" . __CLASS__ . "]", E_USER_ERROR );
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::fetch() expects parameter 3 to be a string", E_USER_WARNING );

		if ( $data )
			$this->assignArray( $data, $index );

		$template = file_get_contents( $file );

		return $this->parse( $template, $this->data );
	}

	/**
	 * mauTemplate::parse()
	 * 
	 * (recursively) Parses template string with data array
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
			foreach ( $blocks as & $block )
			{
				$_replaceData = '';
				$_blockName = '/' . $block[1];

				// Block data assigned?
				if ( isset( $data[$_blockName] ) && is_array( $data[$_blockName] ) )
				{
					// Sort by key
					ksort( $data[$_blockName] );

					// Loop through block data
					foreach ( $data[$_blockName] as & $_blockData )
					{
						$_replaceData .= $this->parse( $block[2], ( is_array( $_blockData ) ? $_blockData : array() ) );
					}
					unset( $_blockData );
				}

				// Replace block with filled block(s)
				$template = str_replace( $block[0], $_replaceData, $template );
			}
			unset( $block );
		}

		// Variables
		if ( preg_match_all( $this->variablePattern, $template, $variables, PREG_SET_ORDER ) )
		{
			// Replace variables
			foreach ( $variables as & $variable )
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
			unset( $variable );
		}

		// Include blocks
		if ( preg_match_all( $this->includeBlockPattern, $template, $includeBlocks, PREG_SET_ORDER ) )
		{
			foreach ( $includeBlocks as & $includeBlock )
			{
				$_replaceData = '';
				$_includeBlockName = '+' . $includeBlock[1];
				$indentation = '';

				// If indentation is added
				if ( count( $includeBlock ) == 3 )
				{
					$indentation = $includeBlock[1];
					$_includeBlockName = '+' . $includeBlock[2];
				}

				// Block data assigned?
				if ( isset( $data[$_includeBlockName] ) && is_array( $data[$_includeBlockName] ) )
				{
					// Sort by key
					ksort( $data[$_includeBlockName] );

					// Loop through block data
					foreach ( $data[$_includeBlockName] as & $_blockFile )
					{
						if ( file_exists( $_blockFile ) )
						{
							// Allow single line include blocks & indent string
							if ( $indentation )
								$_replaceData .= str_indent( $this->parse( "\r\n" . file_get_contents( $_blockFile ), $data ), $indentation );
							else
								$_replaceData .= $this->parse( file_get_contents( $_blockFile ), $data );
						}
						else
							trigger_error( __CLASS__ . "::parse() has invalid file '" . $_blockFile . "' for include block '[" . $_includeBlockName . "]'",
								E_USER_WARNING );
					}
					unset( $_blockFile );
				}

				// Replace block with filled block(s)
				$template = str_replace( $includeBlock[0], $_replaceData, $template );
			}
			unset( $includeBlock );
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
	 */
	function add_delimiters( $value, $left = '{', $right = '}' )
	{
		if ( is_string( $value ) )
			return $left . $value . $right;
		elseif ( is_array( $value ) )
			return array_map( 'add_delimiters', $value );
		else
			return trigger_error( "add_delimiters() expects parameter 1 to be a string or array", E_USER_WARNING );
	}
}

if ( ! function_exists( 'array_merge_recursive_distinct' ) )
{
	/**
	 * array_merge_recursive_distinct()
	 * 
	 * Merge two or more arrays recursively distinct
	 * 
	 * Source: http://www.php.net/manual/en/function.array-merge-recursive.php#96201
	 * 
	 * @param array array1
	 * @param ... aN
	 * @return array
	 */
	function array_merge_recursive_distinct()
	{
		$arrays = func_get_args();
		$base = array_shift( $arrays );

		if ( ! is_array( $base ) )
			$base = empty( $base ) ? array() : array( $base );

		foreach ( $arrays as $append )
		{
			if ( ! is_array( $append ) )
				$append = array( $append );

			foreach ( $append as $key => $value )
			{
				if ( ! array_key_exists( $key, $base ) && ! is_numeric( $key ) )
				{
					$base[$key] = $append[$key];
					continue;
				}
				if ( is_array( $value ) || is_array( $base[$key] ) )
				{
					$base[$key] = array_merge_recursive_distinct( $base[$key], $append[$key] );
				} elseif ( is_numeric( $key ) )
				{
					if ( ! in_array( $value, $base ) )
						$base[] = $value;
				}
				else
				{
					$base[$key] = $value;
				}
			}
		}

		return $base;
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
	 */
	function str_indent( $string, $indentation = "\t" )
	{
		if ( ! is_string( $string ) )
			return trigger_error( "str_indent() expects parameter 1 to be a string", E_USER_WARNING );
		if ( ! is_string( $indentation ) && ! is_int( $indentation ) )
			return trigger_error( "str_indent() expects parameter 2 to be a string or integer", E_USER_WARNING );

		return str_replace( "\n", "\n" . ( is_int( $indentation ) ? str_repeat( "\t", $tabs ) : $indentation ), $string );
	}
}

// EOF
