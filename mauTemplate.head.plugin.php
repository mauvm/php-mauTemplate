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
	// Version
	const		VERSION		= '1.1.0';

	// Data
	private 	$tpl,

				$meta 		= array(),
				$styles 	= array(),
				$scripts 	= array();

	/**
	 * mauTemplateHead::__construct()
	 *
	 * @param mauTemplate $mauTemplate
	 * @return
	 */
	function __construct( mauTemplate & $mauTemplate )
	{
		// Prepare and install plugin
		$this->tpl 			= & $mauTemplate;
		$mauTemplate->head 	= & $this;
	}

	// ---------------------------------------- //
	//   Managing head
	// ---------------------------------------- //
	/**
	 * mauTemplateHead::addMeta()
	 *
	 * @param string $key
	 * @param string $content
	 * @param string $attr
	 * @param string $block
	 * @return
	 */
	function addMeta( $key, $content, $attr = 'name', $block = 'meta' )
	{
		$compare = $content . ':' . $attr . ':' . $key;

		// Check if already added
		if( ! in_array( $compare, $this->meta ) )
		{
			// Add meta block
			$this->tpl->newBlock( '/' . $block, array(
				'attr'		=> $attr,
				'key'		=> $key,
				'content'	=> $content
			) );

			$this->tpl->restoreIndex();

			$this->meta[] = $compare;
		}
	}

	/**
	 * mauTemplateHead::addStyle()
	 *
	 * @param string $HREF
	 * @param string $media
	 * @param string $block
	 * @return
	 */
	function addStyle( $HREF, $media = 'screen', $block = 'style' )
	{
		$compare = $HREF . ':' . $media;

		// Check if already added
		if( ! in_array( $compare, $this->styles ) )
		{
			// Add style block
			$this->tpl->newBlock( '/' . $block, array(
				'href'		=> $HREF,
				'media'		=> $media
			) );

			$this->tpl->restoreIndex();

			$this->styles[] = $compare;
		}
	}

	/**
	 * mauTemplateHead::addScript()
	 *
	 * @param string $SRC
	 * @param string $block
	 * @return
	 */
	function addScript( $SRC, $block = 'script' )
	{
		// Check if already added
		if( ! in_array( $SRC, $this->scripts ) )
		{
			// Add script block
			$this->tpl->newBlock( '/' . $block, array(
				'src'		=> $SRC
			) );

			$this->tpl->restoreIndex();

			$this->scripts[] = $SRC;
		}
	}
}

// EOF
