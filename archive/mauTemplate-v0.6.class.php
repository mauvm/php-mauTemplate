<?php

/**
 * mauTemplate
 * 
 * Flextendable Template Engine
 * 
 * @category   HTML
 * @package   
 * @author     Maurits van Mastrigt <mauvm@hotmail.com>
 * @copyright  Copyright © 2010 Maurits van Mastrigt. All rights reserved.
 * @license    http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version    0.6
 * @access     public
 */
class mauTemplate
{
	// ---------------------------------------- //
	//   Variables
	// ---------------------------------------- //

	// Indexing
	protected $indexStr = '';
	protected $indexRef = null;
	
	// Block/variable patterns
	protected $includeBlockPattern = "/(?#cb)\<!--\s?\[\+(\w{3,32}+)\]\s?--\>/";
	//                                 --1--  -----2----- ----3---- ----2----
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get include block name (length 3-32)

	protected $blockPattern = "/(?#cb)\<!--[\\t| ]?\[(\w{3,32}+)\][\\t| ]?--\>(.+?)(?#cb)\<!--[\\t| ]?\[\/\\1\][\\t| ]?--\>/is";
	//                          --1--- ------2------- ----3---- ------2------- -4- --1--- ------2------- -3- ------2-------
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get block name (length 3-32)
	// 4. Get block data

	protected $variablePattern = "/[^\\\]\{(\w{2,32}+)\}/";
	//                             -1- ---2--- -1-
	// -- Legend
	// 1. Variable prefix/surfix
	// 2. Get variable name (length 2-32)

	// Code beautifying
	protected $beautifyCode = false;

	// Data
	public $data = array();
	private $_globals = array();

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
	 * NOTE: Use a forward slash as delimiter!
	 * 
	 * @param string $pattern
	 * @return
	 */
	public function setIncludeBlockPattern( $pattern )
	{
		if ( empty( $pattern ) || ! is_string( $pattern ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		// Strip off pre- and surfix
		if ( $pattern[0] == $pattern[strlen( $pattern ) - 1] )
			$pattern = substr( $pattern, 1, -1 );

		// Verify pattern
		if ( @preg_match( '/' . $pattern . '/', '' ) === false )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid pattern '/" . addslashes( $pattern ) . "/'", E_USER_WARNING );

		// Set pattern
		$this->includeBlockPattern = '/' . ( $this->beautifyCode ? "[\\r\\n]{0,1}([\\t| ]*)?" : '(?#cb)' ) . $pattern . '/';
	}

	/**
	 * mauTemplate::setBlockPattern()
	 * 
	 * Set block preg_match_all pattern
	 * 
	 * NOTE: Use a forward slash as delimiter!
	 * 
	 * @param string $blockStart
	 * @param string $blockEnd
	 * @return
	 */
	public function setBlockPattern( $blockStart, $blockEnd )
	{
		if ( empty( $blockStart ) || ! is_string( $blockStart ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );
		if ( empty( $blockEnd ) || ! is_string( $blockEnd ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a string", E_USER_WARNING );

		// Strip off pre- and surfix
		if ( $blockStart[0] == $blockStart[strlen( $blockStart ) - 1] )
			$blockStart = substr( $blockStart, 1, -1 );
		if ( $blockEnd[0] == $blockEnd[strlen( $blockEnd ) - 1] )
			$blockEnd = substr( $blockEnd, 1, -1 );

		// Verify pattern
		if ( @preg_match( '/' . $blockStart . '/', '' ) === false )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid pattern '/" . addslashes( $blockStart ) . "/'", E_USER_WARNING );
		if ( @preg_match( '/' . $blockEnd . '/', '' ) === false )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid pattern '/" . addslashes( $blockEnd ) . "/'", E_USER_WARNING );

		// Set pattern
		$this->blockPattern = '/' . ( $this->beautifyCode ? "[\\r\\n]{0,1}[\\t| ]*?" : '(?#cb)' ) . $blockStart;
		$this->blockPattern .= '(.+?)' . ( $this->beautifyCode ? "[\\r\\n]{0,1}[\\t| ]*?" : '(?#cb)' ) . $blockEnd . '/s';
	}

	/**
	 * mauTemplate::setVariablePattern()
	 * 
	 * Set variable preg_match_all pattern
	 * 
	 * NOTE: Use a forward slash as delimiter!
	 * 
	 * @param string $pattern
	 * @return
	 */
	public function setVariablePattern( $pattern )
	{
		if ( empty( $pattern ) || ! is_string( $pattern ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		// Strip off pre- and surfix
		if ( $pattern[0] == $pattern[strlen( $pattern ) - 1] )
			$pattern = substr( $pattern, 1, -1 );

		// Verify pattern
		if ( @preg_match( '/' . $pattern . '/', '' ) === false )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid pattern '/" . addslashes( $pattern ) . "/'", E_USER_WARNING );

		$this->variablePattern = '/' . $pattern . '/';
	}

	/**
	 * mauTemplate::setCodeBeautifying()
	 * 
	 * Enable/disable code beautifying
	 * Disable for increased performance
	 * 
	 * @param bool $enabled
	 * @return
	 */
	public function setCodeBeautifying( $enabled = true )
	{
		if ( ! is_bool( $enabled ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a boolean", E_USER_WARNING );

		if ( $enabled )
		{
			// Add code beautifying pattern to include block & block
			$this->includeBlockPattern = str_replace( '(?#cb)', "[\\r\\n]{0,1}([\\t| ]*)?", $this->includeBlockPattern );
			$this->blockPattern = str_replace( '(?#cb)', "[\\r\\n]{0,1}[\\t| ]*?", $this->blockPattern );
		}
		else
		{
			// Remove code beautifying pattern from include block & block
			$this->includeBlockPattern = str_replace( "[\\r\\n]{0,1}([\\t| ]*)?", '(?#cb)', $this->includeBlockPattern );
			$this->blockPattern = str_replace( "[\\r\\n]{0,1}[\\t| ]*?", '(?#cb)', $this->blockPattern );
		}

		// Set code beautifying
		$this->beautifyCode = $enabled;
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
	 * @param bool $toArray	 
	 * @return string or array
	 */
	public function getCleanIndex( $index, $toArray = false )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );
		if ( ! is_bool( $toArray ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a boolean", E_USER_WARNING );

		// Remove initial dot
		if ( strpos( $index, './' ) === 0 )
			$index = substr( $index, 1 );

		// Create index parts
		$indexParts = explode( '/', ( empty( $index ) || $index[0] == '/' ? $this->indexStr : '' ) . $index );

		// Clean index string
		foreach ( $indexParts as $key => $part )
		{
			// Seperate part & index key
			if ( $pos = strpos( $part, ':' ) )
				$part = substr( $part, 0, $pos );

			// Find previous key
			$prevKey = $key - 1;
			while ( ! isset( $indexParts[$prevKey] ) && $prevKey > 0 )
				--$prevKey;

			if ( $part == '..' )
				unset( $indexParts[$key], $indexParts[$prevKey] ); // Go to parent: unset current & previous key
			elseif ( empty( $part ) || $part == '.' )
				unset( $indexParts[$key] ); // Remove invalid parts
			elseif ( $part == $indexParts[$prevKey] )
				unset( $indexParts[$prevKey] ); // Remove duplicate parts

			unset( $prevKey );
		}

		// Return cleaned index
		return ( $toArray ? $indexParts : implode( '/', $indexParts ) );
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
	 * mauTemplate::getData()
	 * 
	 * Get data array
	 * 
	 * @return array
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * mauTemplate::getBlockCount()
	 * 
	 * Get block count (by optional index)
	 * 
	 * @param string $index
	 * @return int
	 */
	public function getBlockCount( $index = '' )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		// Prepare
		$info = $this->getBlockInfo( $index );

		// Return count
		return $info['total'];
	}

	/**
	 * mauTemplate::getBlockInfo()
	 * 
	 * Get block information (by optional index)
	 * 
	 * @param string $index
	 * @return array
	 */
	public function getBlockInfo( $index = '' )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		// Save index & prepare
		$currIndex = $this->indexStr;
		$index = ( $index ? $this->getCleanIndex( $index ) : $this->indexStr );
		$info = array();

		// Extract raw block
		$info['index'] = $index;
		$info['block'] = ( strpos( $index, '/' ) ? substr( strrchr( $index, '/' ), 1 ) : $index );

		// Extract block name and index key
		list( $info['name'], $info['key'] ) = explode( ':', $info['block'] );

		// Extract first & last key
		$this->setIndex( $index . '/..' );

		if ( isset( $this->indexRef['/' . $info['name']] ) && is_array( $this->indexRef['/' . $info['name']] ) )
		{
			$keys = array_keys( $this->indexRef['/' . $info['name']] );

			$info['first'] = ( current( $keys ) == $info['key'] );
			$info['last'] = ( end( $keys ) == $info['key'] );

			// Extract total block count
			$info['total'] = count( $keys );
		}
		else
		{
			$info['first'] = true;
			$info['last'] = true;
			$info['total'] = 0;
		}

		// Restore index
		$this->setIndex( $currIndex );

		return $info;
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

		// Set index reference to root
		$this->indexRef = &$this->data;

		// Traverse index string parts to set index reference
		$indexParts = $this->getCleanIndex( $index, true );
		$lastKey = end( array_keys( $indexParts ) );

		foreach ( $indexParts as $key => $part )
		{
			// Extract index key
			if ( strpos( $part, ':' ) )
			{
				list( $part, $indexKey ) = explode( ':', $part );

				if ( ! ctype_digit( $indexKey ) )
					return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid index key '" . $indexKey . "' (must be numeric)", E_USER_WARNING );
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

			// Sort blocks & set index
			$this->indexRef[$part][$indexKey] = array();
			ksort( $this->indexRef[$part] );

			$this->indexRef = &$this->indexRef[$part][$indexKey];

			// Save index key
			$indexParts[$key] = substr( $part, 1 ) . ':' . $indexKey; // Strip off trailing slash and add index key

			unset( $indexKey );
		}

		// Set index string
		$this->indexStr = implode( '/', $indexParts );

		return true;
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
	public function newBlock( $block, array & $data = null )
	{
		if ( empty( $block ) || ! is_string( $block ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a (not empty) string", E_USER_WARNING );

		// Append new block to index
		if ( $this->setIndex( $block, true ) )
		{
			if ( $data )
				$this->assignArray( $data );

			return $this->indexStr;
		}

		return false;
	}

	/**
	 * mauTemplate::prevBlock()
	 * 
	 * Sets index to previous block
	 * 
	 * @param string $index
	 * @return bool
	 */
	public function prevBlock( $index = '' )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		// Get block info
		$info = $this->getBlockInfo( $index );

		if ( $info['first'] )
			return false; // No previous block

		// Set index to parent, to traverse to previous block
		$this->setIndex( $info['index'] . '/..' );

		// Find previous key
		$prevKey = $info['key'] - 1;
		while ( ! isset( $this->indexRef['/' . $info['name']][$prevKey] ) )
			--$prevKey;

		// Set index to next block
		$this->setIndex( '/' . $info['name'] . ':' . $prevKey );

		return true;
	}

	/**
	 * mauTemplate::nextBlock()
	 * 
	 * Sets index to next block
	 * 
	 * @param string $index
	 * @return bool
	 */
	public function nextBlock( $index = '' )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a string", E_USER_WARNING );

		// Get block info
		$info = $this->getBlockInfo( $index );

		if ( $info['last'] )
			return false; // No next block

		// Set index to parent, to traverse to next block
		$this->setIndex( $info['index'] . '/..' );

		// Find next key
		$nextKey = $info['key'] + 1;
		while ( ! isset( $this->indexRef['/' . $info['name']][$nextKey] ) )
			++$nextKey;

		// Set index to next block
		$this->setIndex( '/' . $info['name'] . ':' . $nextKey );

		return true;
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
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a (not empty) string", E_USER_WARNING );
		if ( $key == '__GLOBALS' || strpos( $key, '/' ) === 0 || strpos( $key, '+' ) === 0 )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid key '" . $key . "'", E_USER_WARNING );
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
	public function assignArray( array & $data, $index = '' )
	{
		if ( ! is_string( $index ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a string", E_USER_WARNING );

		// Check for invalid data
		foreach ( array_keys( $data ) as $key )
		{
			if ( strpos( $key, '/' ) === 0 || strpos( $key, '+' ) === 0 )
			{
				trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid key '" . $key . "'", E_USER_WARNING );

				// Remove invalid data
				unset( $data[$key] );
			}
		}

		if ( ! $data )
			return;

		// Process index
		if ( $index )
		{
			// Save current index
			$currIndex = $this->indexStr;

			$this->setIndex( $index );
		}

		// Merge data arrays
		$this->indexRef = array_merge_recursive_distinct( $this->indexRef, $data );

		// Restore index
		if ( isset( $currIndex ) )
			$this->setIndex( $currIndex );
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
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a (not empty) string", E_USER_WARNING );
		if ( strpos( $key, '/' ) === 0 || strpos( $key, '+' ) === 0 )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() has invalid key '" . $key . "'", E_USER_WARNING );
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
	 * @param int $indexKey
	 * @return
	 */
	public function assignInclude( $key, $file, $indexKey = null )
	{
		if ( empty( $key ) || ! is_string( $key ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 1 to be a (not empty) string", E_USER_WARNING );
		if ( ! is_string( $file ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 2 to be a string", E_USER_WARNING );
		if ( $indexKey != null && ! ctype_digit( $indexKey ) )
			return trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() expects parameter 3 to be an integer", E_USER_WARNING );

		// Get last available index key
		if ( ! $indexKey )
		{
			$indexKey = 0;
			while ( isset( $this->indexRef['+' . $key][$indexKey] ) )
				++$indexKey;
		}

		// Assign file to index key
		$this->indexRef['+' . $key][$indexKey] = $file;
	}

	/**
	 * mauTemplate::clearData()
	 * 
	 * Clears (all, or from given index) assigned data
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
			// Save current index & get block info
			$currIndex = $this->indexStr;
			$info = $this->getBlockInfo( $index );

			// Set index to parent, to remove given block
			$this->setIndex( $info['index'] . '/..' );

			// Unset block
			unset( $this->indexRef['/' . $info['name']][$info['key']] );

			// Restore index
			if ( $currIndex == $info['index'] )
				$this->prevBlock( $currIndex ); // Set to previous block if current block is cleared
			else
				$this->setIndex( $currIndex );
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
	 * @param mixed $template (String or Array)
	 * @param array $data
	 * @return string
	 */
	public function parse( $template, array & $data = null )
	{
		// Prepare
		$this->_globals = null;

		// Parse template
		return $this->_parse( $template, $data );
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
		// Save data for GLOBAL variables
		if ( empty( $this->_globals ) && ! empty( $data['__GLOBALS'] ) && is_array( $data['__GLOBALS'] ) )
			$this->_globals = $data['__GLOBALS'];

		// Blocks
		if ( preg_match_all( $this->blockPattern, $template, $blocks, PREG_SET_ORDER ) )
		{
			// ARRAY_KEYS to increase performance in case of large data
			foreach ( array_keys( $blocks ) as $key )
			{
				$_replaceData = '';
				$_blockName = '/' . $blocks[$key][1];

				// Block data assigned?
				if ( isset( $data[$_blockName] ) && is_array( $data[$_blockName] ) )
				{
					// Sort by key
					ksort( $data[$_blockName] );

					// Loop through block data
					foreach ( array_keys( $data[$_blockName] ) as $_blockKey )
					{
						if ( is_array( $data[$_blockName][$_blockKey] ) )
							$_replaceData .= $this->_parse( $blocks[$key][2], $data[$_blockName][$_blockKey] );
						else
							$_replaceData .= $this->_parse( $blocks[$key][2] ); // No data to pass on
					}
				}

				// Replace block with filled block(s)
				$template = str_replace( $blocks[$key][0], $_replaceData, $template );
			}
		}

		// Variables
		if ( preg_match_all( $this->variablePattern, $template, $variables, PREG_SET_ORDER ) )
		{
			// Replace variables
			foreach ( $variables as $variable )
			{
				// Check for GLOBALS
				if ( isset( $this->_globals[$variable[1]] ) )
					$value = $this->_globals[$variable[1]];

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
						if ( ! empty( $_blockFile ) )
						{
							if ( file_exists( $_blockFile ) )
							{
								// Allow single line include blocks & indent string
								if ( $this->beautifyCode && ctype_space( $_indentation ) )
									$_replaceData .= str_replace( "\n", "\n" . $_indentation, "\r\n" . $this->_parse( file_get_contents( $_blockFile ) ) );
								else
									$_replaceData .= $this->_parse( file_get_contents( $_blockFile ), $data );
							}
							else
								trigger_error( __CLASS__ . "::" . __FUNCTION__ . "() can not find file '" . $_blockFile . "' for include block '[" . $_includeBlockName .
									"]'", E_USER_WARNING );
						}
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
	 * Parameters are passed by reference, though only for performance reasons.
	 * They're not altered by this function.
	 * 
	 * Original source: http://nl2.php.net/manual/en/function.array-merge-recursive.php#89684
	 * 
	 * Improved by Maurits van Mastrigt
	 *   + second parameter always an array
	 *   + array_keys() in foreach (for performance in case of large arrays)
	 * 
	 * @param array $array1
	 * @param array $array2
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

// EOF
