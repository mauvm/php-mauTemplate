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
 * @version    0.9.4
 * @access     public
 */
// @TODO Add function: setTempIndex
// @TODO Add function: restoreIndex
// @TODO Add function? getBlockInfo
// @TODO Stopped at  : getBlockCount
class mauTemplate
{
	// ---------------------------------------- //
	//   Variables
	// ---------------------------------------- //

	// Version
	public $version = '0.9.4';

	// Data
	protected $aData = array();
	protected $aGlobals = array();

	// Indexing
	protected $aIndexParts;
	protected $aIndex;

	// Code beautifying
	protected $bBeautifyCode = true;

	// Block/variable patterns
	protected $sIncludeBlockPattern = "/[\\r\\n]{0,1}([\\t| ]*)?\<!--\s?\[\+([a-zA-Z0-9-_]{3,32}+)\]\s?--\>/";
	//                                  -----------1-----------  -----2----- ----------3--------- ----2----
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get include block name (length 3-32)

	protected $sBlockPattern = "/[\\r\\n]{0,1}[\\t| ]*?\<!--[\\t| ]?\[([a-zA-Z0-9-_]{3,32}+)\][\\t| ]?--\>(.+?)[\\r\\n]{0,1}[\\t| ]*?\<!--[\\t| ]?\[\/\\1\][\\t| ]?--\>/is";
	//                           ----------1----------- ------2------- ----------3--------- ------2------- -4- ----------1----------- ------2------- -3- ------2-------
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get block name (length 3-32)
	// 4. Get block data

	// @TODO Ignore escaped brackets (eq. \{variable})
	protected $sVariablePattern = "/(?(?=[\\r\\n]+)[\\r\\n]?[\\t| ]*|)\{([a-zA-Z0-9-_]{2,32}+)\}/"; //"/[\\r\\n]{0,1}([\\t| ]*)?\{([a-zA-Z0-9-_]{2,32}+)\}/";
	//                              -----------1----------- -2- --------3--------- -2-
	// -- Legend
	// 1. Code beautifying
	// 2. Variable prefix/surfix
	// 3. Get variable name (length 2-32)

	/**
	 * mauTemplate::__construct()
	 * 
	 * @return
	 */
	public function __construct()
	{
		$this->aIndex = &$this->aData;
		$this->aIndexParts = array();
	}

	// ---------------------------------------- //
	//   Data retrieval functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::getIndex()
	 * 
	 * Returns a cleaned index string
	 * 
	 * @param mixed $mIndex
	 * @param bool $bStrict	 
	 * @param bool $bReturnArray
	 * @return string or array
	 */
	public function getIndex( $mIndex = '', $bStrict = false, $bReturnArray = false )
	{
		// Set and store new index
		$mNewIndex = $this->setTempIndex( $mIndex, $bStrict, $bReturnArray );

		// Restore index
		$this->restoreIndex();

		// Return new index
		return $mNewIndex;
	}

	/**
	 * mauTemplate::getData()
	 * 
	 * Get data array
	 * 
	 * @param string $mIndex
	 * @return array
	 */
	// @TODO Optional function: getData
	public function getData( $mIndex )
	{
		// Set new index
		$this->setTempIndex( $mIndex, true );

		// Store data
		$aData = $this->aIndex;

		// Restore index
		$this->restoreIndex();

		// Return data
		return $aData;
	}

	/**
	 * mauTemplate::getAssigned()
	 * 
	 * Get assigned data
	 * 
	 * @param string $sKey
	 * @return string or null on failure
	 */
	// @TODO Optional function: getAssigned
	public function getAssigned( $sKey )
	{
		return ( isset( $this->aIndex[$sKey] ) ? $this->aIndex[$sKey] : null );
	}

	/**
	 * mauTemplate::getBlockCount()
	 * 
	 * Get block count (by optional index)
	 * 
	 * @param string $mIndex
	 * @return int
	 */
	// @TODO Optional function: getBlockCount
	public function getBlockCount( $mIndex = '' )
	{
		// Set new index
		$aIndex = $this->setTempIndex( $mIndex, true, true );
		$aLastIndex = end( $aIndex );
		$aIndex[] = array( '..' ); // Move to parent
		$aIndex = $this->setTempIndex( $aIndex . '/..', true, true );

		// Store data
		if ( empty( $this->aIndex[$aLastIndex[0]] ) )
			$iCount = 0;
		else
			$iCount = count( $this->aIndex[$aLastIndex[0]] );

		// Restore index
		$this->restoreIndex( 2 );

		// Return data
		return $iCount;
	}

	// ---------------------------------------- //
	//   Data assigning functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::setIndex()
	 * 
	 * (relatively) Set index to template data array
	 * 
	 * @param mixed $mIndex
	 * @param bool $bStrict
	 * @param bool $bReturnArray
	 * @return string or array
	 */
	public function setIndex( $mIndex, $bStrict = false, $bReturnArray = false )
	{
		// Verify parameters
		if ( is_string( $mIndex ) )
			$mIndex = explode( '/', $mIndex );
		elseif ( is_array( $mIndex ) )
			$mIndex = &$mIndex;
		else
			throw new Exception( "Invalid parameter 'mIndex' (must be string or array)" );

		// Absolute index
		if ( empty( $mIndex[0] ) && ! empty( $mIndex[1] ) )
			$this->aIndexParts = array();

		// Build total part array
		$this->aIndex = &$this->aData;

		foreach ( $mIndex as & $sPart )
			$this->aIndexParts[] = &$sPart;

		// Loop through parts
		$sLast = '';
		$aParts = array();
		foreach ( $this->aIndexParts as & $aPart )
		{
			// Convert to array
			if ( is_string( $aPart ) )
				$aPart = explode( ':', $aPart );

			// Skip empty, doubles and process parent
			if ( ! $aPart[0] || $aPart[0] == '.' )
				continue;
			elseif ( $aPart[0] == '..' )
				array_pop( $aParts );
			else
			{
				// Skip double
				if ( $aPart[0] === $sLast )
					array_pop( $aParts );

				// Verify block
				if ( $bStrict && preg_match( '/[^a-zA-Z0-9\_\-]/', $aPart[0] ) )
					throw new Exception( "Block '{$aPart[0]}' has illegal characters (a-Z, 0-9 and -_ are allowed)!" );

				// Verify index
				if ( ! isset( $aPart[1] ) || $aPart[1] === '' )
					$aPart[1] = 'last';

				$sLast = $aPart[0];
				$aParts[] = $aPart;
			}
		}

		// Set index
		$sIndex = '';
		foreach ( $aParts as & $aPart )
		{
			// Go into block
			if ( $bStrict && ! isset( $this->aIndex['/' . $aPart[0]] ) )
				throw new Exception( "Block '{$aPart[0]}' does not exist!" );

			$this->aIndex = &$this->aIndex['/' . $aPart[0]];

			if ( $this->aIndex === null )
				$this->aIndex = array();

			// Process pseudo indexes
			if ( ! ctype_digit( "{$aPart[1]}" ) )
			{
				ksort( $this->aIndex );

				if ( $aPart[1] == 'first' )
					$aPart[1] = current( array_keys( $this->aIndex ) );
				elseif ( $aPart[1] == 'last' || $aPart[1] === null )
					$aPart[1] = end( array_keys( $this->aIndex ) );
			}

			// Verify block index
			if ( $aPart[1] === false )
				$aPart[1] = 0;
			if ( $bStrict && ! isset( $this->aIndex[$aPart[1]] ) )
				throw new Exception( "Block index '{$aPart[0]}:{$aPart[1]}' does not exist!" );
			if ( ! ctype_digit( "{$aPart[1]}" ) )
				throw new Exception( "Block index '{$aPart[0]}:{$aPart[1]}' is invalid (must be numeric)!" );
			if ( ! isset( $this->aIndex[$aPart[1]] ) )
				$this->aIndex[$aPart[1]] = array();

			// Go into block index
			$this->aIndex = &$this->aIndex[$aPart[1]];
			if ( ! $bReturnArray )
				$sIndex .= ( $sIndex ? '/' : '' ) . "{$aPart[0]}:{$aPart[1]}";
		}

		$this->aIndexParts = $aParts;

		// Return resolved
		return ( $bReturnArray ? $this->aIndexParts : $sIndex );
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
			throw new Exception( "Expecting parameter 1 to be a (not empty) string" );

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
	public function prevBlock( $index = '/' )
	{
		if ( ! is_string( $index ) )
			throw new Exception( "Expecting parameter 1 to be a string" );

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

		// Set index to previous block
		return $this->setIndex( '/' . $info['name'] . ':' . $prevKey );
	}

	/**
	 * mauTemplate::nextBlock()
	 * 
	 * Sets index to next block
	 * 
	 * @param string $index
	 * @return bool
	 */
	public function nextBlock( $index = '/' )
	{
		if ( ! is_string( $index ) )
			throw new Exception( "Expecting parameter 1 to be a string" );

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
		return $this->setIndex( '/' . $info['name'] . ':' . $nextKey );
	}

	/**
	 * mauTemplate::assign()
	 * 
	 * Assign value to a key in current index
	 * 
	 * @param mixed $key (String or array)
	 * @param string $value
	 * @return
	 */
	public function assign( $key, $value = null )
	{
		if ( empty( $key ) || ! is_string( $key ) && ! is_array( $key ) )
			throw new Exception( "Expecting parameter 1 to be a (not empty) string or array" );
		if ( is_string( $key ) )
		{
			if ( $key == '__GLOBALS' || $key[0] == '/' || $key[0] == '+' )
				throw new Exception( "Invalid key '" . $key . "'" );
			if ( ! is_string( $value ) )
				throw new Exception( "Expecting parameter 2 to be a string" );
		}

		return ( is_array( $key ) ? array_walk( $key, array( $this, 'assign' ) ) : $this->indexRef[$key] = $value );
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
			throw new Exception( "Expecting parameter 1 to be a (not empty) string" );
		if ( $key[0] == '/' && $key[0] == '+' )
			throw new Exception( "Invalid key '" . $key . "'" );
		if ( ! is_string( $value ) )
			throw new Exception( "Expecting parameter 2 to be a string" );

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
			throw new Exception( "Expecting parameter 1 to be a (not empty) string" );
		if ( ! is_string( $file ) )
			throw new Exception( "Expecting parameter 2 to be a string" );
		if ( $indexKey != null && ! is_int( $indexKey ) || ! ctype_digit( $indexKey ) )
			throw new Exception( "Expecting parameter 3 to be an integer" );

		// Get last available index key
		if ( ! $indexKey )
		{
			$indexKey = 0;
			while ( isset( $this->indexRef['+' . $key][$indexKey] ) )
				++$indexKey;
		}

		// Assign file to index key
		$this->indexRef['+' . $key][( int )$indexKey] = $file;
	}

	/**
	 * mauTemplate::clearData()
	 * 
	 * Clears (all, or from given index) assigned data
	 * 
	 * @param string $index
	 * @param bool $keepGlobals
	 * @return
	 */
	public function clearData( $index = '/', $keepGlobals = false )
	{
		if ( ! is_string( $index ) )
			throw new Exception( "Expecting parameter 1 to be a string" );

		// Unset index
		if ( $index )
		{
			// Save current index & get block info
			$currIndex = $this->indexStr;
			$info = $this->getBlockInfo( $index );

			// Set index to parent, to remove given block
			$this->setIndex( $info['index'] . '/..' );

			// Unset block
			unset( $this->indexRef['/' . $info['name']][$info['key']] );

			// Restore index (set to previous block if current block is cleared)
			return ( $currIndex == $this->indexStr . '/' . $info['name'] . ':' . $info['key'] ? $this->prevBlock( $currIndex ) : $this->setIndex( $currIndex ) );
		}
		else
		{
			// Unset all data (but optionally keep globals)
			if ( $keepGlobals && ! empty( $this->data['__GLOBALS'] ) )
			{
				$globals = $this->data['__GLOBALS'];
				$this->data = array();
				$this->data['__GLOBALS'] = $globals;
			}
			else
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
	 * @param array $data
	 * @param bool $clearData
	 * @return string
	 */
	public function fetch( $file, array & $data = null, $clearData = false )
	{
		if ( empty( $file ) || ! is_string( $file ) )
			throw new Exception( "Expecting parameter 1 to be a (not empty) string" );
		if ( ! is_bool( $clearData ) )
			throw new Exception( "Expecting parameter 2 to be a boolean" );
		if ( ! file_exists( $file ) )
			throw new Exception( "Can not find file '" . $_blockFile . "'" );

		// Use assigned data
		if ( $data === null )
			$data = &$this->data;

		$template = $this->parse( file_get_contents( $file ), $data );

		// Clear data
		if ( $clearData )
			$this->clearData( '', false );

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
	public function parse( $template, array & $data = null )
	{
		if ( empty( $template ) || ! is_string( $template ) )
			throw new Exception( "Expecting parameter 1 to be a (not empty) string" );

		// Prepare
		$this->_globals = null;

		// Use assigned data
		if ( ! $data )
			$data = &$this->data;

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
	protected function _parse( $template, array & $data = null, $depth = 0 )
	{
		// Save data for GLOBAL variables
		if ( empty( $this->_globals ) && ! empty( $data['__GLOBALS'] ) && is_array( $data['__GLOBALS'] ) )
			$this->_globals = $data['__GLOBALS'];

		// Remove indentation
		if ( $this->beautifyCode && $depth )
			$template = preg_replace( "/\\r\\n[\\t ]*/", "\r\n", $template );

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
							$_replaceData .= $this->_parse( $blocks[$key][2], $data[$_blockName][$_blockKey], $depth + 1 );
						else
							$_replaceData .= $this->_parse( $blocks[$key][2], null, $depth + 1 ); // No data to pass on
					}
				}

				// Replace block with filled block(s)
				$template = substr_replace( $template, $_replaceData, strpos( $template, $blocks[$key][0] ), strlen( $blocks[$key][0] ) );
			}
		}

		// Variables
		if ( preg_match_all( $this->variablePattern, $template, $variables, PREG_SET_ORDER ) )
		{
			// Replace variables
			foreach ( $variables as & $variable )
			{
				$_replaceData = '';

				// Check for GLOBALS
				if ( isset( $this->_globals[$variable[1]] ) )
					$_replaceData = $this->_globals[$variable[1]];

				// Check for data variable
				elseif ( isset( $data[$variable[1]] ) )
					$_replaceData = $data[$variable[1]];

				// Remove newline from RAW
				if ( $variable[0][0] == "\n" )
					$variable[0] = substr( $variable[0], 1 );

				$template = substr_replace( $template, $_replaceData, strpos( $template, $variable[0] ), strlen( $variable[0] ) );
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
								$_replaceData .= $this->_parse( "\r\n" . file_get_contents( $_blockFile ), $data, $depth + 1 );
							else
								throw new Exception( "Can not find file '{$_blockFile}' for include block '[{$_includeBlockName}]'" );
						}
					}
				}

				// Replace block with filled block(s)
				$template = substr_replace( $template, $_replaceData, strpos( $template, $includeBlock[0] ), strlen( $includeBlock[0] ) );
			}
		}

		// Return parsed template (part) + add indentation
		return ( $this->beautifyCode && $depth ? preg_replace( "/\\r\\n/", "\r\n\t", $template ) : $template );
	}
}

// EOF
