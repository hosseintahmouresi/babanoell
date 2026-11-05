jQuery( function( $ ) {
	"use strict";

	var pwa 	= $( '.codevz-pwa' ),
		cookie 	= pwa.attr( 'data-cookie' ),
		isiOS 	= /iPad|iPhone/.test( navigator.userAgent ),
		isMobile= /iPhone|iPad|iPod|Android|webOS|BlackBerry|IEMobile|Opera Mini/i.test( navigator.userAgent );

	// Close PWA with cookie.
	$( 'body' ).on( 'click', '.codevz-pwa-close', function() {

		pwa.removeClass( 'codevz-pwa-show' );

		document.cookie = cookie + "=true; expires=Fri, 31 Dec 2040 23:59:59 GMT; path=/";

	});

	// Check standalone display.
	if ( window.matchMedia( '(display-mode: standalone)' ).matches || window.navigator.standalone === true ) {

		pwa.removeClass( 'codevz-pwa-show' );

		$( '.codevz-pwa-close' ).trigger( 'click' );

	// Check cookie to show popup.
	} else if ( ! document.cookie.includes( cookie + '=' ) ) {

		pwa.addClass( 'codevz-pwa-show' );

	}

	// Check mobile or desktop.
	pwa.addClass( isMobile ? 'codevz-pwa-mobile' : 'codevz-pwa-desktop' );

	// Check iOS and android.
	pwa.removeClass( 'codevz-pwa-ios codevz-pwa-android' ).addClass( isiOS ? 'codevz-pwa-ios' : 'codevz-pwa-android' );

	// Customize preview.
	if ( $( '.customize-partial-edit-shortcuts-shown' ).length ) {
		pwa.removeClass( 'codevz-pwa-desktop' ).addClass( 'codevz-pwa-show codevz-pwa-mobile' );
	}

});