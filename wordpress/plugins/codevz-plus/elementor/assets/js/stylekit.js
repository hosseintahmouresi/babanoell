/**
 * StyleKit for elementor.
 *
 * @since 4.2.0
 */
jQuery( function( $ ) {

	// General function to add/update the edit link.
	function updateEditLink( $select ) {
		const id = $select.val();
		if ( ! id || id == '0' ) {
			return;
		}

		const editURL = window.location.href.replace(/post=\d+/, 'post=' + id);
		let $link = $select.parent().next('.xtra-edit-link');

		if ($link.length) {
			$link.attr('href', editURL);
		} else {
			$select.parent().after('<a class="xtra-edit-link" href="' + editURL + '" target="_blank"><i class="far fa-pen-to-square"></i></a>');
			$select.parent().parent().addClass( 'codevz-select-page' );
		}
	}

	// Observe panel changes
	elementor.hooks.addAction('panel/open_editor/widget', function() {
		const observer = new MutationObserver(function() {
			$('select[data-setting="xtra_elementor_template"]').each(function() {
				updateEditLink($(this));
			});
		});

		observer.observe(document.querySelector('.elementor-panel'), { childList: true, subtree: true });
	});

	// Update link on select change
	$(document).on('change', 'select[data-setting="xtra_elementor_template"]', function() {
		updateEditLink($(this));
	});


	// Plugin object.
	window[ 'xtraElementor' ] = {};

	// StyleKit live control view.
	var controlView = elementor.modules.controls.BaseMultiple.extend( {

		// Control on ready.
		onReady: function() {

			var api = this;

			setTimeout( function() {

				var sk = $( 'a[data-sk="elementor-control-sk-' + api.model.cid + '"]' ),
					ss = sk.attr( 'data-name' );

				sk.parent().codevz_reload_script( false, api );

				window[ 'xtraElementor' ][ ss ] = api;

			}, 250 );

		},

		// Destroy.
		onBeforeDestroy: function() {
			$( '.ui-dialog-titlebar-close' ).trigger( 'click' );
		},

	});

	elementor.addControlView( 'stylekit', controlView );

});