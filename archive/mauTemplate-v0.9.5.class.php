<?php

/**
 * mauTemplate
 * 
 * Flextendable Template Engine
 * 
 * @category	HTML
 * @author 		Maurits van Mastrigt <mauvm@hotmail.com>
 * @copyright	Copyright © 2010 Maurits van Mastrigt. All rights reserved.
 * @license		http://www.gnu.org/licenses/gpl.html GNU General Public License
 * @version		0.9.6 BETA
 * @access		public
 * 
 * @feature		code beautifying
 * @feature		parse benchmarking
 * @feature		prevBlock and nextBlock functions
 * @feature		setData function
 * @feature		caching
 * @feature		if/else blocks
 */
class mauTemplate
{
	// ---------------------------------------- //
	//   Variables
	// ---------------------------------------- //

	// Version
	const VERSION = '0.9.5';

	// Data
	protected $aData = array();
	protected $aGlobals = array();
	protected $aEscapeChars = array( array( '{', '[' ), array( '\{', '\[' ) ); // Prevents variale/block insertion
	protected $_aGlobals = array();

	// Indexing
	protected $aIndex;
	protected $aIndexes;
	protected $aIndexParts;

	// Temp indexing
	protected $aTempIndexes = array();

	// Code beautifying
	//protected $bBeautifyCode = true;

	// Block/variable patterns
	protected $sBlockPattern = '/[\r\n]{0,1}[\t| ]*?\<!--[\t| ]?\[([a-zA-Z0-9-_]{3,32}+)\][\t| ]?--\>(.+?)[\r\n]{0,1}[\t| ]*?\<!--[\t| ]?\[\/\1\][\t| ]?--\>/is';
	//                           ----------1-------- -----2----- ------------3----------- -----2----- -4- ---------1--------- -----2----- ---3--- -----2----
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get block name (length 3-32)
	// 4. Get block data

	protected $sVariablePattern = '/(?(?=[\r\n]+)[\r\n]?[\t| ]*|)[^\\\\](\{([a-zA-Z0-9-_]{2,32}+)\})/';
	//                              --------------1-------------- --2-- -3- ----------4--------- -3-
	// -- Legend
	// 1. Code beautifying
	// 2. Process escape character
	// 3. Variable prefix/surfix
	// 4. Get variable name (length 2-32)

	protected $sIncludeBlockPattern = '/[\r\n]{0,1}([\t| ]*)?\<!--[\t| ]?\[\+([a-zA-Z0-9-_]{3,32}+)\][\t| ]?--\>/';
	//                                  -----------1--------- -----2----- --------------3------------ -----2----
	// -- Legend
	// 1. Code beautifying
	// 2. Block prefix/surfix
	// 3. Get include block name (length 3-32)

	/**
	 * mauTemplate::__construct()
	 * 
	 * @return
	 */
	public function __construct()
	{
		// Set default index
		$this->aIndex = &$this->aData;
		$this->aIndexParts = array();
	}

	// ---------------------------------------- //
	//   Data retrieval functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::indexToString()
	 * 
	 * Returns a cleaned index string
	 * 
	 * @param array $aIndex
	 * @return string
	 */
	public function indexToString( array & $aIndex = null )
	{
		// None given, use current
		if ( null === $aIndex )
			$aIndex = &$this->aIndexParts;

		// Loop through parts
		$sIndex = '';
		foreach ( $aIndex as & $aIndexPart )
		{
			if ( $sIndex )
				$sIndex .= '/';

			// Build index string, use last index as default
			if ( ! empty( $aIndexPart[0] ) )
				$sIndex .= $aIndexPart[0] . ':' . ( isset( $aIndexPart[1] ) ? $aIndexPart[1] : 'last' );
		}

		// Return index string
		return $sIndex;
	}

	/**
	 * mauTemplate::getIndex()
	 * 
	 * Returns a cleaned index (string or array)
	 * 
	 * @param mixed $mIndex
	 * @param bool $bStrict	 
	 * @param bool $bReturnArray
	 * @return string or array
	 */
	public function getIndex( $mIndex = '.', $bStrict = false, $bReturnArray = false )
	{
		// Return current
		if ( '.' === $mIndex )
			return ( $bReturnArray ? $this->aIndexParts : $this->indexToString() );

		// Set and store new index
		$mNewIndex = $this->setIndex( $mIndex, $bStrict, $bReturnArray );

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
	 * @param mixed $mIndex
	 * @return array
	 */
	public function getData( $mIndex = '.' )
	{
		// Set new index
		$this->setIndex( $mIndex, true );

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
	public function getBlockCount( $mIndex = '.' )
	{
		// Get parent index
		$aIndex = $this->setIndex( $mIndex, true, true );
		$aLastIndex = end( $aIndex );
		$aIndex = $this->setIndex( '..', true, true );

		// Get count
		$iCount = 0;
		if ( ! empty( $this->aIndex['/' . $aLastIndex[0]] ) )
			$iCount = count( $this->aIndex['/' . $aLastIndex[0]] );

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
	 * @param bool $bAppendBlock
	 * @return string or array
	 */
	public function setIndex( $mIndex, $bStrict = false, $bReturnArray = false, $bAppendBlock = false )
	{
		// Verify parameters
		if ( is_string( $mIndex ) )
			$mIndex = explode( '/', $mIndex );
		elseif ( ! is_array( $mIndex ) )
			throw new Exception( "Invalid parameter 'mIndex' (must be string or array)" );

		// Store index
		$this->aIndexes[] = $this->aIndexParts;

		// Absolute index
		if ( empty( $mIndex[0] ) )
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
			if ( ! $aPart[0] || '.' == $aPart[0] )
				continue;
			elseif ( '..' == $aPart[0] )
				array_pop( $aParts );
			else
			{
				// Skip double
				if ( $aPart[0] === $sLast )
					array_pop( $aParts );

				// Verify block
				if ( $bStrict && preg_match( '/[^a-zA-Z0-9\_\-]/', $aPart[0] ) )
					throw new Exception( "Block '{$aPart[0]}' has illegal characters (a-Z, 0-9 and -_ are allowed)" );

				// Verify index
				if ( ! isset( $aPart[1] ) || '' === $aPart[1] )
					$aPart[1] = 'last';

				$sLast = $aPart[0];
				$aParts[] = $aPart;
			}
		}

		// Only update index reference on change in index parts
		if ( $bAppendBlock || $aParts !== end( $this->aIndexes ) )
		{
			// Set index
			$iLastKey = end( array_keys( $aParts ) );
			foreach ( $aParts as $iKey => &$aPart )
			{
				// Go into block
				if ( $bStrict && ! isset( $this->aIndex['/' . $aPart[0]] ) )
					throw new Exception( "Block '{$aPart[0]}' does not exist!" );

				$this->aIndex = &$this->aIndex['/' . $aPart[0]];

				if ( null === $this->aIndex )
					$this->aIndex = array();

				// Process pseudo indexes
				if ( ! ctype_digit( "{$aPart[1]}" ) )
				{
					ksort( $this->aIndex );

					if ( 'first' == $aPart[1] )
						$aPart[1] = current( array_keys( $this->aIndex ) );
					elseif ( 'last' == $aPart[1] || null === $aPart[1] )
						$aPart[1] = end( array_keys( $this->aIndex ) );
				}

				// Append block
				if ( $bAppendBlock && $iLastKey == $iKey )
				{
					while ( isset( $this->aIndex[$aPart[1]] ) )
						++$aPart[1];
				}

				// Verify block index
				if ( false === $aPart[1] )
					$aPart[1] = 0;
				if ( $bStrict && ! isset( $this->aIndex[$aPart[1]] ) )
					throw new Exception( "Block index '{$aPart[0]}:{$aPart[1]}' does not exist!" );
				if ( ! ctype_digit( "{$aPart[1]}" ) )
					throw new Exception( "Block index '{$aPart[0]}:{$aPart[1]}' is invalid (must be numeric)!" );
				if ( ! isset( $this->aIndex[$aPart[1]] ) )
					$this->aIndex[$aPart[1]] = array();

				// Go into block index
				$this->aIndex = &$this->aIndex[$aPart[1]];
			}
		}

		$this->aIndexParts = $aParts;

		// Return resolved
		return ( $bReturnArray ? $this->aIndexParts : $this->indexToString() );
	}

	/**
	 * mauTemplate::restoreIndex()
	 * 
	 * Restore stored index
	 * 
	 * @param int $iHistory
	 * @param bool $bReturnArray
	 * @return string or array
	 */
	public function restoreIndex( $iHistory = 1, $bReturnArray = false )
	{
		if ( ! $this->aIndexes )
			return false;

		// Get last stored index
		while ( $iHistory-- && null !== end( $this->aIndexes ) )
			$aIndex = array_pop( $this->aIndexes );

		// Set and return index
		return $this->setIndex( array_merge( array( '' ), $aIndex ), true, $bReturnArray );
	}

	/**
	 * mauTemplate::newBlock()
	 * 
	 * Add new block, and assign optional data
	 * 
	 * @param mixed $mIndex
	 * @param bool $bReturnArray
	 * @return bool
	 */
	public function newBlock( $mIndex = '.', $bReturnArray = false )
	{
		return $this->setIndex( $mIndex, false, $bReturnArray, true );
	}

	/**
	 * mauTemplate::assign()
	 * 
	 * Assign value to a key in current index
	 * 
	 * @param mixed $mKey (String or array)
	 * @param string $sValue
	 * @return
	 */
	public function assign( $mKey, $sValue = null )
	{
		if ( is_string( $mKey ) )
			$mKey = array( $mKey => $sValue );
		if ( is_array( $mKey ) )
		{
			// Loop and assign
			foreach ( $mKey as $sKey => &$sValue )
			{
				if ( $sKey != '__GLOBALS' && $sKey[0] != '/' && $sKey[0] != '+' )
					$this->aIndex[$sKey] = ( string )$sValue;
			}
		}
	}

	/**
	 * mauTemplate::assignGlobal()
	 * 
	 * Assigns global variable
	 * 
	 * @param mixed $mKey (String or array)
	 * @param string $sValue
	 * @return
	 */
	public function assignGlobal( $mKey, $sValue = null )
	{
		if ( is_string( $mKey ) )
			$mKey = array( $mKey => &$sValue );
		if ( is_array( $mKey ) )
		{
			// Loop and assign
			foreach ( $mKey as $sKey => &$sValue )
			{
				if ( $sKey != '__GLOBALS' && $sKey[0] != '/' && $sKey[0] != '+' )
					$this->aGlobals[$sKey] = ( string )$sValue;
			}
		}
	}

	/**
	 * mauTemplate::assignInclude()
	 * 
	 * Assign include block (on current index)
	 * 
	 * @param mixed $mKey (string or array)
	 * @param string $sFile
	 * @param int $iIndex
	 * @return
	 */
	public function assignInclude( $mKey, $sFile = null, $iIndex = null )
	{
		if ( is_string( $mKey ) )
			$mKey = array( $mKey => &$sFile );
		if ( is_array( $mKey ) )
		{
			// Loop and assign
			foreach ( $mKey as $sKey => &$sFile )
			{
				// Verify index key
				if ( $sKey != '__GLOBALS' && $sKey[0] != '/' && $sKey[0] != '+' )
				{
					// Check if index is given
					if ( null !== $iIndex && ctype_digit( "{$iIndex}" ) )
					{
						$this->aIndex['+' . $sKey][$iIndex] = ( string )$sFile;
						++$iIndex;
					}
					else
						$this->aIndex['+' . $sKey][] = ( string )$sFile;
				}
			}
		}
	}

	/**
	 * mauTemplate::clearData()
	 * 
	 * Clears (all, or from given index) assigned data
	 * 
	 * @param mixed $mIndex
	 * @param bool $bKeepGlobals
	 * @return
	 */
	public function clearData( $mIndex = '.', $bKeepGlobals = true )
	{
		// Clear given index
		$this->setIndex( $mIndex );
		$this->aIndex = array();

		// Remove globals too
		if ( ! $bKeepGlobals )
			$this->aGlobals = array();
	}

	// ---------------------------------------- //
	//   Parse functions
	// ---------------------------------------- //

	/**
	 * mauTemplate::fetch()
	 * 
	 * Fetches template file and parses it (with optional data clearance)
	 * 
	 * @param string $sFile
	 * @param array $aData (override assigned data)
	 * @param bool $bClearData
	 * @return string
	 */
	public function fetch( $sFile, array & $aData = null, $bClearData = false )
	{
		if ( ! $sFile || ! file_exists( $sFile ) )
			throw new Exception( "Can not find file '{$sFile}'" );

		// Use assigned data
		if ( null === $aData )
			$aData = &$this->aData;

		// Parse template
		$sTemplate = $this->parse( file_get_contents( $sFile ), $aData );

		// Clear data
		if ( $bClearData )
			$this->clearData( '/', false );

		// Return HTML
		return $sTemplate;
	}

	/**
	 * mauTemplate::parse()
	 * 
	 * Parses template string with data array
	 * 
	 * @param string $sTemplate
	 * @param array $aData (override assigned data)
	 * @return string
	 */
	public function parse( $sTemplate, array & $aData = null )
	{
		if ( ! $sTemplate || ! is_string( $sTemplate ) )
			throw new Exception( "Invalid parameter 'sTemplate' (must be a -non empty- string)" );

		// Prepare global variables
		$this->_aGlobals = ( isset( $aData['__GLOBALS'] ) ? $aData['__GLOBALS'] : $this->aGlobals );

		unset( $aData['__GLOBALS'] );

		// Use assigned data
		if ( null === $aData )
			$aData = &$this->aData;

		// Parse template, return HTML
		return str_replace( $this->aEscapeChars[1], $this->aEscapeChars[0], $this->_parse( $sTemplate, $aData ) );
	}

	/**
	 * mauTemplate::_parse()
	 * 
	 * Actual parsing function
	 * 
	 * @param string $sTemplate
	 * @param array $aData
	 * @return string
	 */
	protected function _parse( $sTemplate, array & $aData = null )
	{
		// Blocks
		if ( preg_match_all( $this->sBlockPattern, $sTemplate, $aBlocks, PREG_SET_ORDER ) )
		{
			// ARRAY_KEYS to increase performance in case of large data
			foreach ( array_keys( $aBlocks ) as $iKey )
			{
				$_sReplaceData = '';
				$_sBlockName = '/' . $aBlocks[$iKey][1];

				// Block data assigned?
				if ( isset( $aData[$_sBlockName] ) && is_array( $aData[$_sBlockName] ) )
				{
					// Sort by key
					ksort( $aData[$_sBlockName] );

					// Loop through block data
					foreach ( array_keys( $aData[$_sBlockName] ) as $_iBlockKey )
					{
						if ( ! isset( $aData[$_sBlockName][$_iBlockKey] ) )
							$aData[$_sBlockName][$_iBlockKey] = array();

						$_sReplaceData .= $this->_parse( $aBlocks[$iKey][2], $aData[$_sBlockName][$_iBlockKey] );
					}
				}

				// Replace block with filled block(s)
				$sTemplate = substr_replace( $sTemplate, $_sReplaceData, strpos( $sTemplate, $aBlocks[$iKey][0] ), strlen( $aBlocks[$iKey][0] ) );
			}
		}

		// Variables
		if ( preg_match_all( $this->sVariablePattern, $sTemplate, $aVariables, PREG_SET_ORDER ) )
		{
			// Replace variables
			foreach ( $aVariables as & $aVariable )
			{
				$_sReplaceData = '';

				// Check for GLOBALS
				if ( isset( $this->_aGlobals[$aVariable[2]] ) )
					$_sReplaceData = $this->_aGlobals[$aVariable[2]];

				// Check for data variable
				elseif ( isset( $aData[$aVariable[2]] ) )
					$_sReplaceData = $aData[$aVariable[2]];

				// Check match string, prepend first character of replace string again
				if ( "\n" == $aVariable[0][0] )
				{
					$_sReplaceSearch = &$aVariable[1];
					//$_sReplaceData = str_replace( "\n", $aVariable[0], $_sReplaceData );
				}
				else
				{
					$_sReplaceSearch = &$aVariable[0];
					$_sReplaceData = $aVariable[0][0] . $_sReplaceData;
				}

				// Only replace first occurence and escape certain characters
				$sTemplate = substr_replace( $sTemplate, str_replace( $this->aEscapeChars[0], $this->aEscapeChars[1], $_sReplaceData ), strpos( $sTemplate,
					$_sReplaceSearch ), strlen( $_sReplaceSearch ) );
			}
		}

		// Include blocks
		if ( preg_match_all( $this->sIncludeBlockPattern, $sTemplate, $aIncludeBlocks, PREG_SET_ORDER ) )
		{
			foreach ( $aIncludeBlocks as & $aIncludeBlock )
			{
				$_sReplaceData = '';
				$_sIncludeBlockName = '+' . $aIncludeBlock[1];
				$_sIndentation = '';

				// If indentation is added
				if ( 3 == count( $aIncludeBlock ) )
				{
					$_sIndentation = $aIncludeBlock[1];
					$_sIncludeBlockName = '+' . $aIncludeBlock[2];
				}

				// Block data assigned?
				if ( isset( $aData[$_sIncludeBlockName] ) && is_array( $aData[$_sIncludeBlockName] ) )
				{
					// Sort by key
					ksort( $aData[$_sIncludeBlockName] );

					// Loop through block data
					foreach ( $aData[$_sIncludeBlockName] as $iKey => &$_sBlockFile )
					{
						if ( ! $_sBlockFile )
							continue;
						if ( file_exists( $_sBlockFile ) )
						{
							unset( $aData[$_sIncludeBlockName][$iKey] ); // Prevent infinite include loop
							$_sReplaceData .= $this->_parse( "\r\n" . file_get_contents( $_sBlockFile ), $aData );
						}
						else
							throw new Exception( "Can not find file '{$_sBlockFile}' for include block '[{$_sIncludeBlockName}]'" );
					}
				}

				// Replace block with filled block(s)
				$sTemplate = substr_replace( $sTemplate, $_sReplaceData, strpos( $sTemplate, $aIncludeBlock[0] ), strlen( $aIncludeBlock[0] ) );
			}
		}

		// Return parsed template (part)
		return $sTemplate;
	}
}

// EOF
