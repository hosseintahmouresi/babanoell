! function( $ ) {
	"use strict";

	Codevz_Plus.google_map = function() {

		var gmap = $( '.gmap' ), timeout;

		if ( gmap.length ) {

			function waitForGoogleMaps( callback, retries = 50, interval = 250 ) {
				let attempts = 0;
				const check = setInterval(function () {
					if (typeof google === 'object' && typeof google.maps === 'object') {
						clearInterval(check);
						callback();
					} else if (++attempts >= retries) {
						clearInterval(check);
						console.warn('Google Maps not loaded in time');
					}
				}, interval);
			}

			// Check Elementor editor.
			var elementor = $( '.elementor-editor-active' ).length;

			// Load google maps JS file.
			if ( ! $( '#cz_google_map_api_js' ).length ) {

				var sc = document.createElement( 'script' );
				sc.setAttribute( 'id','cz_google_map_api_js' );
				sc.setAttribute( 'src','https://maps.google.com/maps/api/js?key=' + gmap.data( "api-key" ) + '&loading=async&callback=Codevz_Plus.google_map' );
				document.head.appendChild( sc );

			}

			waitForGoogleMaps( function () {

				$( window ).on( 'scroll.xtra_gmap', function() {

					clearTimeout( timeout );

					timeout = setTimeout( function() {

						if ( Codevz_Plus.inview( gmap, 250 ) ) {

							if ( typeof google != 'undefined' ) {

								gmap.not( '.done' ).html( '' ).each(function () {

									var dis = $(this),
										fn = 'mapfunction_' + dis.attr('id'),
										attempts = 0,
										maxAttempts = 25;

									function tryInitMap() {
										if (typeof window[fn] === 'function') {
											try {
												window[fn]();
											} catch (e) {
												console.error('Error running map function:', fn, e);
											}
											dis.addClass('done');
										} else {
											attempts++;
											if (attempts < maxAttempts) {
												setTimeout(tryInitMap, 250);
											} else {
												console.warn('Map function not found after retries:', fn);
											}
										}
									}

									tryInitMap();
								});

								if ( $( '.gmap.done' ).length === gmap.length ) {
									$( window ).trigger( 'resize' ).off( 'scroll.xtra_gmap' );
								}

							}

						}

					}, 250 );

				}).trigger( 'scroll.xtra_gmap' );

			});


		}

	};

	Codevz_Plus.google_map();

}( jQuery );