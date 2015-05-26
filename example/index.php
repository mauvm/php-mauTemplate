<?php

require_once ( '../mauTemplate.class.php' );

$oMT = new mauTemplate;

$oMT->newBlock( 'content' );
$oMT->assign( 'body', '<p>Hello world!</p>' );

// Code blocks
for( $i = 1; $i < 5; ++$i )
{
	$oMT->newBlock( 'code' );
	// or $oMT->newBlock ( '/content/code' );

	$oMT->assign( 'code', 'code block ' . $i );
}

var_dump($oMT->getIndex());

$oMT->newBlock( './../../content' );
$oMT->assign( 'body', 'See? Another content block!' );

// The slash makes the index go back to root
$oMT->newBlock( '/footer' );
$oMT->assign( 'body', '<p>by mauTemplate</p>' );

echo $oMT->fetch( 'template.tpl' );

?>
