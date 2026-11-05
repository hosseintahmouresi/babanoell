jQuery( function( $ ) {
	"use strict";

	Codevz_Plus.subscribe = function() {

		$( 'body' ).off( 'submit', '.cz_subscribe_ajax' ).on( 'submit', '.cz_subscribe_ajax', function( e ) {

			e.preventDefault();

			var $this 	= $( this ),
				nonce 	= $this.attr( 'data-nonce' ),
				email 	= $this.find( 'input[name=email]' ).val(),
				name 	= $this.find( 'input[name=name]' ).val() || email.substring( 0, email.indexOf( '@' ) ),
				website = $this.find( 'input[name=website]' ).val(),
				msg 	= $this.find( '.cz_subscribe_msg' );

			msg.removeClass( 'cz_subscribe_msg_error' ).hide();

			$this.addClass( 'cz_subscribe_loader' ).find( 'input, button' ).attr( 'disabled', 'disabled' );

			$.get( $( 'body' ).attr( 'data-ajax' ), { action: 'cz_subscribe_ajax', name: name, email: email, website: website, nonce: nonce }, function( r ) {

				$this.removeClass( 'cz_subscribe_loader' ).find( 'input, button' ).removeAttr( 'disabled' );

				msg.show().html( r.message );

				if ( r.type ) {

					if ( r.type == '200' ) {
						$this[0].reset();
					}

					if ( r.type == '202' ) {
						msg.addClass( 'cz_subscribe_msg_error' );
					}

				}

			});

		});

	};

	Codevz_Plus.subscribe();

});