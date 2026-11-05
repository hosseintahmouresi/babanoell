jQuery( function( $ ) {
	"use strict";

	Codevz_Plus.mobileFixedNav = function() {

		var mobileNav = $( '.xtra-fixed-mobile-nav' ),
			others 	  = $( 'footer, .backtotop' ),
			overlay   = $( '.inner_layout > .cz_overlay' ),
			currentURL = window.location.href,
			currentURL = currentURL.indexOf( '?' ) >= 0 ? currentURL.split( '?' )[0] : currentURL,
			currentURL = currentURL.indexOf( '#' ) >= 0 ? currentURL.split( '#' )[0] : currentURL;
			currentURL = currentURL.replace( /\/$/, "" );

		if ( mobileNav.length ) {

			// Fix back to top icon pos & Footer margin.
			const updateMfnMargin = () => {
				if ( $( window ).width() <= 768 ) {
					const h = mobileNav.outerHeight();
					others.css( 'margin-bottom', h ).next( '.fixed_contact' ).css( 'margin-bottom', h );
				} else {
					others.css( 'margin-bottom', '' ).next( '.fixed_contact' ).css( 'margin-bottom', '' );
				}
			};
			updateMfnMargin();
			let debounceTimer;
			$( window ).on( 'resize', () => {
				clearTimeout( debounceTimer );
				debounceTimer = setTimeout( updateMfnMargin, 500 );
			});

			// Set active class.
			mobileNav.find( 'a' ).each( function() {

				var $this = $( this );

				if ( $this.attr( 'href' ).replace( /\/$/, "" ) == currentURL ) {

					$this.addClass( 'xtra-active' );

					if ( $( '.xtra-fixed-mobile-nav-svg' ).length && ! $this.find( 'svg' ).length ) {

						$this.append( '<svg viewBox="0 0 680 720" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style=" position: absolute; bottom: 0px; width: 48px; "> <defs> <filter id="codevz_goo" x="-50%" width="200%" y="-50%" height="200%" color-interpolation-filters="sRGB"> <feGaussianBlur in="SourceGraphic" stdDeviation="12" result="blur"></feGaussianBlur> <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 21 -7" result="cm"></feColorMatrix> </filter> </defs> <g filter="url(#codevz_goo)"> <path d="M340 540C454 540 485 668 680 720H0C195 668 226 540 340 540Z"></path> <circle cx="340" cy="660" r="40"></circle> </g> </svg>' );

					}

				}

			});

			// Expandable special menu.
			$( 'body' ).on( 'click', '.xtra-fixed-mobile-nav-e', function( e ) {

				if ( $( e.target ).closest( '.xtra-fixed-mobile-nav-e > div' ).length ) {
					return;
				}

				overlay.fadeToggle();
				$( this ).toggleClass( 'czico-close-bold xtra-fixed-mobile-nav-e-active' ).find( '> div' ).fadeToggle();

			}).on( 'click', function( e ) {

				if ( $( e.target ).closest( '.outer_search .search, .sub-menu, .cz_cart_items, .icon_mobile_offcanvas_menu, .xtra-fixed-mobile-nav' ).length ) {
					return;
				}

				overlay.fadeOut();
				$( '.xtra-fixed-mobile-nav-e' ).removeClass( 'czico-close-bold' ).find( '> div' ).fadeOut();

			});

		}

	};

	Codevz_Plus.mobileFixedNav();

});