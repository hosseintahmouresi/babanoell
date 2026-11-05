/**
 * Codevz customize controls JS
 * 
 * @author Codevz
 * @link http://codevz.com
 */
( function( $ ) {
  'use strict';

	if ( wp.customize ) {

		var api = wp.customize;

		api.bind( 'ready', function () {

			var sidebar = $( '.wp-full-overlay-sidebar' ),
					controls = [],
					timeout = 0;

			// Enable controls inview.
			const activatedSections = {};
			function activateControlsInSection(sectionId) {
				if (activatedSections[sectionId]) {
					return;
				}
				activatedSections[sectionId] = true;
				api.section(sectionId, function(section) {
					if (!section) return;

					section.controls().forEach(function(control) {

						if (!control.active()) {
							control.active.set(true);
						}

						// Prevent control from being deactivated again
						const originalSet = control.active.set;
						control.active.set = function(value) {
							if (value === false) {
								// ignore attempts to deactivate
								return;
							}
							originalSet.call(control.active, value);
						};

						if (location.search.includes('codevz_quick_options')) {
							const setting = control.setting || (Array.isArray(control.settings) ? control.settings[0] : null);
							if (setting && setting.transport !== 'postMessage') {
								setting.transport = 'postMessage';
							}
						}

					});
				});
			}
			// Enable controls inview.
			Object.keys(api.section._value).forEach(function(sectionId) {
				const section = api.section(sectionId);
				if (!section) return;

				section.expanded.bind(function(expanded) {
					if (expanded) {
						activateControlsInSection(sectionId);
					}
				});
			});


			// Move woocommerce panels into theme woocommerce.
	    // List WooCommerce section IDs you want to move
	    const sectionsToMove = [
	        'woocommerce_product_catalog',
	        'woocommerce_product_images',
	        'woocommerce_checkout',
	        'woocommerce_store_notice'
	    ];
	    sectionsToMove.forEach( function ( sectionID ) {
	        api.section( sectionID, function ( section ) {
	            section.panel( 'codevz_theme_options-post_type_product' );
	        });
	    });
	    // Optional: hide the default WooCommerce panel if empty
	    api.panel( 'woocommerce', function ( panel ) {
	        panel.deactivate(); // or use panel.active.set(false);
	    });

			// Show cookie div on setting section.
			function showCookie() {
				$( '#customize-preview > iframe' ).contents().find( '.xtra-cookie' ).css( 'display', 'flex' );
			}
			api.section( 'codevz_theme_options-cookie', function( section ) {
				section.expanded.bind(function(isExpanded) {
					if ( isExpanded ) {
						showCookie();
					}
				});
			});
			[ 'cookie', 'cookie_content', 'cookie_button' ].forEach( function( controlId ) {
				api( controlId, function( setting ) {
					setting.bind(function() {
						showCookie();
					});
				});
			});

			// Show PWA div on setting section.
			function showPWA() {
				$( '#customize-preview > iframe' ).contents().find( '.codevz-pwa' ).removeClass( 'codevz-pwa-desktop' ).addClass( 'codevz-pwa-show' );
			}
			api.section( 'codevz_theme_options-general_pwa', function( section ) {
				section.expanded.bind( function( isExpanded ) {
					if ( isExpanded ) {
						setTimeout( function() {
							showPWA();
							$( '.preview-tablet' ).trigger( 'click' );
						}, 200 );
					}
				});
			});

			// Add customizer search icon.
			if ( ! $( '.xtra-search-icon' ).length ) {

				$( '.customize-controls-close' ).after( '<a class="xtra-search-icon" href="#" title="' + sk_aiL10n.search + '"><i class="fas fa-search"></i><span class="screen-reader-text">' + sk_aiL10n.search + '</span></a>' );

			}

			$( 'body' ).on( 'click', '.xtra-search-icon', function() {

				// Add search box.
				if ( ! $( '.xtra-search' ).length ) {

					$( '.wp-full-overlay-header' ).after( '<div class="xtra-search"><i class="fas fa-search"></i><input type="search" placeholder="' + sk_aiL10n.search_pl + '" /></div>' );

					$( '#customize-theme-controls' ).prepend( '<ul class="customize-pane-parent xtra-search-results"></ul>' );

				}

				// Activate search box.
				sidebar.toggleClass( 'xtra-search-active' );

				// Focus input.
				setTimeout( function() {

					$( '.xtra-search' ).find( 'input' ).focus();

				}, 50 );

			}).on( 'click', '.xtra-search-results li', function() {

				var panel = $( this ).attr( 'data-panel' ),
						section = $( this ).attr( 'data-section' );

				section && api.section( section ).expand();

			}).on( 'keyup search', '.xtra-search input', function( e ) {

				var $this = $( this );

				// Hide search box on close/clear icon.
				e.type === 'search' && sidebar.removeClass( 'xtra-search-active' );

				// Clear debounce.
				clearTimeout( timeout );

				// Debounce.
				timeout = setTimeout( function() {

					// Cache controls first time.
					if ( ! controls.length ) {

						// WordPress core controls.
						$.map( _wpCustomizeSettings.controls, function( control ) {

							if ( control.section ) {

								if ( control.settings.default && control.settings.default.indexOf( '_tablet' ) < 0 && control.settings.default.indexOf( '_mobile' ) < 0 ) {

									var newControl = {},
											section = api.section( control.section ),
											panel = api.panel( section.params.panel );

									// CSF only.
									if ( ! control.label ) {

										control.label = $( control.content ).find( 'h4' ).first().text();

										if ( control.label && control.settings.default.indexOf( '_css_' ) >= 0 ) {
											control.label = control.label + ' style';
										}

									}

									if ( control.settings.default == 'codevz_theme_options[footer_2_left]' ) {

										control.description = 'Copyright';

									}

									newControl.id 					= control.settings.default;
									newControl.title 				= control.label || '';
									newControl.description 	= control.description || '';
									newControl.panel 				= panel ? panel.params.id : '';
									newControl.panelName 		= panel ? panel.params.title : '';
									newControl.section 			= section.params.id || '';
									newControl.sectionName 	= section.params.title || '';

									controls.push( newControl );

								}

							}

						});

					}

					// Search value.
					var value = $this.val(),
						searchResults = $( '.xtra-search-results' );

					if ( value.length >= 2 ) {

						// Find matches.
						var matches = controls.filter( function( control ) {

							var keywords = value.split( ' ' ),
								length = 0;

							keywords.map( function( keyword ) {

								var regex = new RegExp( keyword, 'gi' );

								length = JSON.stringify( control ).match( regex ) ? length + 1 : length;

							});

							return keywords.length === length;

						});

						// If find matches.
						if ( matches.length ) {

							// Collapse sections/panels.
							api.section.each( function( section ) {

								section.collapse(
									{
										duration: 0,
										completeCallback: function() {

											api.panel.each( function( panel ) {

												panel.collapse( { duration: 0 } );

											} );

										}
									}
								);

							});

							var sections 	= [],
									section 	= '',
									html 			= '';

							// Search results.
							matches.map( function( control ) {

								section = control.section;

								if ( $.inArray( section, sections ) === -1 ) {

									sections.push( section );

									var titles = matches.map( function( control ) {

										return section === control.section && control.title;

									} );

									var uniqueNames = [];

									$.each( titles, function( i, el ) {

										if ( $.inArray( el, uniqueNames ) === -1 ) {
											uniqueNames.push( el );
										}

									});

									titles = uniqueNames.filter( Boolean );

									html += '<li id="accordion-section-' + section + '" class="accordion-section control-section control-section-default" aria-owns="sub-accordion-section-' + section + '" data-panel="' + control.panel + '" data-section="' + section + '"><h3 class="accordion-section-title"><b>' + control.panelName + ' ▸ ' + control.sectionName + '</b><span>' + titles.slice( 0, 8 ).join( ', ' ) + '</span></h3></li>';

								}

							});

							searchResults.html( '<li>' + sk_aiL10n.search_result +  '</li>' + html );

						} else {

							// Not found any matches.
							searchResults.html( '<li>' + sk_aiL10n.search_not +  '</li>' );

						}

					} else {

						// No keywords.
						searchResults.html( '' );

					}

				}, 500 );

			});

			// Section focus.
			api.previewer.bind( 'xtra-section', function( args ) {

				if ( args.section === 'header_4' ) {
					args.section = 'mobile_header';
				}

				// Focus.
				api.section( 'codevz_theme_options-' + args.section ).focus();

				// Find element.
				if ( args.index ) {

					var optionName = '#customize-control-codevz_theme_options-',
							accordion = $( optionName + args.section + '_left' );

					if ( args.index.indexOf( 'center' ) >= 0 ) {
						accordion = $( optionName + args.section + '_center' );

					} else if ( args.index.indexOf( 'right' ) >= 0 ) {
						accordion = $( optionName + args.section + '_right' );

					}

					// Open accordion.
					setTimeout( function() {

						var index = parseInt( args.index.replace( /[^0-9]/g, '' ) ),
							title = accordion.find( '.codevz-cloneable-wrapper > div' ).eq( index ).find( '> h4' );

						!title.hasClass( 'ui-state-active' ) && title.trigger( 'click' );

						title.offset() && $( '#customize-controls .wp-full-overlay-sidebar-content' ).scrollTop( title.offset().top - 150 );

					}, 1000 );

				}

			} );

		} );

	}

})( jQuery );