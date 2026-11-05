/**
 * StyleKit free for elementor.
 *
 * @since 4.2.0
 */
jQuery( function( $ ) {

	var controlView = elementor.modules.controls.BaseMultiple.extend( {

		onReady: function() {

			var api = this;

			setTimeout( function() {

				var sk = $( 'a[data-sk="elementor-control-sk-' + api.model.cid + '"]' );

				sk.off().on( 'click', function() {

					alert( sk_aiL10n.pro );

					return false;

				});

			}, 250 );

		},

	});

	elementor.addControlView( 'stylekit_pro', controlView );

});