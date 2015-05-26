<?php

/**
 * mauTemplateHead
 * 
 * mauTemplate plugin to manage HTML head
 * 
 * @category	HTML
 * @author 		Maurits van Mastrigt <mauvm@hotmail.com>
 * @copyright	Copyright © 2010 Maurits van Mastrigt. All rights reserved.
 * @license		http://www.gnu.org/licenses/gpl.html GNU General Public License
 * @version		1.1 BETA
 * @access		public
 */
class mauTemplateHead
{
	private $oMT;

	private $aMeta = array();
	private $aStyles = array();
	private $aScripts = array();
	
	private $sDir = null;

	public function __construct( mauTemplate & $oMT, $bMinify )
	{
		// Prepare and install plugin
		$this->oMT = &$oMT;
		$oMT->head = &$this;
	}

	// ---------------------------------------- //
	//   Managing head
	// ---------------------------------------- //

	public function addMeta( $sKey, $sContent, $sAttr = 'name' )
	{
		$sCompare = $sContent . ':' . $sAttr . ':' . $sKey;

		// Check if in use
		if ( ! in_array( $sCompare, $this->aMeta ) )
		{
			// Add meta block
			$this->oMT->newBlock( '/meta' );
			$this->oMT->assign( 'attr', $sAttr );
			$this->oMT->assign( 'key', $sKey );
			$this->oMT->assign( 'content', $sContent );
			$this->oMT->restoreIndex();

			$this->aMeta[] = $sCompare;
		}
	}

	public function addStyle( $sHREF, $sMedia = 'screen' )
	{
		$sCompare = $sHREF . ':' . $sMedia;

		// Check if in use
		if ( ! in_array( $sCompare, $this->aStyles ) )
		{
			// Add style block
			$this->oMT->newBlock( '/style' );
			$this->oMT->assign( 'href', $sHREF );
			$this->oMT->assign( 'media', $sMedia );
			$this->oMT->restoreIndex();

			$this->aStyles[] = $sCompare;
		}
	}

	public function addScript( $sSRC, $bMinify = false, $bCombine = false )
	{
		// Check if in use
		if ( ! in_array( $sSRC, $this->aScripts ) )
		{
			// Add script block
			$this->oMT->newBlock( '/script' );
			$this->oMT->assign( 'src', $sSRC );
			$this->oMT->restoreIndex();

			$this->aScripts[] = $sSRC;
		}
	}

	// ---------------------------------------- //
	//   Compressing / speed optimalisation
	// ---------------------------------------- //

	public function setCacheDirectory( $sDir )
	{
		if ( ! is_dir( $sDir ) )
			throw new Exception( "Invalid directory '{$sDir}'" );
			
		$this->sDir = &$sDir;
	}
}

// EOF
