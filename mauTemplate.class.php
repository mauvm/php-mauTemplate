<?php

return new mauTemplate;

/**
 * mauTemplate
 *
 * Flextendable Template Engine
 *
 * Separate the PHP from the HTML
 *
 * @category    PHP, HTML
 * @author      Maurits van Mastrigt <maurits@vanmastrigt.nl>
 * @copyright   Copyright © 2011 Maurits van Mastrigt. All rights reserved.
 * @license     http://www.gnu.org/licenses/gpl.html GNU General Public License
 * @tutorial    http://dev.mauvm.nl/mauTemplate
 * @version     1.1.2
 * @access      public
 *
 *** // Changelog // ***
 *
 * v1.1.2 		[07-02-2012]
 *   - Removed include blocks
 *
 * v1.1.1 		[28-01-2012]
 *   - Modified getAssigned method to return all if no key is given
 *   - Added getAssignedGlobal method
 *
 * v1.1.0		[11-09-2011]
 *   - Removed Hungarian notation
 *   - Made the slash in block endings optional
 *   - Added loadPlugin method
 *   - Changed parameter of clearData method: keepGlobals to clearGlobals (this reverses the parameter)
 *   - Assign include now (by default) finds the first index available instead of appending at end
 *   - Variables can now override globals
 *
 * v1.0.1		[17-04-2011]
 *   - Fixed reference error in PHP 5.2, end( array_keys() )
 *   - Fixed newline removal, in case of \n{var}
 *   - Added data parameter to newBlock method
 *   - Minor performance tweaks
 */
class mauTemplate
{
	// ---------------------------------------- //
	//   Variables
	// ---------------------------------------- //

	// Version
	const 		VERSION 			= '1.1.2';

	// Data
	protected 	$data 				= array(),
				$globals 			= array(),
				$escapeChars 		= array( array( '{', '[' ), array( '\{', '\[' ) ), // Prevents variale/block insertion
 				$_globals 			= array();

	// Indexing
	protected 	$index,
				$indexes,
				$indexParts;

	// Plugins
	protected	$pluginDir 			= null,
				$plugins 			= array();

	// Block/variable patterns
	protected 	$blockPattern 		= '/[\r\n]{0,1}[\t| ]*?\<!--[\t| ]?\[([a-zA-Z0-9-_]{2,32}+)\][\t| ]?--\>(.+?)[\r\n]{0,1}[\t| ]*?\<!--[\t| ]?\[[\/]{0,1}\1\][\t| ]?--\>/is';
	//									----------1-------- -----2----- ------------3----------- -----2----- -4- ---------1--------- -----2----- ------3------ -----2-----
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get block name (length 2-32)
	// 4. Get block data

	protected 	$variablePattern 	= '/(?(?=[\r\n]+)[\r\n]?[\t| ]*|)[^\\\\](\{([a-zA-Z0-9-_|]{2,32}+)\})/';
	//									--------------1-------------- --2-- -3- ----------4--------- -3-
	// -- Legend
	// 1. Code beautifying
	// 2. Process escape character
	// 3. Variable prefix/surfix
	// 4. Get variable name (length 2-32)

	/**
	 * mauTemplate::__construct()
	 *
	 * @return
	 */
	function __construct()
	{
		// Set default index
		$this->index 		= & $this->data;
		$this->indexParts 	= array();
	}

	/**
	 * mauTemplate::loadPlugin()
	 *
	 * Basic plugin loader
	 *
	 * @param string $name
	 * @return bool
	 */
	function loadPlugin( $name )
	{
		// Prepare
		$file 	= dirname( __FILE__ ) . '/plugins/mauTemplate.' . $name . '.php';
		$class 	= 'mauTemplate' . $name;

		// Verify existance
		if( file_exists( $file ) )
		{
			include_once ( $file );

			// Load plugin
			if( class_exists( $class ) )
			{
				new $class( $this );
				return true;
			}
		}

		return false;
	}

	// ---------------------------------------- //
	//   Data retrieval functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::indexToString()
	 *
	 * Returns a cleaned index string
	 *
	 * @param array $indexParts
	 * @return string
	 */
	function indexToString( array & $indexParts = null )
	{
		// None given, use current
		if( ! $indexParts ) $indexParts = & $this->indexParts;

		// Loop through parts
		$indexString = '';

		foreach( $indexParts as & $indexPart )
		{
			if( $indexString ) $indexString .= '/';

			// Build index string, use last index as default
			if( ! empty( $indexPart[0] ) )
				$indexString .= $indexPart[0] . ':' . ( isset( $indexPart[1] ) ? $indexPart[1] : 'last' );
		}

		// Return index string
		return $indexString;
	}

	/**
	 * mauTemplate::getIndex()
	 *
	 * Returns a cleaned index (string or array)
	 *
	 * @param mixed $index (string or array)
	 * @param bool $strict
	 * @param bool $returnArray
	 * @return string or array
	 */
	function getIndex( $index = '.', $strict = false, $returnArray = false )
	{
		// Return current
		if( $index === '.' )
			return ( $returnArray ? $this->indexParts : $this->indexToString() );

		// Set and store new index
		$newIndex = $this->setIndex( $index, $strict, $returnArray );

		// Restore index and return new index
		$this->restoreIndex();

		return $newIndex;
	}

	/**
	 * mauTemplate::getData()
	 *
	 * Get data array
	 *
	 * @param mixed $index (string or array)
	 * @return array
	 */
	function getData( $index = '.' )
	{
		// Set new index
		$this->setIndex( $index, true );

		// Store data
		$data = $this->index;

		// Restore index and return data
		$this->restoreIndex();

		return $data;
	}

	/**
	 * mauTemplate::getAssigned()
	 *
	 * Get assigned data
	 *
	 * @param string $key
	 * @return string or null on failure
	 */
	function getAssigned( $key = null )
	{
		if( $key )
			return ( isset( $this->index[$key] ) ? $this->index[$key] : null );
		else
			return $this->index;
	}

	/**
	 * mauTemplate::getAssignedGlobal()
	 *
	 * Get assigned global data
	 *
	 * @param string $key
	 * @return string or null on failure
	 */
	function getAssignedGlobal( $key = null )
	{
		if( $key )
			return ( isset( $this->globals[$key] ) ? $this->globals[$key] : null );
		else
			return $this->globals;
	}

	/**
	 * mauTemplate::getBlockCount()
	 *
	 * Get block count (by optional index)
	 *
	 * @param mixed $index (string or array)
	 * @return int
	 */
	function getBlockCount( $index = '.' )
	{
		// Get parent index
		$index = $this->setIndex( $index, true, true );

		$lastIndex = end( $index );

		$index = $this->setIndex( '..', true, true );

		// Get count
		$count = 0;

		if( ! empty( $this->index['/' . $lastIndex[0]] ) )
			$count = count( $this->index['/' . $lastIndex[0]] );

		// Restore index and return count
		$this->restoreIndex( 2 );

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
	 * @param mixed $index (string or array)
	 * @param bool $strict
	 * @param bool $returnArray
	 * @param bool $appendBlock
	 * @return string or array
	 */
	function setIndex( $index, $strict = false, $returnArray = false, $appendBlock = false )
	{
		// Verify parameters
		if( is_string( $index ) )
			$index = explode( '/', $index );
		elseif( ! is_array( $index ) )
			throw new Exception( "Invalid parameter 'mIndex' (must be string or array)" );

		// Store index
		$this->indexes[] 	= $this->indexParts;

		// Absolute index
		if( empty( $index[0] ) )
			$this->indexParts = array();

		// Build total part array
		$this->index 		= & $this->data;

		foreach( $index as & $part )
			$this->indexParts[] = & $part;

		// Loop through parts
		$last 				= '';
		$parts 				= array();

		foreach( $this->indexParts as & $part )
		{
			// Convert to array
			if( is_string( $part ) )
				$part = explode( ':', $part );

			// Skip empty, doubles and process parent
			if( ! $part[0] || $part[0] == '.' )
				continue;
			elseif( $part[0] == '..' )
				array_pop( $parts );
			else
			{
				// Skip double
				if( $part[0] === $last )
					array_pop( $parts );

				// Verify block
				if( $strict && preg_match( '/[^a-zA-Z0-9\_\-]/', $part[0] ) )
					throw new Exception( "Block '{$part[0]}' has illegal characters (a-Z, 0-9 and -_ are allowed)" );

				// Verify index
				if( ! isset( $part[1] ) || $part[1] === '' )
					$part[1] = 'last';

				$last 		= $part[0];
				$parts[] 	= $part;
			}
		}

		// Only update index reference on change in index parts
		if( $appendBlock || $parts !== end( $this->indexes ) )
		{
			// Get last key
			$keys 			= array_keys( $parts );
			$lastKey 		= end( $keys ); // Bugfix for reference error in PHP 5.2

			// Set index
			foreach( $parts as $key => & $part )
			{
				// Go into block
				if( $strict && ! isset( $this->index['/' . $part[0]] ) )
					throw new Exception( "Block '{$part[0]}' does not exist!" );

				$this->index = & $this->index['/' . $part[0]];

				if( $this->index === null )
					$this->index = array();

				// Process pseudo indexes
				if( ! ctype_digit( "{$part[1]}" ) )
				{
					ksort( $this->index );

					if( $part[1] == 'first' )
					{
						$keys 		= array_keys( $this->index );
						$part[1] 	= current( $keys ); // Bugfix for reference error in PHP 5.2
					}
					elseif( $part[1] == 'last' || $part[1] === null )
					{
						$keys 		= array_keys( $this->index );
						$part[1] 	= end( $keys ); 	// Bugfix for reference error in PHP 5.2
					}
				}

				// Append block
				if( $appendBlock && $lastKey == $key )
				{
					while( isset( $this->index[$part[1]] ) ){ ++$part[1]; }
				}

				// Verify block index
				if( $part[1] === false )
					$part[1] = 0;
				if( $strict && ! isset( $this->index[$part[1]] ) )
					throw new Exception( "Block index '{$part[0]}:{$part[1]}' does not exist!" );
				if( ! ctype_digit( "{$part[1]}" ) )
					throw new Exception( "Block index '{$part[0]}:{$part[1]}' is invalid (must be numeric)!" );
				if( ! isset( $this->index[$part[1]] ) )
					$this->index[$part[1]] = array();

				// Go into block index
				$this->index = & $this->index[$part[1]];
			}
		}

		$this->indexParts = $parts;

		// Return resolved
		return ( $returnArray ? $this->indexParts : $this->indexToString() );
	}

	/**
	 * mauTemplate::restoreIndex()
	 *
	 * Restore stored index
	 *
	 * @param int $history
	 * @param bool $returnArray
	 * @return string or array
	 */
	function restoreIndex( $history = 1, $returnArray = false )
	{
		if( ! $this->indexes )
			return false;

		// Get last stored index
		while( $history-- && end( $this->indexes ) !== null ){ $index = array_pop( $this->indexes ); }

		// Set and return index
		return $this->setIndex( array_merge( array( '' ), $index ), true, $returnArray );
	}

	/**
	 * mauTemplate::newBlock()
	 *
	 * Add new block, and assign optional data
	 *
	 * @param mixed $index (string or array)
	 * @param mixed $data (string or array)
	 * @param bool $returnArray
	 * @return string or array
	 */
	function newBlock( $index = '.', $data = null, $returnArray = false )
	{
		$index = $this->setIndex( $index, false, $returnArray, true );

		// Optionally assign data
		if( $data )
		{
			if( is_array( $data ) )
				$this->assign( $data );
			else
			{
				// Assign string to blockname
				$lastPart = end( $this->indexParts );

				$this->assign( $lastPart[0], $data );
			}
		}

		return $index;
	}

	/**
	 * mauTemplate::assign()
	 *
	 * Assign value to a key in current index
	 *
	 * @param mixed $key (string or array)
	 * @param string $value
	 * @return
	 */
	function assign( $key, $value = '' )
	{
		if( is_string( $key ) ) $key = array( $key => & $value );

		if( is_array( $key ) )
		{
			// Loop and assign
			foreach( $key as $var => & $value )
			{
				if( $var != '__GLOBALS' && $var[0] != '/' )
					$this->index[$var] = (string) $value;
			}
		}
	}

	/**
	 * mauTemplate::assignGlobal()
	 *
	 * Assign global variable
	 *
	 * @param mixed $key (string or array)
	 * @param string $value
	 * @return
	 */
	function assignGlobal( $key, $value = '' )
	{
		if( is_string( $key ) ) $key = array( $key => & $value );

		if( is_array( $key ) )
		{
			// Loop and assign globals
			foreach( $key as $var => & $value )
			{
				if( $var != '__GLOBALS' && $var[0] != '/' )
					$this->globals[$var] = (string) $value;
			}
		}
	}

	/**
	 * mauTemplate::clearData()
	 *
	 * Clears assigned data from given index
	 *
	 * @param mixed $index (string or array)
	 * @param bool $clearGlobals
	 * @return
	 */
	function clearData( $index = '.', $clearGlobals = false )
	{
		// Clear given index
		$this->setIndex( $index );
		$this->index = array();

		// Remove globals too
		if( $clearGlobals ) $this->globals = array();
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
	 * @param array $data (override assigned data)
	 * @param bool $clearData
	 * @return string
	 */
	function fetch( $file, array & $data = null, $clearData = false )
	{
		if( ! $file || ! file_exists( $file ) )
			throw new Exception( "Can not find file '{$file}'!" );

		// Use assigned data
		if( ! $data ) $data = & $this->data;

		// Parse template
		$template = $this->parse( file_get_contents( $file ), $data );

		// Clear data
		if( $clearData ) $this->clearData( '/', false );

		// Return HTML
		return $template;
	}

	/**
	 * mauTemplate::parse()
	 *
	 * Parses template string with data array
	 *
	 * @param string $template
	 * @param array $data (override assigned data)
	 * @return string
	 */
	function parse( $template, array & $data = null )
	{
		if( ! $template || ! is_string( $template ) )
			throw new Exception( "Invalid parameter 'template' (must be a -non empty- string)" );

		// Prepare global variables
		$this->_globals = ( isset( $data['__GLOBALS'] ) ? $data['__GLOBALS'] : $this->globals );

		unset( $data['__GLOBALS'] );

		// Use assigned data
		if( ! $data ) $data = & $this->data;

		// Parse template, return HTML
		return str_replace( $this->escapeChars[1], $this->escapeChars[0], $this->_parse( $template, $data ) );
	}

	/**
	 * mauTemplate::_parse()
	 *
	 * Actual parsing function
	 *
	 * @param string $template
	 * @param array $data
	 * @return string
	 */
	protected function _parse( $template, array & $data = null )
	{
		// Blocks
		if( preg_match_all( $this->blockPattern, $template, $blocks, PREG_SET_ORDER ) )
		{
			// ARRAY_KEYS to increase performance in case of large data
			foreach( array_keys( $blocks ) as $key )
			{
				$_replaceData 		= '';
				$_blockName 		= '/' . $blocks[$key][1];

				// Block data assigned?
				if( isset( $data[$_blockName] ) && is_array( $data[$_blockName] ) )
				{
					// Sort by key
					ksort( $data[$_blockName] );

					// Loop through block data
					foreach( array_keys( $data[$_blockName] ) as $_blockKey )
					{
						if( ! isset( $data[$_blockName][$_blockKey] ) )
							$data[$_blockName][$_blockKey] = array();

						$_replaceData .= $this->_parse( $blocks[$key][2], $data[$_blockName][$_blockKey] );
					}
				}

				// Replace block with filled block(s)
				$template 			= substr_replace( $template, $_replaceData, strpos( $template, $blocks[$key][0] ), strlen( $blocks[$key][0] ) );
			}
		}

		// Variables
		if( preg_match_all( $this->variablePattern, $template, $variables, PREG_SET_ORDER ) )
		{
			// Replace variables
			foreach( $variables as & $variable )
			{
				$_replaceData = '';

				// Check for data variable
				if( isset( $data[$variable[2]] ) )
					$_replaceData 	= $data[$variable[2]];

				// Check for GLOBALS
				elseif( isset( $this->_globals[$variable[2]] ) )
					$_replaceData 	= $this->_globals[$variable[2]];

				// Apply filter
				if( ! strpos( $variable[2], '|raw' ) )
					$_replaceData 	= htmlspecialchars( $_replaceData );

				// Check match string, prepend first character of replace string again
				if( $variable[0][0] == "\r" || $variable[0][0] == "\n" )
				{
					$_replaceSearch = & $variable[1];
				}
				else
				{
					$_replaceSearch = & $variable[0];
					$_replaceData 	= $variable[0][0] . $_replaceData;
				}

				// Only replace first occurence and escape certain characters
				$template 			= substr_replace( $template, str_replace( $this->escapeChars[0], $this->escapeChars[1], $_replaceData ), strpos( $template, $_replaceSearch ), strlen( $_replaceSearch ) );
			}
		}

		// Return parsed template (partial)
		return $template;
	}
}

// EOF
