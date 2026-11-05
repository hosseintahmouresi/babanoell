! function( $ ) {
	"use strict";

	Codevz_Plus.counter = function() {

		$( '.cz_counter' ).codevzPlus( 'counter', function( x, i ) {

			var del = $( window ).width() <= 480 ? 0 : parseInt( x.data( 'delay' ) ) || 0, 
				eln = x.find( '.cz_counter_num' ),
				org = Codevz_Plus.convertNumbers( eln.text(), true ),
				dur = parseInt( x.data( 'duration' ) ),
				com = !x.data( 'disable-comma' ),
				tls = com ? Math.ceil( org ).toLocaleString() : Math.ceil( org );

			// No counter.
			if ( dur == 0 || x.hasClass( 'done' ) || $( window ).width() <= 768 ) {

				eln.html( Codevz_Plus.convertNumbers( tls ) );

				return;

			}

			// Set zero.
			eln.html( Codevz_Plus.convertNumbers( '0' ) );

			// On page scrolling
			$( window ).on( 'scroll.counter', function() {

				if ( Codevz_Plus.inview( x ) && ! x.hasClass( 'done' ) ) {

					x.addClass( 'done' ).delay( del ).prop( 'Counter', 0 ).animate(
						{
							Counter: org
						},
						{
							duration: dur,
							easing: 'swing',
							step: function () {
								eln.text( Codevz_Plus.convertNumbers( com ? Math.ceil( this.Counter ).toLocaleString() : Math.ceil( this.Counter ) ) );
							},
							complete: function () {
								eln.text( Codevz_Plus.convertNumbers( tls ) );
							}
						}
					);

				}

				if ( ! $( '.cz_counter:not(.done)' ).length ) {
					$( window ).off( 'scroll.counter' );
				}

			}).trigger( 'scroll.counter' );

		});

	};

	Codevz_Plus.counter();

}( jQuery );