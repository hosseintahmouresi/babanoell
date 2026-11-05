! function( $ ) {
	"use strict";

	Codevz_Plus.expanding_cards = function( wpb ) {

		var active = 'codevz-plus-ex-card-active';

		$( 'body' ).on( 'click', '.codevz-plus-ex-card', function() {

			$( this ).addClass( active ).siblings().removeClass( active );

		}).on( 'mouseenter', '.codevz-plus-ex-cards-hover .codevz-plus-ex-card', function() {

			$( this ).addClass( active ).siblings().removeClass( active );

		});

	};

	Codevz_Plus.expanding_cards();

}( jQuery );