/* Codevz Admin Custom JS */
jQuery( function( $ ) {
	'use strict';

	var wind 	= $( window ),
		body 	= $( 'body' ),
		prv 	= $( '.sk_preview_image_inner' ),
		inView 	= function(e, t) {
			return ( t ? t : 800 ) >= e.offset().top;
		};

	// Copy/Paste StyleKit data.
	function codevzClipboard(action, obj = null, callback = null) {
			const performCopy = (text) => {
					if (navigator.clipboard && window.isSecureContext) {
							navigator.clipboard.writeText(text)
									.then(() => {
											callback(obj);
										}
									).catch(err => {
											console.error('Failed to copy StyleKit with Clipboard API:', err);
											fallbackCopy(text);
									});
					} else {
							fallbackCopy(text);
					}
			};

			const fallbackCopy = (text) => {
					const textArea = document.createElement("textarea");
					textArea.value = text;
					document.body.appendChild(textArea);
					textArea.focus();
					textArea.select();
					try {
							document.execCommand('copy');
					} catch (err) {
							console.error('Fallback StyleKit copy failed:', err);
					}
					document.body.removeChild(textArea);
			};

			const performPaste = () => {
					if (navigator.clipboard && window.isSecureContext) {
							navigator.clipboard.readText()
									.then(text => {
											try {
													const obj = JSON.parse(text);
													callback(obj);
											} catch (err) {
													console.error('Failed StyleKit to parse JSON from clipboard:', err);
											}
									})
									.catch(err => {
											console.error('Failed StyleKit to read with Clipboard API:', err);
											fallbackPaste();
									});
					} else {
							fallbackPaste();
					}
			};

			const fallbackPaste = () => {
					const textArea = document.createElement("textarea");
					document.body.appendChild(textArea);
					textArea.focus();
					textArea.select();
					try {
							document.execCommand('paste');
							const text = textArea.value;
							try {
									const obj = JSON.parse(text);
									callback(obj);
							} catch (err) {
									console.error('Failed StyleKit to parse JSON from fallback paste:', err);
							}
					} catch (err) {
							console.error('Fallback StyleKit paste failed:', err);
					}
					document.body.removeChild(textArea);
			};

			if (document.hasFocus()) {
					action === 'copy' ? performCopy(JSON.stringify(obj)) : performPaste();
			} else {
					window.addEventListener('focus', function focusHandler() {
							window.removeEventListener('focus', focusHandler);
							action === 'copy' ? performCopy(JSON.stringify(obj)) : performPaste();
					});
			}
	}

	// Post formats switch.
	if ( $( '.post-formats-select' ).length ) {

		var pf_radio  = $( '#post-formats-select input' ),
			pf_xtra   = $( '.post-formats-select' ),
			pf_func = function( value ) {

			value = ( value === 'standard' ) ? 0 : value;

			pf_xtra.parent().find( '> div:first' ).trigger( 'click' );
			pf_xtra.parent().find( '[data-id="' + value + '"]' ).trigger( 'click' );

			// Switch tab.
			$( '[data-section="codevz_page_meta_post_format_settings"]' ).trigger( 'click' );

			// Scroll to meta setting.
			$( 'html, body, .block-editor-editor-skeleton__content' ).animate({ scrollTop: $( '#codevz_page_meta' ).offset().top - 50 }, 'slow' );

		};

		// PF changes on xtra tabs.
		pf_xtra.on( 'change', function() {

		var value = $( this ).val();

		pf_radio.filter('[value=' + value + ']').prop( 'checked', true ).trigger( 'change' );
		
		// New editor change value.
		var option = $( '.editor-post-format option[value=' + ( value == 0 ? 'standard' : value ) + ']' );
		option.prop( 'selected', 'selected' ).addClass( 'codevz_pf_done' ).parent().trigger( 'change' );

		setTimeout( function() {
			option.removeClass( 'codevz_pf_done' );
		}, 250 );

		});

		// Old editor radio click.
		pf_radio.on( 'click', function() {
		pf_func( $( this ).val() );
		});

		// New editor select changes.
		body.on( 'change', '.editor-post-format select', function() {

		if ( ! $( this ).find( '.codevz_pf_done' ).length ) {

			pf_func( $( this ).val() );

		}

		});

	}

	// Custom sidebars
	if ( $( '.sidebars-column-2' ).length ) {

		// Widget duplicator.
		function addDuplicateLinkToWidget($widget) {

			if ( ! $widget.find('.codevz-duplicate-widget').length ) {

				var params = new URLSearchParams( window.location.search );
				params.set( 'action', 'codevz_duplicate' );
				params.set( 'widget_name', $widget.find('.id_base').val() );
				params.set( 'widget_id', $widget.find('.widget_number').val() );
				params.set( 'widget_sidebar', $widget.closest('.widgets-sortables').attr( 'id' ) );

				var duplicateLink = $( '<a>', {
					href: '?' + params.toString(),
					text: 'Duplicate',
					class: 'codevz-duplicate-widget'
				});

				$widget.find('.widget-title h3').append(' | ').append(duplicateLink);

			}

		}

		// Add duplicate link to existing widgets
		$('#widgets-right .widget').each(function() {
			addDuplicateLinkToWidget($(this));
		});

		// Add duplicate link to dynamically added widgets
		//$(document).on('DOMNodeInserted', '#widgets-right', function() {
			//addDuplicateLinkToWidget($(this));
		//});

		// Custom sidebar generator.
		$( '.sidebars-column-2' ).append( '<div class="widgets-holder-wrap cz-custom-sidebar"><div class="sidebar-name"><h2>Add Custom Sidebar<span class="spinner"></span></h2></div><div class="cz_sidebar_cn"><input name="cz_sidebar_name" type="text" placeholder="Name ..."><input type="submit" name="cz_add_sidebar" class="button button-primary" value="Add"></div></div>' );
	
		$( '[name="cz_add_sidebar"]' ).on( 'click', function(e) {
			var en = $( this ), spinner = en.closest( '.widgets-holder-wrap' ).find( '.spinner' );

			if ( ! en.hasClass( 'cz_doing' ) ) {
				en.addClass( 'cz_doing' );
				spinner.addClass( 'is-active' );

				$.ajax({
				type: "GET",
				url: ajaxurl,
				dataType: 'html',
				data: 'action=codevz_custom_sidebars&add_sidebar=1&sidebar_name=' + $( '[name="cz_sidebar_name"]' ).val(),
				success: function( data ) {
					if ( data === 'done' ) {
					location.reload();
					} else {
					confirm( 'Something went wrong, Please try again.' );
					spinner.removeClass( 'is-active' );
					en.removeClass( 'cz_doing' );
					}
				},
				error: function( e ) {
					console.log( e );
					spinner.removeClass( 'is-active' );
					en.removeClass( 'cz_doing' );
				}
				});
			}

			e.preventDefault();
		});

		$( '#widgets-right [id*="cz-custom-"]' ).each( function() {
			var en = $( this );

			if ( ! en.find( '.cz-remove-sidebar' ).length ) {
				en.find( '.sidebar-name' ).append( '<a href="#" class="cz-remove-sidebar" title="Delete this sidebar"><div class="dashicons dashicons-no-alt"></div></a>' );
			}
		});

		$( '.cz-remove-sidebar' ).on( 'click', function(e) {
			var en = $( this ), spinner = en.closest( '.widgets-holder-wrap' ).find( '.spinner' );

			if ( ! en.hasClass( 'cz_doing' ) && confirm( 'Are you sure?' ) ) {
				en.addClass( 'cz_doing' );
				spinner.addClass( 'is-active' );

				$.ajax({
				type: "GET",
				url: ajaxurl,
				dataType: 'html',
				data: 'action=codevz_custom_sidebars&remove_sidebar=1&sidebar_name=' + en.closest( '.widgets-sortables' ).attr( 'id' ),
				success: function( data ) {
					if ( data === 'done' ) {
					location.reload();
					} else {
					confirm( 'Something went wrong, Please try again.' );
					spinner.removeClass( 'is-active' );
					en.removeClass( 'cz_doing' );
					}
				},
				error: function( e ) {
					console.log( e );
					spinner.removeClass( 'is-active' );
					en.removeClass( 'cz_doing' );
				}
				});
			}

			e.preventDefault();
		});
	}

	// Confirm duplicate posts/pages/tax/widget.
	body.on( 'click', '.duplicate > a, a.codevz-duplicate-widget', function( e ) {

		if ( ! confirm( 'Are you sure you want duplicate this item?' ) ) {
			e.preventDefault();
		}

		e.stopPropagation();

	});

	// VC Templates
	if ( $( '.vc_templates-template-type-default_templates' ).length ) {
		$( '.vc_templates-button, .vc_templatera_button, #vc_templates-more-layouts, #vc_templatera-more-layouts' ).on( 'click.cz_templates', function() {

		// Filters
		var filters = $( '.cz_vc_filters' ),
			dtt     = $( '[data-tab="default_templates"]' ),
			vupc    = $( '.vc_ui-panel-content-container' ),
			last_li = $( '[data-vc-ui-element-target="[data-tab=default_templates]"]' ).html( filters.data( 'tab-title' ) ).parent('li'),
			vutl    = $( '.vc_ui-template-list' ),
			time    = null;

		last_li.prependTo( last_li.parent() );
		last_li.find( 'button' ).trigger( 'click' );

		dtt.find( '.vc_col-md-12' ).addClass( 'vc_col-md-2' ).removeClass( 'vc_col-md-12' ).html( '' ).append( filters );
		dtt.find( '.vc_column' ).addClass( 'vc_col-md-10 cz_templates_items' ).removeClass( 'vc_col-md-12' );

		filters.removeClass( 'hidden' ).find( 'li' ).each(function() {
			var dis = $( this ),
				count = $( '.cz_templates_items .' + dis.data( 'filter' ) ).length;

			if ( count ) {
			dis.append( '<span>' + count + '</span>' );
			} else {
			dis.remove();
			}
			
			dis.on( 'click', function() {
			var cls = dis.data( 'filter' );

			dis.addClass( 'cz_active' ).siblings().removeClass( 'cz_active' );
			$( '.cz_templates_items .vc_ui-template-list .vc_ui-template' ).addClass( 'cz_deactive' );
			$( '.cz_templates_items .vc_ui-template-list .' + cls ).removeClass( 'cz_deactive' );

			vupc.trigger( 'resize' );

			setTimeout( function() {
				vupc.trigger( 'scroll' );
			}, 250 );

			});
		});

		// templates images
		vupc.off( 'scroll' ).on( 'scroll', function(e) {
			$( '.vc_templates-template-type-default_templates' ).each(function(i) {
				var dis = $( this );

				if ( ! dis.find( 'img' ).length ) {
					var cls = dis.attr( 'class' ),
						title = cls.match( /\d+_jpg/g );
					$( '.vc_ui-list-bar-item-trigger', dis ).html( '<img loading="lazy" src="https://xtratheme.com/img/templates/' + title[0].replace( /_/g, '.' ) + '" />' + cls );
				}

			});
		});

		$( '.vc_templates-template-type-default_templates' ).on( 'click', '.vc_ui-list-bar-item-trigger', function() {

			var dis = $( this );

			dis.addClass( 'cz_template_loader_after' ).find( 'img' ).css( 'opacity', '0.3' );

			setTimeout(function() {
				dis.removeClass( 'cz_template_loader_after' ).find( 'img' ).css( 'opacity', '1' );
			}, 4000 );

		});

		// Search tempaltes
		$( '.vc_ui-search-box-input' ).on( 'keyup', 'input', function() {

			clearTimeout( time );

			time = setTimeout( function() {
				vupc.trigger( 'resize' ).trigger( 'scroll' );
			}, 250 );

		});

		vupc.scroll();
		$( this ).off( 'click.cz_templates' );
		});

		// Front-end templates button
		$('#vc_inline-frame').on( 'load', function() {
		$('#vc_inline-frame').contents().find( '#vc_templates-more-layouts' ).on( 'click', function() {
			$( '.vc_templates-button' ).trigger( 'click' );
		});
		});
	}

	// Eye icon for vc preview
	if ( $( '.vc_navbar-frontend' ).length ) {
		$( '.vc_navbar-frontend .vc_navbar-nav' ).append('<li class="vc_pull-right cz_vc_preview"><a href="javascript:;" class="vc_icon-btn vc_post-settings" title="Quick Preview"><i class="vc-composer-icon fa fa-eye"></i></a></li>');
		$( '.cz_vc_preview' ).on( 'click', 'a', function(e) {

		$( 'i', this ).toggleClass( 'fa-eye fa-eye-slash' ).attr( 'disabled', 'disabled' );
		
		var i = $( '#vc_inline-frame' ).contents().find( 'body' );

		if ( $( 'i', this ).hasClass( 'fa-eye-slash' ) ) {
			i.append('<style id="cz_vc_preview_css">.compose-mode .vc_row:hover{z-index: auto !important}.vc_controls, .vc_welcome, .vc_empty-element, div .vc_empty:hover, .compose-mode .vc_element.vc_hold-hover>.wpb_row>.vc_element:before, .compose-mode .vc_element.vc_hover>.wpb_row>.vc_element:before, .compose-mode .vc_element:hover>.wpb_row>.vc_element:before, .view-mode .vc_element.vc_hold-hover>.wpb_row>.vc_element:before, .view-mode .vc_element.vc_hover>.wpb_row>.vc_element:before, .view-mode .vc_element:hover>.wpb_row>.vc_element:before {display: none !important} .compose-mode .vc_vc_row>.vc_parallax, .compose-mode .vc_vc_row>[data-vc-full-width=true], .compose-mode .vc_vc_row_inner, .compose-mode .vc_vc_section>.vc_parallax, .compose-mode .vc_vc_section>[data-vc-full-width=true], .compose-mode .vc_vc_video {padding-top: 0;padding-bottom: 0}@media (max-width:767px) {.compose-mode .vc_hidden-xs,.view-mode .vc_hidden-xs{display:none!important}}@media (min-width:768px) and (max-width:991px) {.compose-mode .vc_hidden-sm,.view-mode .vc_hidden-sm{display:none!important;}}@media (min-width:992px) and (max-width:1199px) {.compose-mode .vc_hidden-md,.view-mode .vc_hidden-md{display:none!important}}@media (min-width:1200px) {.compose-mode .vc_hidden-lg,.view-mode .vc_hidden-lg{display:none!important}}.compose-mode .page_content [id^=cz_]:hover,.compose-mode *{outline:none !important}.compose-mode .page_content .cz_gap{background:none}.compose-mode .vc_element .vc_element-container, .view-mode .vc_element .vc_element-container,.compose-mode .vc_empty-shortcode-element, .compose-mode .vc_templatera, .compose-mode .vc_vc_column_text, .compose-mode .vc_vc_widget_sidebar{min-height:unset}</style>');
		} else {
			$( '#cz_vc_preview_css', i ).remove();
		}

		if ( $( '.cz_btn_inline', i ).length ) {
			$( '.cz_btn_inline', i ).closest( '.vc_cz_button' ).toggleClass( 'cz_btn_inline' );
		}

		e.preventDefault();
		return false;
		});
	}

	// Slider Option
	$.fn.codevz_slider = function() {
		return this.each(function() {

		$( this ).html( '<div></div>' );

		var dis    = $( this ),
			input  = dis.prev('input'),
			slider = $( '> div', dis ),
			data   = dis.data( 'options' ),
			step   = parseInt( data.step ) || 1,
			min    = parseInt( data.min ) || 0,
			max    = parseInt( data.max ) || 100,
			unit   = '',
			val    = input.val();

		// Connect 4 fields to each other
		var nParent = input.closest( '.codevz-field-codevz_sizes' );
		if ( nParent.length ) {
			$( '.fa-link', nParent ).off().on( 'click', function() {
				dis.find( 'input' ).trigger( 'focus' );
				$( this ).toggleClass( 'cz_connect' );
				nParent.toggleClass( 'codevz-plus-inputs-connected' );
			});
		}

		slider.slider({
			range: 'min',
			value: parseInt( val || 0 ),
			step: step,
			min: min,
			max: max,
			slide: function( e, o ) {
				unit = input.val().replace(/[ 0-9]/g, '').replace('-', '');
				input.val( parseInt( o.value || 0 ) + ( unit || data.unit ) ).trigger( 'change' );
			}
		});

		// If border is custom
		if ( val ) {
			val = ( val.indexOf(' ') >= 0 ) ? 'addClass' : 'removeClass';
			dis.parent()[val]( 'cz_border_custom_val' );
		}

		// Focus input run mousewheel and keydown
		if ( ! input.closest( '.cz_border_custom_val' ).length ) {

			input.on( 'focus', function() {

				// Turn ON mousewheel and keydown
				input.off( 'mousewheel DOMMouseScroll keydown' ).on( "mousewheel DOMMouseScroll keydown", function( e ) {

					var unit = input.val().replace(/[ 0-9]/g, '').replace('-', ''),
						val  = parseInt( input.val() || 0 );

					// Keyboard Up
					if ( e.which == 38 ) {
						input.val( ( val + step ) + ( unit || data.unit ) ).trigger( 'change' );
						slider.slider({ value: val + step });
					} else if ( e.which == 40 ) {
						input.val( ( val - step ) + ( unit || data.unit ) ).trigger( 'change' );
						slider.slider({ value: val - step });
					}

					// Return false space on 4 fileds.
					if ( e.keyCode == 32 && ( dis.closest('.codevz-field-codevz_sizes').length || dis.closest('.codevz-field-codevz_box_shadow').length ) ) { 
						return false;
					}

					// Mousewheel Up/Down.
					if ( e.type === 'mousewheel' || e.type === 'DOMMouseScroll' ) {

						var deltaY = e.deltaY || e.originalEvent.wheelDelta || -e.originalEvent.detail;

						if ( typeof deltaY != 'undefined' ) {

							if ( deltaY > 0 ) {
								input.val( ( val + step ) + ( unit || data.unit ) ).trigger( 'change' );
								slider.slider({ value: val + step });
							} else {
								input.val( ( val - step ) + ( unit || data.unit ) ).trigger( 'change' );
								slider.slider({ value: val - step });
							}

							return false;
						}
					}

				}); // End mousewheel keydown
			}); // End focus

		} // End check closest( '.cz_border_custom_val' ).length

		input.on( 'keyup change', function() {

			val = input.val();

			slider.slider({ value: parseInt( val || '0' ) });

			// Connect all fields together
			if ( $( '.fa-link', nParent ).hasClass('cz_connect') ) {
				$( 'input', nParent ).val( val );
				$( '.codevz-slider > div', nParent ).slider({ value: parseInt( val ) });
			}

			// If border is custom
			dis.parent()[( val.indexOf(' ') >= 0 ? 'addClass' : 'removeClass' )]( 'cz_border_custom_val' );

		}).on( 'keyup', function() {

			slider.slider({ value: parseInt( input.val() || '0' ) });

		}).on( 'focusout', function() {

			// Turn OFF mousewheel and keydown
			input.off( 'mousewheel DOMMouseScroll keydown' );

			// Remove RTL and unwanted strings.
			val = input.val().replace( /[\u0660-\u0669\u06F0-\u06F9]/g, function(m) {
				return '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹'.indexOf( m ) % 10;
			}).replace( /[^0-9a-zA-Z.%\/\-\\()*\^#=+_]/g, '' );

			// Fix Unit
			if ( $.isNumeric( val ) ) {
				input.val( val + data.unit ).trigger( 'change' );
			} else if ( val == '-' ) {
				input.val( null ).trigger( 'change' );
			}

		}); // End input.on()

		}); // End return this
	}; // End codevz_slider

	// Image select for vc
	$.fn.codevz_image_select = function() {
		return this.each(function() {
		var dis = $( this );

		$( 'input', dis ).on( 'change', function() {
			if ( this.checked ) {
			var val = $( this ).val();
			$( 'input[type="hidden"]', dis ).val( val ).trigger( 'change' );
			}
		});
		});
	};

	// Customize WPB CSS_Editor
	$.fn.cz_vc_editor = function() {
		return this.each(function() {

			var dis = $( this ),
				brr = $( '[name="border_radius"]', dis ),
				brr_val = brr.val(),
				brr_val = ( brr_val == 'null' || ! brr_val ) ? '' : brr_val;

			// Change border radius field to input with slider
			brr.after( '<div class="codevz-field codevz-field-slider"><input type="text" name="border_radius" class="vc_border-radius cz_slider" value="' + brr_val + '"><div class="codevz-slider" data-options="{&quot;unit&quot;:&quot;px&quot;,&quot;step&quot;:1,&quot;min&quot;:0,&quot;max&quot;:120}"></div><div class="clear"></div></div>' );
			brr.remove();

			// Add background positions
			$( '[name="background_style"]', dis ).append( '<option value="cover;background-position:top">Cover, Top</option><option value="cover;background-position:bottom">Cover, Bottom</option><option value="no-repeat!important;background-position:left top">No repeat, Top left</option><option value="no-repeat !important;background-position: center top">No repeat, Top center</option><option value="no-repeat !important;background-position: right top">No repeat, Top right</option><option value="no-repeat !important;background-position: left bottom">No repeat, Bottom left</option><option value="no-repeat !important;background-position: center bottom">No repeat, Bottom center</option><option value="no-repeat !important;background-position: right bottom">No repeat, Bottom right</option>' );

		});
	};

	// Set shortcode unique ID
	$.fn.cz_live_shortcode_id = function() {
		return this.each(function() {
		var id = Math.floor(Math.random() * 99999) + 11111;
		$( this ).val( 'cz_' + id ).trigger( 'change' );
		});
	};

	// Right click for textarea.
	  const formats = [
	    { label: 'STRONG', open: '<strong>', close: '</strong>' },
	    { label: 'EM', open: '<em>', close: '</em>' },
	    { label: 'CODE', open: '<code>', close: '</code>' },
	    { label: 'A', open: '<a href="#" target="_blank">', close: '</a>' },
	    { label: 'COLOR', open: '<span style="color:red">', close: '</span>' },
	    { label: 'SIZE', open: '<span style="font-size:16px">', close: '</span>' },
	  ];

	  const $menu = $('<div class="codevz-plus-textarea-right-click" style="display:none"/>').appendTo('body');
	  formats.forEach(f => {
	    $('<button/>', { html: f.label, 'data-open': f.open, 'data-close': f.close }).appendTo($menu);
	  });

	  let $target = null, sel = { start: 0, end: 0 };

		$(document).on('contextmenu', 'textarea', function (e) {
		  const val = this.value;
		  const start = this.selectionStart;
		  const end = this.selectionEnd;
		  const selected = val.slice(start, end);

		  if (start === end) return; // nothing selected
		  if (selected.length >= 40 || selected.length === val.length) return; // skip if too long or all text

		  e.preventDefault();
		  $target = $(this);
		  sel = { start, end };
		  $menu.css({ top: e.pageY, left: e.pageX }).fadeIn(120);
		});

	  $(document).on('click', () => $menu.hide());

	  // Check if selection already wrapped by tags
	  function isWrapped(text, open, close) {
	    return text.startsWith(open) && text.endsWith(close);
	  }

	  $menu.on('click', 'button', function (e) {
	    e.stopPropagation();
	    const ta = $target[0];
	    const open = $(this).data('open'), close = $(this).data('close');
	    const val = $target.val();

	    let selectedText = val.slice(sel.start, sel.end);

	    if (isWrapped(selectedText, open, close)) {
	      // Remove the wrapping tags
	      const newText = selectedText.slice(open.length, selectedText.length - close.length);
	      // Replace selection with unwrapped text, update selection accordingly
	      ta.setSelectionRange(sel.start, sel.end);
	      ta.setRangeText(newText, sel.start, sel.end, 'end');
	      // Set new selection to cover unwrapped text
	      ta.setSelectionRange(sel.start, sel.start + newText.length);
	    } else {
	      // Add wrapping tags
	      const newText = open + selectedText + close;
	      ta.setSelectionRange(sel.start, sel.end);
	      ta.setRangeText(newText, sel.start, sel.end, 'end');
	      // Set selection covering newly wrapped text
	      ta.setSelectionRange(sel.start, sel.start + newText.length);
	    }

	    ta.focus();
	    $menu.hide();
	  });

	// Pro disable option in select.
	if ( typeof elementor != 'undefined' && elementor.hooks ) {

		var ss;

		elementor.hooks.addAction( 'panel/open_editor/widget', function( panel, model, view ) {

			clearTimeout( ss );

			ss = setInterval( function() {

				panel.$el.find( 'option:not([disabled])' ).each( function() {

					var $this = $( this );

					if ( $this.text().indexOf( '[' ) >= 0 ) {
						$this.attr( 'disabled', 'disabled' );
					}

				});

				panel.$el.find( '.elementor-control-type-image_select label' ).each( function( e ) {

					var $this = $( this );

					if ( ! $this.hasClass( 'xtra-image-select-pro' ) && $this.find( 'span' ).text().indexOf( '[' ) >= 0 ) {
						$this.addClass( 'xtra-image-select-pro' );
					}

				});

				panel.$el.find( '.xtra-image-select-pro' ).off().on( 'click', function( e ) {

					alert( sk_aiL10n.pro );

					e.preventDefault();

				});

			}, 1000 );

		});

	}

	// WPB copy all SK
	if ( ! $( '.xtra_copy_all_sk' ).length && ( window.location.href.indexOf( 'localXXX' ) >= 0 || window.location.href.indexOf( 'xtratheme.' ) >= 0 || window.location.href.indexOf( 'themetor.' ) >= 0 || window.location.href.indexOf( 'codevz.' ) >= 0 || window.location.href.indexOf( 'weebtheme.' ) >= 0 ) ) {

		var left = $( '.rtl, .site-rtl-user-ltr' ).length ? '72px' : '-62px';

		$( '.vc_ui-dropdown-trigger' ).before( '<div class="xtra_copy_all_sk" style="position:absolute;left:' + left + ';cursor:pointer;font-size: 20px; top: 3px;"><i class="fa fa-file"></i></div>' );

		body.on( 'click', '.xtra_copy_all_sk', function() {

			var sk = {},
				$this = $( this );

			$this.css( 'color', '#ffae00' );

			setTimeout( function() {
				$this.css( 'color', '#fff' );
			}, 1000 );

			$( '.vc_edit_form_elements [name]' ).not( '.vc_param_group-list [name]' ).each( function() {

				var $this = $( this ),
					name  = $this.attr( 'name' ),
					value = $this.val();

				if ( value && ( name.indexOf( 'sk_' ) >= 0 || name === 'svg_bg' ) ) {

					if ( name.indexOf( '_hover' ) < 0 ) {

						var hover = $( '.vc_edit_form_elements input[name="' + $this.attr( 'data-hover_id' ) + '"]' ).val() || '',
							rtl   = '';

						// Get RTL mode.
						if ( value.indexOf( 'RTL' ) >= 0 ) {

							rtl = value.split( 'RTL' )[1].replace( 'RTL', '' );
							value = value.split( 'RTL' )[0];

						}

						var svg = value.split( ';' );

						if ( value.indexOf( '_class' ) >= 0 ) {

							value = '';

							$.each( svg, function( k, v ) {

								if ( v.indexOf( '_' ) != 0 ) {
									value += v + ';';
								}

							});

							value = value.replace( ';;', ';' );

						}

						if ( name === 'sk_css' && $this.closest( '[data-vc-shortcode="cz_image"]' ).length ) {
							name = 'sk_image_in';
						}

						sk[ name ] = {

							'normal': value.replace( /CDVZ/g, '' ) || '0',
							'hover': hover.replace( /CDVZ/g, '' ) || '0',
							'rtl': rtl || '0'

						};

					}

				} else if ( value ) {

					if ( $this.attr( 'type' ) === 'checkbox' ) {

						var value = $this.is( ':checked' );

						if ( value ) {

							value = 'yes';

							if ( name === 'hide_on_d' ) {

								sk[ 'hide_desktop' ] = value;

							} else if ( name === 'hide_on_t' ) {

								sk[ 'hide_tablet' ] = value;

							} else if ( name === 'hide_on_m' ) {

								sk[ 'hide_mobile' ] = value;

							} else {

								sk[ name ] = value;

							}

						}

					} else if ( $this.hasClass( 'cz_slider_field' ) && name.indexOf( 'parallax' ) < 0 && value != '0' ) {

						var newValue = value.match( /\d+.\d+|\d+|\w+|\%/g );

						if ( newValue ) {

							if ( ! newValue[1] ) {

								sk[ name ] = value;

							} else if ( newValue[0] ) {

								if ( $this.closest( '[data-vc-shortcode="cz_gap"]' ).length ) {
									if ( newValue[0] >= 40 ) {
										newValue[0] = newValue[0] - 40;
									} else {
										newValue[0] = 0;
									}
								}

								sk[ name ] = {
									'size': newValue[0],
									'unit': newValue[1] || ''
								};

							}

						}

					} else {

						if ( value.indexOf( 'url:' ) >= 0 ) {

							var url = value.replace( 'url:', '' ).split( '|' );

							if ( url[ 0 ] ) {

								url = decodeURIComponent( url[ 0 ] );
								url = url.replace( 'xtratheme.com/', 'xtratheme.com/elementor/' ).replace( 'themetor.com/', 'themetor.com/elementor/' ).replace( 'codevz.com/', 'codevz.com/elementor/' ).replace( 'weebtheme.com/', 'weebtheme.com/elementor/' );
								url = url ? url : '#';

								sk[ name ] = {

									'url': url,
									'is_external': '',
									'nofollow': '',
									'custom_attributes': ''

								};

							}

						} else if ( name === 'parallax_h' && value != '0' ) {

							sk[ 'parallax' ] = value;

						} else if ( name === 'parallax' && value != '0' ) {

							sk[ 'parallax_speed' ] = value;

						} else if ( name === 'mparallax' && value != '0' ) {

							sk[ 'mouse_speed' ] = value;

						} else if ( name === 'class' ) {

							sk[ '_css_classes' ] = value;

						} else if ( $this.hasClass( 'param_group_field' ) && value.indexOf( '%' ) >= 0 ) {

							var items = JSON.parse( decodeURIComponent( value ) );

							$.each( items, function( k, v ) {

								if ( items[ k ][ 'icon' ] ) {
									value = items[ k ][ 'icon' ];
								} else if ( items[ k ][ 'i' ] ) {
									value = items[ k ][ 'i' ];
								} else {
									value = false;
								}
	 
								if ( value ) {
									var library = 'fa-solid';

									if ( value.indexOf( 'czico' ) >= 0 ) {
										library = 'xtra-custom-icons';
									} else if ( value.indexOf( 'far ' ) >= 0 ) {
										library = 'fa-regular';
									} else if ( value.indexOf( 'fab ' ) >= 0 ) {
										library = 'fa-brands';
									} else if ( value.indexOf( 'twitter' ) >= 0 || value.indexOf( 'facebook' ) >= 0 || value.indexOf( 'apple' ) >= 0 || value.indexOf( 'android' ) >= 0 || value.indexOf( 'pinterest' ) >= 0 || value.indexOf( 'youtube' ) >= 0 || value.indexOf( 'skype' ) >= 0 || value.indexOf( 'dribbble' ) >= 0 || value.indexOf( '500px' ) >= 0 || value.indexOf( 'flickr' ) >= 0 || value.indexOf( 'advisor' ) >= 0 || value.indexOf( 'behance' ) >= 0 || value.indexOf( 'telegram' ) >= 0 || value.indexOf( 'whatsapp' ) >= 0 || value.indexOf( 'reddit' ) >= 0 || value.indexOf( 'instagram' ) >= 0 || value.indexOf( 'wordpress' ) >= 0 || value.indexOf( 'clubhouse' ) >= 0 || value.indexOf( 'linkedin' ) >= 0 || value.indexOf( 'facebook' ) >= 0 ) {
										library = 'fa-brands';
									}

									if ( items[ k ][ 'icon' ] ) {
										items[ k ][ 'icon' ] = { value: value, library: library };
									} else if ( items[ k ][ 'i' ] ) {
										items[ k ][ 'i' ] = { value: value, library: library };
									}
								}

							});

							sk[ name ] = items;

						} else if ( $this.hasClass( 'cz_icon_field' ) ) {

							var library = 'fa-solid';

							if ( value.indexOf( 'czico' ) >= 0 ) {
								library = 'xtra-custom-icons';
							} else if ( value.indexOf( 'far ' ) >= 0 ) {
								library = 'fa-regular';
							} else if ( value.indexOf( 'fab ' ) >= 0 ) {
								library = 'fa-brands';
							} else if ( value.indexOf( 'twitter' ) >= 0 || value.indexOf( 'facebook' ) >= 0 || value.indexOf( 'apple' ) >= 0 || value.indexOf( 'android' ) >= 0 || value.indexOf( 'pinterest' ) >= 0 || value.indexOf( 'youtube' ) >= 0 || value.indexOf( 'skype' ) >= 0 || value.indexOf( 'dribbble' ) >= 0 || value.indexOf( '500px' ) >= 0 || value.indexOf( 'flickr' ) >= 0 || value.indexOf( 'advisor' ) >= 0 || value.indexOf( 'behance' ) >= 0 || value.indexOf( 'telegram' ) >= 0 || value.indexOf( 'whatsapp' ) >= 0 || value.indexOf( 'reddit' ) >= 0 || value.indexOf( 'instagram' ) >= 0 || value.indexOf( 'wordpress' ) >= 0 || value.indexOf( 'clubhouse' ) >= 0 || value.indexOf( 'linkedin' ) >= 0 || value.indexOf( 'facebook' ) >= 0 ) {
								library = 'fa-brands';
							}

							sk[ name ] = { value: value, library: library };

						} else if ( name.indexOf( 'parallax' ) < 0 && ! $this.hasClass( 'codevz-icon-value' ) && ! $this.hasClass( 'cz_slider' ) && name !== 'cursor' && name !== 'id' && name !== 'wpb_tinymce_content' && name !== 'items' && name !== 'link' && name !== 'link_type' && name !== 'images' && name !== 'image' && name !== 'size' && name !== 'anim_delay' && name !== 'css_animation' ) {

							sk[ name ] = value;

						}

					}

				}

			});

			codevzClipboard( 'copy', sk, ( data ) => {
				console.log( 'Copy all WPBakery: ', data );
			});

		});

		if ( typeof elementor != 'undefined' && elementor.hooks ) {

			// Search.
			//$( '#tmpl-elementor-panel-element-search' ).append( '<a>title</a><a>button</a><a>gap</a><a>list</a><a>image</a>' );

			body.on( 'click', '#elementor-panel-elements-search-wrapper a', function() {

				var val = $( this ).text();

				$( '#elementor-panel-elements-search-input' ).val( val );

				$( '.elementor-element-wrapper' ).each( function() {

					var $elem = $( this ).css( 'margin-bottom', '0' ),
							search = new RegExp( val, 'i' );

					if ( search && $elem.find('.title').html().search( search ) < 0 ) {
						$elem.hide();
					} else {
						$elem.show().css( 'margin-bottom', '10px' );
					}

					$( '.elementor-panel-category-title' ).hide();

				});

			}).append( '<style>#elementor-panel-elements-search-area a{display:inline-block;cursor:pointer;padding:5px 10px;background:#f7f7f7;margin:10px 2px 0 0}</style>' );

			// Paste.
			elementor.hooks.addAction( 'panel/open_editor/widget', function( panel, model, view ) {

				$( document ).off( 'xtra_elementor' ).on( 'xtra_elementor', function( event, data ) {

					if ( data ) {

						console.log( data );
						//data = JSON.parse( data );

						$.each( data, function( key, value ) {

							if ( key.indexOf( 'sk_' ) >= 0 ) {

								value = value || { normal: '0', hover: '0', rtl: '0' };

							}

							model.setSetting( key, value );

						});

						// Live preivew fix.
						setTimeout( function() {

							$( '.elementor-tab-control-advanced' ).trigger( 'click' );

							setTimeout( function() {
								$( '[data-setting="top"]' ).trigger( 'input' );
							}, 250 );

							setTimeout( function() {
								$( '.elementor-tab-control-content' ).trigger( 'click' );
							}, 500 );

							//$e.routes.refreshContainer( 'panel' );

						}, 500 );

					}

				});

			});

			elementor.hooks.addFilter( 'elements/widget/contextMenuGroups', function( groups, element ) {

				groups.push(
					{
						name: 'xtra',
						actions: [
							{
								name: 'xtra_sk_paste',
								title: 'Paste from WPBakery',
								callback: function() {

									codevzClipboard( 'paste', null, ( data ) => {

										console.log( 'Paste from WPBakery: ', data );

										$( document ).trigger( 'xtra_elementor', [ data ] );

									});

								}
							}
						]
					}
				);

				return groups;

			} );

		}

	}

	if ( typeof elementor != 'undefined' && elementor.hooks ) {

		elementor.hooks.addAction( 'panel/open_editor/widget', function( panel, model, view ) {

			// Fix white text in editor.
			setTimeout( function() {

				$( '.mce-edit-area > iframe' ).contents().find( 'body' ).css({
					'background': 'rgba(167, 167, 167, 0.25)'
				});

			}, 1500 );

		});

	}

	if ( $( '.wp-customizer' ).length ) {

		// Mobile header customize change iframe
		$( '#accordion-section-codevz_theme_options-mobile_header, #accordion-section-codevz_theme_options-mobile_fixed_navigation' ).on( 'click', function() {
			$( '[data-device="mobile"]' ).trigger( 'click' );
			$( '.customize-section-back' ).on( 'click.cz', function() {
			$( '[data-device="desktop"]' ).trigger( 'click' );
			$( this ).off( 'click.cz' );
			});
		});

		function checkCustomTemplateOptions( option ) {

			var active 		= 'codevz-section-disabled',
					title 		= option === 'header_elementor' ? sk_aiL10n.custom_header : sk_aiL10n.custom_footer,
					selector 	= $( '[name="codevz_theme_options[' + option + ']"]' ),
					sections 	= option === 'header_elementor' ? $( '#accordion-section-codevz_theme_options-header_logo, #accordion-section-codevz_theme_options-header_social, #accordion-section-codevz_theme_options-header_5, #accordion-section-codevz_theme_options-fixed_side_1, #accordion-section-codevz_theme_options-header_1, #accordion-section-codevz_theme_options-header_2, #accordion-section-codevz_theme_options-header_3, #accordion-section-codevz_theme_options-mobile_header' ) : $( '#accordion-section-codevz_theme_options-footer_1, #accordion-section-codevz_theme_options-footer_2, #accordion-section-codevz_theme_options-footer_widgets' ); 

			if ( selector.val() ) {
				sections.addClass( active ).find( 'h3' ).append( '<i>' + title + '</i>' );
			} else {
				sections.removeClass( active ).find( 'h3 i' ).remove();
			}

			selector.on( 'change', function( e ) {

				if ( $( this ).val() ) {

					sections.addClass( active ).find( 'h3' ).append( '<i>' + title + '</i>' );

				} else {

					sections.removeClass( active ).find( 'h3 i' ).remove();

				}

			});

		}

		checkCustomTemplateOptions( 'header_elementor' );
		checkCustomTemplateOptions( 'footer_elementor' );

	}

	// Style kit switch with responsive device mode.
	body.on( 'click.cz', '.devices-wrapper button, #e-responsive-bar-switcher input, #vc_screen-size-control ul a, .MuiTabs-flexContainer > button', function() {

		if ( $( '[aria-describedby="cz_modal_kit"]' ).css( 'display' ) == 'none' ) {
			return;
		}

		var $this = $( this ),
			device = $this.data( 'device-mode' ) || $this.data( 'device' ) || $this.val(),
			modal = $( '[aria-describedby="cz_modal_kit"]' );

		if ( ! device ) {

			if ( $this.hasClass( 'vc-c-icon-layout_landscape-tablets' ) || $this.hasClass( 'vc-c-icon-layout_portrait-tablets' ) || $this.is( 'button:nth-child(2)' ) ) {
				device = 'tablet';
			} else if ( $this.hasClass( 'vc-c-icon-layout_landscape-smartphones' ) || $this.hasClass( 'vc-c-icon-layout_portrait-smartphones' ) || $this.is( 'button:nth-child(3)' ) ) {
				device = 'mobile';
			}

		}

		if ( device === 'tablet' ) {
			modal.find( '.fa-tablet-alt' ).trigger( 'click' );
		} else if ( device === 'mobile' ) {
			modal.find( '.fa-mobile-alt' ).trigger( 'click' );
		} else {
			modal.find( '.fa-desktop' ).trigger( 'click' );
		}

	});

	var kit_modal = $( '#cz_modal_kit' ), rgba_reset, reopen_modal;

	// Funcs
	$.fn.codevzSerializeObject=function(){var e={};return $.each(this.serializeArray(),function(a,r){var n=r.name.match(/(.*?)\[(.*?)\]/);if(null!==n){var u=new String(n[1]),i=new String(n[2]);e[u]||(e[u]={}),e[u][i]?$.isArray(e[u][i])?e[u][i].push(r.value):(e[u][i]={},e[u][i].push(r.value)):e[u][i]=r.value}else{var l=new String(r.name);e[l]?$.isArray(e[l])?e[l].push(r.value):(e[l]={},e[l].push(r.value)):e[l]=r.value}}),e};

	function cssToObj(css) {
		if ( !css ) {return;}

		css = css.replace( /CDVZ/g, '' );

		var i, ii, p, v, ob = {}, obj = {},
		rtl    = css.match( "RTL(.*)RTL" ),
		css    = css.indexOf( 'RTL' ) >= 0 ? css.substring( 0, css.indexOf( 'RTL' ) ) : css,
		s      = css.split(/:(?![^(]*\))(?![^"']*["'](?:[^"']*["'][^"']*["'])*[^"']*$)|;/g),
		bg_index = [ 'orientation', 'color', 'color2', 'color3' ],
		orientations = [ 'top', 'right', 'bottom', 'left' ];

		// Prepare inners
		obj['custom'] = '';
		obj['margin'] = {};
		obj['padding'] = {};
		obj['background'] = {};
		obj['box-shadow'] = {};
		obj['text-shadow'] = {};
		obj['border-width'] = {};
		if ( rtl ) {
		obj['rtl'] = rtl[1];
		}
		
		for ( i = 0; i < s.length; i += 2 ) {
		p = s[i], v = s[i+1], ob = {};

		if ( ! p || ! v ) {continue;}

		if ( p === 'background-color' ) {
			obj['background']['color'] = v;
		} else if ( p === 'background-repeat' ) {
			obj['background']['repeat'] = v;
		} else if ( p === 'background-position' ) {
			obj['background']['position'] = v;
		} else if ( p === 'background-attachment' ) {
			obj['background']['attachment'] = v;
		} else if ( p === 'background-size' ) {
			obj['background']['size'] = v;
		} else if ( p === 'background-image' ) {
			if ( v.indexOf( 'url(' ) >= 0 && v.indexOf( ')' ) >= 0 ) {
			obj['background']['image'] = v.split( 'url(' )[1].split( ')' )[0];
			if ( v.indexOf( ',linear' ) >= 0 ) {
				obj['background']['layer'] = '1';
			}
			}
			if ( v.indexOf( 'gradient' ) >= 0 ) {
			var lg = v.match( /linear-gradient\((.*)\)/ );
			v = lg[1].match( /rgba\(.*?\)|#\w+|\w+\s\w+|\w+|-\w+/g );
			if ( v[1] == v[2] ) {
				v[2] = '';
			}
			$.each( v, function( bp, bv ) {
				obj['background'][ bg_index[bp] ] = ( ( bv == 'url' ) ? '' : bv );
			});
			}
		} else if ( p === 'padding' || p === 'margin' || p === 'border-width' ) {
			ob = v.match(/\S+/g);
			if ( ob.length == 4 ) {
			obj[p]['top'] = ob[0];
			obj[p]['right'] = ob[1];
			obj[p]['bottom'] = ob[2];
			obj[p]['left'] = ob[3];
			} else if ( ob.length == 3 ) {
			obj[p]['top'] = ob[0];
			obj[p]['right'] = ob[1];
			obj[p]['bottom'] = ob[2];
			obj[p]['left'] = ob[1];
			} else if ( ob.length == 2 ) {
			obj[p]['top'] = ob[0];
			obj[p]['right'] = ob[1];
			obj[p]['bottom'] = ob[0];
			obj[p]['left'] = ob[1];
			} else if ( ob.length == 1 ) {
			obj[p]['top'] = ob[0];
			obj[p]['right'] = ob[0];
			obj[p]['bottom'] = ob[0];
			obj[p]['left'] = ob[0];
			}

		} else if ( p === 'padding-top' ) {
			obj['padding']['top'] = v;

		} else if ( p === 'padding-right' ) {
			obj['padding']['right'] = v;

		} else if ( p === 'padding-bottom' ) {
			obj['padding']['bottom'] = v;

		} else if ( p === 'padding-left' ) {
			obj['padding']['left'] = v;

		} else if ( p === 'margin-top' ) {
			obj['margin']['top'] = v;

		} else if ( p === 'margin-right' ) {
			obj['margin']['right'] = v;

		} else if ( p === 'margin-bottom' ) {
			obj['margin']['bottom'] = v;

		} else if ( p === 'margin-left' ) {
			obj['margin']['left'] = v;

		} else if ( p === 'border-top-width' ) {
			obj['border-width']['top'] = v;
			obj['border-top-width'] = v;

		} else if ( p === 'border-right-width' ) {
			obj['border-width']['right'] = v;
			obj['border-right-width'] = v;

		} else if ( p === 'border-bottom-width' ) {
			obj['border-width']['bottom'] = v;
			obj['border-bottom-width'] = v;

		} else if ( p === 'border-left-width' ) {
			obj['border-width']['left'] = v;
			obj['border-left-width'] = v;

		} else if ( p.indexOf( 'shadow' ) >= 0 ) {

			v = v.match( /rgba\(.*?\)|#\w+|-\w+|\w+/g );
			obj[p]['x'] = v[0];
			obj[p]['y'] = v[1];
			obj[p]['blur'] = v[2];

			if ( typeof v[3] === 'string' && v[3].indexOf( 'px' ) >= 0 ) {

				obj[p]['color'] = v[4];

				if ( p.indexOf( 'box-shadow' ) >= 0 ) {
						obj[p]['spread'] = v[3];
					if ( typeof v[5] === 'string' ) {
						obj[p]['inset'] = v[5];
					}
				}

			} else {
				obj[p]['color'] = v[3];
			}

		} else if ( p == 'transform' && v && v.match( /^rotate\(-?\d+(\.\d+)?deg\)$/ ) ) {
			v = v.match( /-?\d+(?:\.\d+)?[a-z%]+/gi );
			obj[p] = v[ v.length - 1 ];

		} else if ( p == 'filter' && ( v.indexOf( 'blur' ) >= 0 || v.indexOf( 'grayscale' ) ) >= 0 ) {
			p = v.indexOf( 'blur' ) >= 0 ? 'blur' : 'grayscale';
			v = v.match( /(\d+)%|(\d+)px/g );
			obj[p] = v[0];

		} else if ( p == 'content' || p == 'font-family' ) {
			obj[p] = v.replace( /"|\\|'/g, '' );

		} else if ( p.indexOf( 'border' ) >= 0 && p.indexOf( 'style' ) >= 0 ) {
			obj['border-style'] = v;

		} else if ( kit_modal.find( '[name="' + p + '"]' ).length && p != 'transform' ) {
			obj[p] = v;

		} else {
			obj['custom'] += p + ': ' + v + ';\n';
		}
		}

		return obj;
	}

	function objToCSS(obj) {
		if ( ! obj ) {return;}

		var css = '', bg_layer;
		$.each( obj, function( property, val ) {
		if ( val ) {
			if ( typeof val == 'object' ) {
			if ( property == 'background' ) {
				$.each( val, function( bp, bv ) {
				if ( bp == 'position' || bp == 'size' || bp == 'repeat' || bp == 'attachment' ) {
					css += 'background-' + bp + ':' + bv + ';';
				} else if ( bp == 'layer' && bv ) {
					bg_layer = bv;
				}
				});

				val = $.extend({image: '',color: '',color2: '',color3: '',orientation: ''}, val);

				var image = val['image'] ? 'url(' + val['image'] + ')' : '',
				c1 = val['color'],
				c2 = val['color2'],
				c3 = val['color3'],
				is_color = ( c1 || c2 || c3 ),
				empty_cl = ( ! c1.length + ! c2.length + ! c3.length ),
				linear = val['orientation'] ? val['orientation'] : '0deg',
				linear = linear + ( c1 ? ',' + c1 + ( ( ! c2 && ! c3 ) ? ',' + c1 : '' ) : '' ),
				linear = linear + ( c2 ? ',' + c2 + ( ( ! c1 && ! c3 ) ? ',' + c2 : '' ) : '' ),
				linear = linear + ( c3 ? ',' + c3 + ( ( ! c1 && ! c2 ) ? ',' + c3 : '' ) : '' ),
				linear_gr = 'linear-gradient(' + linear + ')';

				if ( empty_cl <= 1 ) {
				css += 'background-color:transparent;';
				}

				if ( is_color && image ) {
				css += 'background-image:' + ( bg_layer ? image + ',' + linear_gr : linear_gr + ',' + image ) + ';';
				} else if ( image ) {
				css += 'background-image:' + image + ';';
				} else if ( empty_cl == 2 ) {
				css += 'background-color:' + c1 + c2 + c3 + ';';
				} else if ( linear && is_color ) {
				css += 'background-image:' + linear_gr + ';';
				}

			} else if ( property == 'margin' || property == 'padding' || property == 'border-width' ) {

				if ( Object.keys( val ).length == 4 ) {
				if ( val['top'] === val['right'] && val['bottom'] === val['left'] && val['top'] === val['left'] ) {
					css += property + ':' + val['top'] + ';';
				} else if ( val['top'] !== val['bottom'] && val['right'] === val['left'] ) {
					css += property + ':' + val['top'] + ' ' + val['right'] + ' ' + val['bottom'] + ';';
				} else if ( val['top'] === val['bottom'] && val['right'] === val['left'] ) {
					css += property + ':' + val['top'] + ' ' + val['right'] + ';';
				} else {
					css += property + ':' + val['top'] + ' ' + val['right'] + ' ' + val['bottom'] + ' ' + val['left'] + ';';
				}
				} else {
				$.each( val, function( obp, obv ) {
					var width = ( property == 'border-width' ) ? '-width' : '',
						new_pro = ( property == 'border-width' ) ? 'border' : property;
					css += new_pro + '-' + obp + width + ':' + obv + ';';
				});
				}

			} else if ( property.indexOf( 'shadow' ) >= 0 ) {

				if ( typeof val === 'object' && ( typeof val['x'] === 'string' || typeof val['y'] === 'string' ) ) {

					val = jQuery.extend( {}, {
						x:      '0px',
						y:      '0px',
						blur:   '0px',
						spread: '0px',
						color:  '#000'
					}, val );

					css += property + ':';

					$.each( val, function( k, v ) {

						if ( ( k === 'spread' || k === 'inset' ) && property.indexOf( 'text-shadow' ) >= 0 ) {
							v = null;
						}

						if ( v ) {
							css += ( k === 'x' ) ? v : ' ' + v;
						}
					});

					if ( typeof val['inset'] === 'string' ) {
						css += ' inset';
					}

					css += ';';

				}

			}

			} else if ( property == 'rtl' ) {
			css += 'RTL' + val + 'RTL';

			} else if ( property == 'content' ) {
			css += 'content:"' + val.replace( /"|:|;/g, '' ) + '";';

			} else if ( property == 'font-family' ) {

				if ( $( '.elementor-panel' ).length ) {
					val = val.split( '=' );
					val = val[0];
				}

				val.replace( '\\', '' );

				css += "font-family:'" + val + "';";

			} else if ( property == 'transform' && val.endsWith( 'deg' ) ) {
				css += 'transform:rotate(' + val + ');';

			} else if ( property == 'blur' ) {
				css += 'filter:blur(' + val + ');';

			} else if ( property == 'grayscale' ) {
				css += 'filter:grayscale(' + val + ');';

			} else if ( val && property == 'custom' ) {

				css += val.toString().replace(/\s*\n\s*/g, '').replace(/.*\{(.*)\}/, '$1').replace(/\s*:\s*/g, ':');

			} else if ( property != 'live_id' && property != 'selector' ) {
				css += property + ':' + val + ';';

			}
		}
		});

		return css;
	}

	// Fix font - icon select in customizer
	$( '.wp-customizer' ).off( 'click.cz' ).on( 'click.cz', '.codevz-font-add, .codevz-icon-add', function(e) {
		if ( kit_modal.hasClass( 'ui-dialog-content' ) ) {
		var issk = $( this ).closest( '#cz_modal_kit' ).length;
		kit_modal.dialog( 'close' );
		setTimeout( function() {
			$( '#codevz-modal-font, #codevz-modal-icon' ).off( 'click.cz2' ).on( 'click.cz2', '.codevz-font-close, .codevz-icon-close, a', function(e) {
			if ( e.target.className.indexOf( 'fa' ) < 0 && kit_modal.hasClass( 'ui-dialog-content' ) && issk ) {
				kit_modal.dialog( 'open' );
			}
			});
		}, 500 );
		}
	});

	// Reload scripts
	$(document).on('codevz-reload-script', function( event, $this, api ) {

		$this.find('.vc_css-editor').not('.codevz-no-script').cz_vc_editor();
		$this.find('.codevz-slider').not('.codevz-no-script').codevz_slider();
		$this.find('[data-param_type="cz_image_select"]').not('.codevz-no-script').codevz_image_select();
		$this.find('.cz_sc_id_field').not('.codevz-no-script').cz_live_shortcode_id();

		// Select pages edit content button.
		$this.on( 'change', '.codevz-select-page select', function() {

			var con = $( this ).parent(),
					val = con.find( 'select' ).val(),
					a 	= con.find( '> a[data-href]' );

			if ( val && val !== 'link' && val !== 'simple' ) {
				a.show().attr( 'href', a.attr( 'data-href' ) + '?codevz_edit_content=' + val );
			} else {
				a.hide();
			}

		});

		// Block reveal dependency
		$this.find( '.vc_param-animation-style, .animation' ).on( 'change', function() {
		var val = $( this ).val();

		if ( val.indexOf( 'cz_brfx_' ) >= 0 || val.indexOf( 'cz_grid_brfx_' ) >= 0 ) {
			$this.find( '[data-vc-shortcode-param-name="sk_brfx"]' ).removeClass( 'vc_dependent-hidden hidden' );
		} else {
			$this.find( '[data-vc-shortcode-param-name="sk_brfx"]' ).addClass( 'vc_dependent-hidden hidden' );
		}
		}).trigger( 'change' );

		// Scroll menu styling btn to menu sk
		$( '.cz_menu_sk' ).each(function() {
		$( this ).on( 'click', function(e) {
			if ( $( '.wp-full-overlay-sidebar-content' ).length ) {
			$( '.wp-full-overlay-sidebar-content' ).animate({ scrollTop: $( this.hash ).position().top - 50 });
			}
			e.preventDefault();
		});
		});

		// Image dropdown param
		var iSelect = $( '.codevz_image_select' );
		iSelect.find( '> div' ).off().on( 'click', function(e) {
			var en = $( this ), 
				id = en.attr( 'data-id' ),
				ul = en.next( 'ul' );

			iSelect.find( 'ul' ).not( ul ).hide();
			en.find( 'i' ).toggleClass( 'fa-angle-down fa-angle-up' );

			ul.find( 'li[data-id="' + id + '"]' ).addClass( 'codevz_on' ).siblings().removeClass( 'codevz_on' );

			ul.toggle().find( 'li' ).off().on( 'click', function() {
				var li = $( this ),
						new_id = li.attr( 'data-id' ),
						title = li.attr( 'data-title' );

				if ( title.indexOf( '[' ) >= 0 ) {

					alert( sk_aiL10n.pro );

					return false;

				}

				li.addClass( 'codevz_on' ).siblings().removeClass( 'codevz_on' );
				en.parent().find( 'input' ).val( new_id ).trigger( 'change' );
				en.attr( 'data-id', new_id ).find( '> img' ).attr( 'src', li.find( 'img' ).attr( 'src' ) );
				en.find( 'span span' ).html( li.attr( 'data-title' ) );
				en.next( 'ul' ).hide();
				en.find( 'i' ).attr( 'class', 'fa fa-angle-down' );
			});
		});
		body.on( 'click', function(e) {
			if ( ! $( e.target ).is( '.codevz_image_select *' ) ) {
				iSelect.find( 'ul' ).hide();
				iSelect.find( '> div i' ).attr( 'class', 'fa fa-angle-down' );
			}
		});

		// Advanced Typo.
		$( '.cz_advance_typo' ).off().on( 'click', function(e) {
			$( this ).hide();
			kit_modal.find( '.codevz-plus-advanced-fields' ).show();
			e.preventDefault();
		});

		// Advanced Background
		$( '.cz_advance_bg' ).off().on( 'click', function(e) {
			$( this ).hide();
			kit_modal.find( '.cz_bg_advanced' ).show();
			e.preventDefault();
		});

		// Close StyleKit on VC modal changes
		$( '.vc_ui-close-button, .vc_edit-form-tab-control, .vc_ui-panel-footer span, .vc_control-btn-layout, .vc_control-btn-edit, .custom_columns, .vc-c-icon-mode_edit, .vc_ui-minimize-button' ).off().on( 'click', function() {
		if ( $( this ).data( 'vc-ui-element' ) == 'button-save' && $( '.compose-mode' ).length ) {
			return;
		}
		if ( kit_modal.hasClass( 'ui-dialog-content' ) ) {
			kit_modal.dialog( 'close' );
		}
		});

		// In the vc front-end
		$( '#vc_inline-frame' ).contents().find( ".vc_ui-close-button, .vc_ui-panel-footer span, .vc_control-btn-layout, .vc_control-btn-edit, .vc_post-settings, .custom_columns, .vc-c-icon-mode_edit, .vc_ui-minimize-button" ).off().on( 'click', function() {
		if ( kit_modal.hasClass( 'ui-dialog-content' ) ) {
			kit_modal.dialog( 'close' );
		}
		});

		// Advanced Show all _css_
		$( '.cz_advanced_tab span' ).off().on( 'click', function() {
		if ( ! $( this ).hasClass( 'cz_active' ) ) {
			$( this ).addClass( 'cz_active' ).siblings().removeClass( 'cz_active' ).closest( 'ul' ).toggleClass( 'cz_all_css_on' );
		}
		});

		// StyleKit value detection
		var sk_value_detection = function( sk ) {

		if ( $( '.wp-customizer' ).length ) {
			return;
		}

		var sk = sk ? sk : $( '.vc_ui-panel-window-inner .cz_sk_btn' );

		sk.each( function() {
			var en = $( this ),
				val = en.prev( '[name]' ).val(),
				out = '';

			en.parent().find( '> span' ).remove();

			if ( val && ! en.next( 'span' ).length ) {
			if ( val.indexOf( 'background' ) >= 0 ) {
				out += 'background';
			}
			if ( val.indexOf( 'font-size' ) >= 0 ) {
				out += out ? ', ' : '';
				out += 'font-size';
			}
			if ( val.indexOf( 'color' ) == 0 || val.indexOf( ';color' ) > 0 ) {
				out += out ? ', ' : '';
				out += 'color';
			}
			if ( val.indexOf( 'border-color' ) >= 0 ) {
				out += out ? ', ' : '';
				out += 'border';
			}

			if ( out && ! api ) {
				en.parent().find( '.sk_btn_preview_image' ).after( '<span class="cz_vdt">[ ' + out + ' ]</span>' );
			}
			}
		});
		};
		sk_value_detection();

		// Preview image click
		$( '.sk_btn_preview_image' ).off().on( 'click', function(e) {

			$( this ).parent().find( '.cz_sk_btn' ).trigger( 'click' );

			e.preventDefault();

		});

		$( '.codevz-field-cz_sk_free .button.cz_sk_btn, .codevz-field-checkbox label[disabled], .xtra-image-select-pro' ).off().on( 'click', function( e ) {

			alert( sk_aiL10n.pro );

			e.preventDefault();

		});

		$( 'option:not([disabled])' ).each( function() {

			var $this = $( this );

			if ( $this.text().indexOf( '[' ) >= 0 ) {
				$this.attr( 'disabled', 'disabled' );
			}

		});

		// StyleKit Button and data
		$this.find( '.cz_sk_btn' ).not( '.cz_sk_free_btn' ).each( function() {

		var en = $( this ),
			input = en.parent().find( 'input' ),
			name  = input.attr( 'name' ),
			id    = name.match( /\w+/g ),
			id    = id[ id.length - 1 ],
			hover = input.attr( 'data-hover_id' ),
			hover = name.replace( id, hover ),
			val = input.val() || $( '[name="' + hover + '"]' ).val() || ( api && api.getControlValue()[ 'normal' ] ) || '',
			preview_img = en.parent().find( '.sk_btn_preview_image' ),
			prev_img = val.match( /(http|https):\/\/[^ ]+(\.gif|\.jpg|\.jpeg|\.png)/gm );

		if ( val ) {
			en.addClass( 'active_stylekit' );
		}

		if ( prev_img && prev_img[0] ) {
			preview_img.css( 'display', 'inline-block' );
		}

		en.off().on( 'click', function( e ) {

			e.preventDefault();

			var en    = $( this ),
				en2   = $( this ),
				par   = en.closest( '.vc_param' ).length ? en.parent() : body,
				inp   = en.prev( '[name]' ),
				name  = inp.attr( 'name' ),
				hvrid = inp.data( 'hover_id' ),
				id    = name.match( /\w+/g ),
				id    = id[ id.length - 1 ],
				nameT = name.replace( id, id + '_tablet' ),
				nameM = name.replace( id, id + '_mobile' ),
				csf   = inp.data( 'fields' ) + ' padding margin',
				csf_ar= csf.split( ' ' ),
				form  = $( 'form', kit_modal ),
				vcm   = en.closest( '.vc_ui-panel-content-container' ),
				vcp   = en.closest( '.ui-draggable' ),
				notvc = !vcm.length,
				nameH = vcm.length ? inp.data( 'hover_id' ) : ( id === '_css_input_textarea' ? name.replace( id, id + '_focus' ) : name.replace( id, id + '_hover' ) ),
				customizer = $( '.wp-customizer' ).length,
				form_timeout = 0,
				activeDevice = kit_modal.find( '.cz_sk_active_tab' ),
				activeDevice = activeDevice.hasClass( 'fa-tablet-alt' ) ? 'tablet' : activeDevice.hasClass( 'fa-mobile-alt' ) ? 'mobile' : 'desktop',
				cachedValue = api ? api.getControlValue() : {};

			var reset_kit = function( r ) {

				// Remove active mode.
				en.removeClass( 'active_stylekit' ).parent().find( '.sk_btn_preview_image' ).next( 'span' ).html( '' );

				// Turn off form changes.
				form.off( 'keyup change' );

				// Reset Process
				kit_modal.find( '[name]' ).not( '[name="live_id"]' ).val( '' ).trigger( 'change' ).attr( 'placeholder', '' ).closest( 'form' ).find( '.codevz-icon-preview, .codevz-font-remove, .codevz-icon-remove' ).trigger( 'click' ).find( 'i:not(.fa-remove)' ).removeClass();
				kit_modal.find( '[type="checkbox"]' ).prop( 'checked', false ).trigger( 'change' );
				kit_modal.find( '.cz_connect' ).removeClass( 'cz_connect' );
				kit_modal.find( '[name="background[orientation]"]' ).val( '90deg' ).trigger( 'change' );
				kit_modal.find( '.wp-picker-clear' ).trigger( 'click' );
				$( '.cz_advance_bg, .cz_advance_typo' ).show();
				kit_modal.find( '.cz_bg_advanced, .codevz-plus-advanced-fields' ).hide();
				$( '.sk_btn_preview_image' ).hide();

				if ( r != 2 ) {
					kit_modal.find( '[name]' ).closest( '.cz_sk_row > div > .codevz-field' )[ r ? 'addClass' : 'hide' ]().closest( '.cz_sk_row' )[ r ? 'addClass' : 'hide' ]();
				}

				// Reset icon.
				if ( r == 1 ) {

					var name  = kit_modal.find( '[name="live_id"]' ).val(),
						id    = name.match( /\w+/g ),
						id    = id[ id.length - 1 ],
						nameT = name.replace( id, id + '_tablet' ),
						nameM = name.replace( id, id + '_mobile' ),
						hover = par.find( '[name="' + name + '"]' ).attr( 'data-hover_id' ),
						hover = name.replace( id, hover );

					// Reset all hidden fields.
					par.find( '[name="' + name + '"], [name="' + nameT + '"], [name="' + nameM + '"], [name="' + hover + '"]' ).val( '' ).trigger( 'change' );
				}

				// Update hidden field
				clearTimeout( form_timeout );
				form_timeout = setTimeout(function() {
					form.on( 'keyup change', '[name]', function() {

					var fieldName = $( '[name="live_id"]', form ).val(),
						new_vals = form.find( '[name]' ).filter(function() {
						  var $el = $(this);
						  var type = $el.attr('type');

						  if (type === 'checkbox') {
						    return true;
						  }

						  if (type === 'radio') {
						    return $el.prop('checked');
						  }

						  return !!$el.val();
						}).codevzSerializeObject();

					new_vals = objToCSS( new_vals );

					if ( new_vals ) {

						en.addClass( 'active_stylekit' );

						var prev_img = new_vals.match( /(http|https):\/\/[^ ]+(\.gif|\.jpg|\.jpeg|\.png)/gm );

						if ( prev_img && prev_img[0] ) {
							preview_img.css( 'background-image', 'url(' + prev_img[0] + ')' ).css( 'display', 'inline-block' );
						} else {
							preview_img.removeAttr( 'style' );
						}

					} else {

						en.removeClass( 'active_stylekit' );
						preview_img.removeAttr( 'style' );

					}

					// Set changes.
					if ( api ) {

						var value = $.extend( {}, {
								normal: '0',
								hover: '0',
								rtl: '0'
							}, cachedValue || api.getControlValue() || {} ),
							
							rtl = new_vals.match( "RTL(.*)RTL" );

						if ( $( '.cz_sk_hover' ).hasClass( 'cz_active' ) ) {
							
							if ( rtl && rtl[ 0 ] ) {
								new_vals = new_vals.replace( rtl[ 0 ], '' );
							}

							value[ 'hover' ] = new_vals || '0';

						} else if ( rtl && rtl[ 1 ] ) {

							value[ 'normal' ] = new_vals.replace( rtl[ 0 ], '' ) || '0';
							value[ 'rtl' ] = rtl[ 1 ] || '0';

						} else {

							value[ 'normal' ] = new_vals || '0';

						}

						if ( value[ 'hover' ] == null ) {
							value[ 'hover' ] = '0';
						}

						// Live font family.
						if ( value[ 'normal' ].indexOf( 'font-family' ) >= 0 ) {

							var font = value[ 'normal' ].match( /font-family:(.*?);/ );

							if ( font && font[1] ) {
								$( '#elementor-preview-iframe' ).contents().find( 'head' ).append( "<link rel='stylesheet' href='https://fonts.googleapis.com/css?family=" + font[1].replace( '=', ':' ).replace( /"|\\|'/g, '' ).replace( / /g, '+' ) + "' type='text/css' media='all' />" );
							}

						}

						// Cache value.
						cachedValue = value;

						// Set value.
						api.setValue( value );

					} else {
						par.find( '[name="' + fieldName + '"]' ).val( new_vals ).trigger( 'change' );
					}

					sk_value_detection( en );
					});

					// Fix active mode color.
					if ( inp.val() && ! api ) {
					en.addClass( 'active_stylekit' );
					}

				}, 100 );

				},
				show_csf = function() {

					$.each( csf_ar, function(i, k) {
						if ( k === 'border' ) {
							kit_modal.find( '[name="border-style"], [name="border-color"], [name="border-radius"]' ).closest( '.codevz-field' ).show().closest( '.cz_sk_row' ).show();
							kit_modal.find( '[name="border-width[top]"]' ).parents( '.codevz-field' ).last().show().closest( '.cz_sk_row' ).show();
						} else if ( k === 'border-color' ) {
							kit_modal.find( '[name="border-style"]' ).val( 'solid' ).trigger( 'change' );
							kit_modal.find( '.codevz-field-key-border-color' ).show();
						} else if ( k === 'padding' || k === 'margin' ) {
							kit_modal.find( '[name="' + k + '[top]"]' ).parents( '.codevz-field' ).last().show().closest( '.cz_sk_row' ).show();
						} else if ( k === 'box-shadow' || k === 'text-shadow' ) {
							kit_modal.find( '[name="' + k + '[x]"]' ).parents( '.codevz-field' ).last().show().closest( '.cz_sk_row' ).show();
						} else if ( k === 'background' ) {
							kit_modal.find( '.cz_sk_bg_row, .codevz-field-background' ).show();
						} else if ( k === 'svg' ) {
							kit_modal.find( '[name="_class_svg_type"], [name="_class_svg_size"], [name="_class_svg_color"]' ).closest( '.codevz-field' ).show().closest( '.cz_sk_row' ).show();
						} else if ( k === 'top' || k === 'right' || k === 'bottom' || k === 'left' ) {
							kit_modal.find( '.codevz-field-key-positions' ).show().closest( '.cz_sk_row' ).show();
						} else {

							kit_modal.find( '[name="' + k + '"]' ).closest( '.codevz-field' ).show().closest( '.cz_sk_row' ).show();

							if ( k === 'color' || k === 'font-size' ) {
								kit_modal.find( '[name="color"], [name="font-size"]' ).closest( '.codevz-field' ).show().closest( '.cz_sk_row' ).show();
							}

						}
					});

					kit_modal.find( '[name]' ).each(function() {
						var pn = $( this );
						if ( pn.val() && pn.attr( 'name' ) != 'live_id' && pn.attr( 'name' ) != 'background[orientation]' ) {
							pn.closest( '.codevz-field' ).show().closest( '.cz_sk_row' ).show();
							pn.closest( 'fieldset' ).closest( '.codevz-field' ).show();

							if ( pn.attr( 'name' ) == 'background[image]' ) {
								pn.closest( '.codevz-field-background' ).show();
							}
						}
					});

					kit_modal.find( '.cz_sk_row > .cz_hr' ).hide().each(function() {
						var n = $( this ).next( '.col' );
						if ( n.find('> .codevz-field').css('display') == 'block' || n.next( '.col' ).find('> .codevz-field').css('display') == 'block' || n.next( '.col' ).next( '.col' ).find('> .codevz-field').css('display') == 'block' ) {
							$( this ).show();
						} else {
							$( this ).hide();
						}
					});

				},
				set_values = function( arr, l, placeholder ) {

					if ( ! placeholder ) {
						kit_modal.find( '[name="live_id"]' ).val( l ).trigger( 'change' );
					}

					kit_modal.find( '[name="font-family"]' ).css( 'font-family', 'inherit' );

					prv.addClass( 'codevz-field-cz_sk_hidden' );

					$.each( arr, function( key, val ) {
						var input_n = kit_modal.find( '[name="' + key + '"]' ), input_n2;

						if ( typeof val == 'object' ) {
							$.each( val, function( k, v ) {
								input_n2 = kit_modal.find( '[name="' + key + '[' + k + ']"]' );

								if ( placeholder ) {
									input_n2.attr( 'placeholder', v );
								} else {

									if ( v && input_n2.is( ':checkbox' ) ) {
										input_n2.prop( 'checked', !!v ).trigger( 'change' );
									} else {
										input_n2.val( v ).trigger( 'change' );
									}

									if ( input_n2.hasClass( 'wp-color-picker' ) ) {
										input_n2.wpColorPicker( 'color', v );
										if ( v && v.indexOf( 'rgba' ) >= 0 ) {
											var alpha = parseFloat( v.replace(/^.*,(.+)\)/, '$1') ) || 1;
											input_n2.closest( '.wp-picker-container' ).find( '.codevz-alpha-slider > span' ).css( 'left', ( alpha * 100 ) + '%' );
											input_n2.closest( '.wp-picker-container' ).find( '.codevz-alpha-text' ).html( alpha );
										}
									}

									if ( key === 'background' && k === 'image' ) {
										var preview_img = en.parent().find( '.sk_btn_preview_image' ),
											prev_img = v.match( /(http|https):\/\/[^ ]+(\.gif|\.jpg|\.jpeg|\.png)/gm );

										if ( prev_img && prev_img[0] ) {

											preview_img.css( 'display', 'inline-block' );

											prv.removeClass( 'codevz-field-cz_sk_hidden' ).attr( 'src', prev_img[0] );

										}
									}

								}
							});
						} else {

							if ( placeholder ) {
								input_n.attr( 'placeholder', val );
							} else {
								input_n.val( val ).trigger( 'change' );

								if ( key === 'font-family' && val ) {
									input_n.css( 'font-family', val );
									input_n.parent().find( '.codevz-font-remove' ).removeClass( 'hidden' );
								} else if ( key === '_class_indicator' && val ) {
									input_n.parent().find( '.codevz-icon-remove, .codevz-icon-preview' ).removeClass( 'hidden' ).find( 'i' ).addClass( val );
								}
							}

						}

						if ( input_n.hasClass( 'wp-color-picker' ) && ! placeholder ) {
							input_n.wpColorPicker( 'color', val );
							if ( val && val.indexOf( 'rgba' ) >= 0 ) {
								var alpha = parseFloat( val.replace(/^.*,(.+)\)/, '$1') ) || 1;
								input_n.closest( '.wp-picker-container' ).find( '.codevz-alpha-slider > span' ).css( 'left', ( alpha * 100 ) + '%' );
								input_n.closest( '.wp-picker-container' ).find( '.codevz-alpha-text' ).html( alpha );
							}
						}
					});

					if ( api && ! placeholder ) {

						var rtlValue = api.getControlValue()[ 'rtl' ] || '';
						rtlValue && kit_modal.find( '[name="rtl"]' ).val( rtlValue ).trigger( 'change' );

					}

					show_csf();
				},
				resizes = function() {
				var wpb_content_height = parseInt( vcm.css( 'height' ) ) - kit_modal.prev( '.ui-dialog-titlebar' ).height();

				kit_modal.css({
					'width': ( parseInt( vcm.css( 'width' ) ) - 55 ) + 'px',
					'height': ( wpb_content_height - 50 ) + 'px',
					'max-height': parseInt( vcm.css( 'height' ) ) + 'px',
				}).closest( '.ui-dialog' ).css({
					'top': parseInt( vcp.css( 'top' ) ) + vcp.find( '.vc_ui-post-settings-header-container' ).outerHeight() + 18 + 'px',
					'left': parseInt( vcp.css( 'left' ) ) + 25 + 'px',
					'width': ( parseInt( vcm.css( 'width' ) ) - 35 ) + 'px',
					'height': ( parseInt( vcm.css( 'height' ) ) - 40 ) + 'px',
				});
				};

			// Fix advanced mode on first open.
			if ( kit_modal.parent().find( '.cz_advanced_mode_active' ).length ) {
				kit_modal.parent().find( '.cz_advanced_mode_active' ).trigger( 'click' );
			}

			// Fix hover ID for menus
			if ( nameH ) {
			if ( nameH.indexOf( 'menu-item' ) >= 0 ) {
				nameH = nameH.replace( '_hover', '' ).replace( '[', '_hover[' );
			}
			if ( nameH.indexOf( '_css_menu_a_' ) >= 0 ) {
			 nameH = nameH.replace( '_hover]', ']' ).replace( '_css_menu_a_', '_css_menu_a_hover_' );
			}
			if ( nameH.indexOf( '_css_menu_ul_a_' ) >= 0 ) {
			 nameH = nameH.replace( '_hover]', ']' ).replace( '_css_menu_ul_a_', '_css_menu_ul_a_hover_' );
			}
			}

		var value = api ? ( api.getControlValue()['normal'] || '' ) || '' : inp.val();

			// RTL set values on first load.
			if ( $( '.rtl, .site-rtl-user-ltr' ).length ) {

				if ( api ) {

					var rtlValue = api.getControlValue()['rtl'];

					if ( rtlValue && rtlValue != '0' ) {

						value = value + rtlValue;

						api.setValue( {

							'normal': value || '0',
							'hover': api.getControlValue()['hover'] || '0',
							'rtl': '0'

						} );

					}

				} else {

					value = value.replace( /RTL/g, '' );

					inp.val( value ).trigger( 'change' );

				}

			}

			// First load
			reset_kit();
			sk_value_detection( en );

			var new_css = cssToObj( value );

			if ( new_css ) {
			en.addClass( 'active_stylekit' );
			}

			set_values( new_css, name );

			// Auto RTL. 
			kit_modal.find( '.xtra-auto-rtl' ).off().on( 'click', function( e ) {

				var auto_rtl = '',
						new_css = cssToObj( api ? ( api.getControlValue()['normal'] || '' ) || '' : inp.val() );

				if ( new_css['text-align'] == 'left' ) {
					auto_rtl += 'text-align:right;';
				} else if ( new_css['text-align'] == 'right' ) {
					auto_rtl += 'text-align:left;';
				}

				if ( new_css['float'] == 'left' ) {
					auto_rtl += 'float:right;';
				} else if ( new_css['float'] == 'right' ) {
					auto_rtl += 'float:left;';
				}

				if ( new_css['left'] && new_css['right'] ) {
					auto_rtl += 'left:' + new_css['right'] + ';';
					auto_rtl += 'right:' + new_css['left'] + ';';
				} else if ( new_css['left'] ) {
					auto_rtl += 'left:auto;right:' + new_css['left'] + ';';
				} else if ( new_css['right'] ) {
					auto_rtl += 'right:auto;left:' + new_css['right'] + ';';
				}

				// Padding and margin.
				$.each( { a: 'margin', b: 'padding' }, function( e, v ) {

					if ( new_css[ v ]['left'] != new_css[ v ]['right'] ) {

					if ( new_css[ v ]['left'] && new_css[ v ]['right'] ) {
						auto_rtl += v + '-left:' + new_css[ v ]['right'] + ';';
						auto_rtl += v + '-right:' + new_css[ v ]['left'] + ';';
					} else if ( new_css[ v ]['left'] ) {
						auto_rtl += v + '-left:0px;' + v + '-right:' + new_css[ v ]['left'] + ';';
					} else if ( new_css[ v ]['right'] ) {
						auto_rtl += v + '-right:0px;' + v + '-left:' + new_css[ v ]['right'] + ';';
					}

					}

				});

				// Border width.
				if ( new_css['border-width']['left'] != new_css['border-width']['right'] ) {

					if ( new_css['border-width']['left'] && new_css['border-width']['right'] ) {
					auto_rtl += 'border-left-width:' + new_css['border-width']['right'] + ';';
					auto_rtl += 'border-right-width:' + new_css['border-width']['left'] + ';';
					} else if ( new_css['border-width']['left'] ) {
					auto_rtl += 'border-left-width:0px;border-right-width:' + new_css['border-width']['left'] + ';';
					} else if ( new_css['border-width']['right'] ) {
					auto_rtl += 'border-right-width:0px;border-left-width:' + new_css['border-width']['right'] + ';';
					}

				}

				// Border radius.
				if ( new_css['border-radius'] ) {

					const radius = new_css['border-radius'].split(' ');

					if (radius.length === 4) {
					    auto_rtl += 'border-radius:' + radius[1] + ' ' + radius[0] + ' ' + radius[3] + ' ' + radius[2] + ';';
					} else if (radius.length === 3) {
					    auto_rtl += 'border-radius:' + radius[0] + ' ' + radius[2] + ' ' + radius[1] + ';';
					} else if (radius.length === 2) {
					    auto_rtl += 'border-radius:' + radius[1] + ' ' + radius[0] + ';';
					}

				}

				$( this ).parent().find( 'textarea' ).val( auto_rtl ).trigger( 'change' );

				e.preventDefault();

			});

			// Modal kit dialog
			if ( ! kit_modal.hasClass( 'ui-dialog-content' ) ) {
			kit_modal.codevz_reload_script().dialog({
				autoOpen: false,
				title: en.text(),
				closeText: '',
				//modal: false,
				height: 'auto',
				width: ( notvc ? 500 : vcm.width() ),
				maxHeight: ( notvc ? wind.height() * 0.75 : vcm.height() ),
				draggable: ( notvc ? true : false ),
				resizable: ( notvc ? true : false ),
				position: {of: ( notvc ? wind : vcm ),my:'center',at:'center'},
				open: function() {
				var titlebar = kit_modal.prev( '.ui-dialog-titlebar' );

					titlebar.find( '.fa-desktop' ).addClass( 'cz_sk_active_tab' ).siblings().removeClass( 'cz_sk_active_tab' );

					vcm.css( 'filter', 'blur(2px)' );

					titlebar.find( 'button' ).attr( 'data-title', sk_aiL10n.close );
			 
					titlebar.find( '.ui-dialog-title' ).attr( 'data-xtitle', sk_aiL10n.normal + ' ' + sk_aiL10n.styles );
				},
				close: function() {vcm.css( 'filter', 'blur(0px)' );}
			}).closest( '.ui-dialog' ).css({position:"fixed"});

			setTimeout(function() {
				kit_modal.dialog( "open" );
				resizes();
			}, 50 );

			// Reload sizes
			if ( ! notvc ) {
				vcp.off( 'drag.cz_sk resize.cz_sk' ).on( 'drag.cz_sk resize.cz_sk', resizes );
				wind.off( 'resize.cz_sk' ).on( 'resize.cz_sk', resizes );
			}
			} else {
			kit_modal.dialog( "open" ).prev( '.ui-dialog-titlebar' ).find( '.ui-dialog-title' ).html( en.text() );
			resizes();
			}

			var mTitle = kit_modal.prev( '.ui-dialog-titlebar' );

			// Hover
			mTitle.find( '.ui-dialog-title' ).removeClass( 'cz_deactive' ).parent().find( '.cz_sk_hover' ).removeClass( 'cz_active' );
			
			if ( ( hvrid && nameH ) || api ) {

			mTitle.find( '.cz_sk_hover' ).remove();

			if ( hvrid === '_css_input_textarea_focus' ) {
				mTitle.append( '<span class="cz_sk_hover" data-xtitle="' + sk_aiL10n.focus + ' ' + sk_aiL10n.styles + '">' + sk_aiL10n.focus + '</span>' );
			} else {
				mTitle.append( '<span class="cz_sk_hover" data-xtitle="' + sk_aiL10n.hover + ' ' + sk_aiL10n.styles + '">' + sk_aiL10n.hover + '</span>' );
			}

			mTitle.find( '.cz_sk_hover' ).off( 'click' ).on( 'click', function() {
				reset_kit( 2 );
				
				// Hover.
				var val = api ? cachedValue[ 'hover' ] || ( api.getControlValue()['hover'] || '0' ) : $( '[name="' + nameH + '"]' ).val();
				set_values( cssToObj( val ), nameH );

				// Placeholder.
				var val_p = api ? cachedValue[ 'normal' ] || ( api.getControlValue()['normal'] || '0' ) : $( '[name="' + name + '"]' ).val();
				set_values( cssToObj( val_p ), name, true );

				if ( val ) {
					en.addClass( 'active_stylekit' );
				}
				mTitle.find( '.fa-desktop' ).addClass( 'cz_sk_active_tab' ).siblings().removeClass( 'cz_sk_active_tab' );
				$( this ).addClass( 'cz_active' ).parent().find( '.ui-dialog-title' ).addClass( 'cz_deactive' );
			});

			mTitle.find( '.ui-dialog-title' ).off( 'click' ).on( 'click', function() {
				reset_kit( 2 );
				var val = api ? cachedValue[ 'normal' ] || value : $( '[name="' + name + '"]' ).val();
				set_values( cssToObj( val ), name );
				if ( val ) {
					en.addClass( 'active_stylekit' );
				}
				$( this ).removeClass( 'cz_deactive' ).parent().find( '.cz_sk_hover' ).removeClass( 'cz_active' );
			});

			} else {
			mTitle.find( '.cz_sk_hover' ).off( 'click' ).remove();
			mTitle.find( '.ui-dialog-title' ).off( 'click' ).removeClass( 'cz_deactive' );
			}

			// Reset button
			if ( ! mTitle.find( '.fa-refresh' ).length ) {
				mTitle.append( '<i class="fa fa-refresh" data-title="' + sk_aiL10n.reset + '"></i>' );
			}

			mTitle.find( '.fa-refresh' ).off().on( 'click', function() {

				if ( confirm( sk_aiL10n.reset_confirm ) ) {

					reset_kit( 1 );

					setTimeout( function() {

						var parent = par.find( '[name="' + $( '[name="live_id"]', form ).val() + '"]' ).parent();

						en.removeClass( 'active_stylekit' );
						parent.find( '.sk_btn_preview_image' ).next( 'span' ).html( '' );
						parent.find( '.cz_vdt' ).html( '' );

						if ( api ) {

							var eName = en.attr( 'data-name' ).replace( /_tablet|_mobile/g, '' );

							cachedValue = {};
							window[ 'xtraElementor' ][ eName ].setValue( { normal: '0', hover: '0', rtl: '0' } );
							window[ 'xtraElementor' ][ eName + '_tablet' ].setValue( { normal: '0', hover: '0', rtl: '0' } );
							window[ 'xtraElementor' ][ eName + '_mobile' ].setValue( { normal: '0', hover: '0', rtl: '0' } );

						}

					}, 100 );

				}

			});

			// Responsive buttons
			!mTitle.find( '.fa-mobile-alt' ).length && mTitle.append( '<i class="fas fa-mobile-alt" data-title="' + sk_aiL10n.mobile + '"></i>' );
			!mTitle.find( '.fa-tablet-alt' ).length && mTitle.append( '<i class="fas fa-tablet-alt" data-title="' + sk_aiL10n.tablet + '"></i>' );
			!mTitle.find( '.fa-desktop' ).length && mTitle.append( '<i class="fas fa-desktop cz_sk_active_tab" data-title="' + sk_aiL10n.desktop + '"></i>' );
			
			mTitle.find( '.fa-tablet-alt, .fa-mobile-alt' ).removeClass( 'cz_deactive_tm cz_sk_active_tab' );
			mTitle.find( '.fa-desktop' ).addClass( 'cz_sk_active_tab' );
			if ( par.hasClass( 'cz_sk' ) || en.closest( '[id*="header_1"],[id*="header_2"],[id*="header_3"],[id*="header_5"],[id*="fixed_side_1"]' ).length || en.closest( '.codevz-field-group' ).length || en.closest( '[id*="mobile_header"]' ).length ) {
				mTitle.find( '.fa-tablet-alt, .fa-mobile-alt' ).addClass( 'cz_deactive_tm' );
			}
			if ( ! par.find( '[name="' + nameM + '"]' ).length && ! api ) {
				mTitle.find( '.fa-mobile-alt' ).addClass( 'cz_deactive_tm' );
			}
			if ( ! par.find( '[name="' + nameT + '"]' ).length && ! api ) {
				mTitle.find( '.fa-tablet-alt' ).addClass( 'cz_deactive_tm' );
			}

			//if ( en.closest( '.wpb_sortable' ).length ) {
				//mTitle.find( '.fa-tablet-alt, .fa-mobile-alt' ).removeClass( 'cz_deactive_tm' );
			//}

			// Responsive on click.
			mTitle.find( '.fa-desktop, .fa-tablet-alt, .fa-mobile-alt' ).off().on( 'click', function() {

			var en = $( this ),
				nn = en.hasClass( 'fa-desktop' ) ? name : en.hasClass( 'fa-tablet-alt' ) ? nameT : nameM;

			if ( en.hasClass( 'cz_sk_active_tab' ) || en.hasClass( 'cz_deactive_tm' ) ) {return;}
			mTitle.find( '.ui-dialog-title' ).removeClass( 'cz_deactive' ).parent().find( '.cz_sk_hover' ).removeClass( 'cz_active' );

			// Set val.
			reset_kit( 2 );
			set_values( cssToObj( api ? api.getControlValue()['normal'] : par.find( '[name="' + nn + '"]' ).val() ), nn );

			// Placeholder.
			var pl_n = en.hasClass( 'fa-mobile-alt' ) ? nameT : name,
					pl_v = cssToObj( api ? api.getControlValue()[ en.hasClass( 'fa-mobile-alt' ) ? 'tablet' : 'normal' ] : par.find( '[name="' + pl_n + '"]' ).val() );
			if ( ! pl_v ) {
				pl_v = cssToObj( api ? api.getControlValue()['normal'] : par.find( '[name="' + name + '"]' ).val() );
			}
			setTimeout( function() {
				set_values( pl_v, pl_n, true );
			}, 500 );

			en.addClass( 'cz_sk_active_tab' ).siblings().removeClass( 'cz_sk_active_tab' );

			// Customizer trigger devices icon.
			if ( customizer ) {

				var devices = $( '.devices-wrapper' ),
					wrapper = $( '.wp-full-overlay' );

				devices.find( 'button' ).removeClass( 'active' );
				wrapper.removeClass( 'preview-desktop preview-tablet preview-mobile' );

				if ( en.hasClass( 'fa-tablet-alt' ) ) {
					devices.find( '.preview-tablet' ).addClass( 'active' );
					wrapper.addClass( 'preview-tablet' );
				} else if ( en.hasClass( 'fa-mobile-alt' ) ) {
					devices.find( '.preview-mobile' ).addClass( 'active' );
					wrapper.addClass( 'preview-mobile' );
				} else {
					devices.find( '.preview-desktop' ).addClass( 'active' );
					wrapper.addClass( 'preview-desktop' );
				}

			} else if ( api ) {

				setTimeout( function() {
					$( 'a[data-sk="elementor-control-sk-c' + en.attr( 'data-cid' ) + '"]' ).trigger( 'click' );
				}, 100 );

				if ( en.hasClass( 'fa-tablet-alt' ) ) {
					$( '#e-responsive-bar-switch-tablet, .MuiTabs-flexContainer > button:nth-child(2)' ).trigger( 'click' );

				} else if ( en.hasClass( 'fa-mobile-alt' ) ) {
					$( '#e-responsive-bar-switch-mobile, .MuiTabs-flexContainer > button:nth-child(3)' ).trigger( 'click' );

				} else {
					$( '#e-responsive-bar-switch-desktop, .MuiTabs-flexContainer > button:nth-child(1)' ).trigger( 'click' );
				}

			} else {

				var wpb_current_device  = $( '#vc_screen-size-current' ),
					wpb_get_current 	= $( '#vc_screen-size-control' ),
					wpb_vc_inline_frame = $( '#vc_inline-frame' ),
					wpb_list_of_devices = $( '.vc_dropdown-list' );

				wpb_list_of_devices.find( 'a' ).removeClass( 'active' );

				if ( en.hasClass( 'fa-tablet-alt' ) ) {

					if ( ! wpb_current_device.hasClass( 'vc-c-icon-layout_landscape-tablets' ) && ! wpb_current_device.hasClass( 'vc-c-icon-layout_portrait-tablets' ) ) {

						wpb_current_device.removeClass( function( i, c ) {
							return ( c.match( /(^|\s)vc-c-icon-layout_\S+/g ) || [] ).join( ' ' );
						}).addClass( 'vc-c-icon-layout_portrait-tablets' );

						wpb_list_of_devices.find( '.vc-c-icon-layout_portrait-tablets' ).addClass( 'active' );

						wpb_vc_inline_frame.css( 'width', 768 );

					}

				} else if ( en.hasClass( 'fa-mobile-alt' ) ) {

					if ( ! wpb_current_device.hasClass( 'vc-c-icon-layout_landscape-smartphones' ) && ! wpb_current_device.hasClass( 'vc-c-icon-layout_portrait-smartphones' ) ) {

						wpb_current_device.removeClass( function( i, c ) {
							return ( c.match( /(^|\s)vc-c-icon-layout_\S+/g ) || [] ).join( ' ' );
						}).addClass( 'vc-c-icon-layout_portrait-smartphones' );

						wpb_list_of_devices.find( '.vc-c-icon-layout_portrait-smartphones' ).addClass( 'active' );

						wpb_vc_inline_frame.css( 'width', 480 );

					}

				} else if ( ! wpb_current_device.hasClass( 'vc-c-icon-layout_default' ) ) {

					wpb_current_device.removeClass( function( i, c ) {
						return ( c.match( /(^|\s)vc-c-icon-layout_\S+/g ) || [] ).join( ' ' );
					}).addClass( 'vc-c-icon-layout_default' );

					wpb_vc_inline_frame.css( 'width', '100%' );

					wpb_list_of_devices.find( '.vc-c-icon-layout_default' ).addClass( 'active' );

				}

			}

			});

		// Customizer trigger devices icon on open.
		var device = $( '.devices-wrapper .active' ).data( 'device' ) || $( '#e-responsive-bar-switcher input:checked' ).val();

		if ( ! device ) {

			var wpb_current_device = $( '#vc_screen-size-current' );

			if ( wpb_current_device.hasClass( 'vc-c-icon-layout_landscape-tablets' ) || wpb_current_device.hasClass( 'vc-c-icon-layout_portrait-tablets' ) ) {
				device = 'tablet';
			} else if ( wpb_current_device.hasClass( 'vc-c-icon-layout_landscape-smartphones' ) || wpb_current_device.hasClass( 'vc-c-icon-layout_portrait-smartphones' ) ) {
				device = 'mobile';
			} else {

				// Temporary FIX.
		        // New Elementor Editor => Find the active device based on `aria-selected`
		        var activeButton = $('.MuiTabs-flexContainer button[aria-selected="true"]');
		        var activeIndex = $('.MuiTabs-flexContainer button').index(activeButton);

		        switch (activeIndex) {
		            case 0:
		                device = 'desktop'; // Button 1
		                break;
		            case 1:
		                device = 'tablet';  // Button 2
		                break;
		            case 2:
		                device = 'mobile';  // Button 3
		                break;
		        }
		    }

		}

		if ( api ) {

			var cid = parseFloat( en2.attr( 'data-sk' ).replace( 'elementor-control-sk-c', '' ) );

			if ( device === 'tablet' && ! mTitle.find( '.fa-tablet-alt' ).hasClass( 'cz_sk_active_tab' ) ) {
				
				mTitle.find( '.fa-tablet-alt' ).addClass( 'cz_sk_active_tab' ).siblings().removeClass( 'cz_sk_active_tab' );
				mTitle.find( '.fa-desktop' ).attr( 'data-cid', cid - 1 );
				mTitle.find( '.fa-mobile-alt' ).attr( 'data-cid', cid + 1 );

			} else if ( device === 'mobile' && ! mTitle.find( '.fa-mobile-alt' ).hasClass( 'cz_sk_active_tab' ) ) {
				
				mTitle.find( '.fa-mobile-alt' ).addClass( 'cz_sk_active_tab' ).siblings().removeClass( 'cz_sk_active_tab' );
				mTitle.find( '.fa-desktop' ).attr( 'data-cid', cid - 2 );
				mTitle.find( '.fa-tablet-alt' ).attr( 'data-cid', cid - 1 );

			} else {

				mTitle.find( '.fa-desktop' ).addClass( 'cz_sk_active_tab' ).siblings().removeClass( 'cz_sk_active_tab' );
				mTitle.find( '.fa-desktop' ).attr( 'data-cid', cid );
				mTitle.find( '.fa-tablet-alt' ).attr( 'data-cid', cid + 1 );
				mTitle.find( '.fa-mobile-alt' ).attr( 'data-cid', cid + 2 );
						
			}

		} else {

			if ( device === 'tablet' && ! mTitle.find( '.fa-tablet-alt' ).hasClass( 'cz_sk_active_tab' ) ) {
				
				mTitle.find( '.fa-tablet-alt' ).trigger( 'click' );

			} else if ( device === 'mobile' && ! mTitle.find( '.fa-mobile-alt' ).hasClass( 'cz_sk_active_tab' ) ) {
				
				mTitle.find( '.fa-mobile-alt' ).trigger( 'click' );

			}

		}

			// Copy/Paste icons.
			if ( ! mTitle.find( '.fa-file-o' ).length && ! mTitle.find( '.fa-files-o' ).length ) {
			mTitle.append( '<i class="fa fa-file-o hide" data-title="' + sk_aiL10n.copy + '"></i><i class="fa fa-files-o hide" data-title="' + sk_aiL10n.paste + '"></i>' );
			}

			// Copy.
			mTitle.find( '.fa-file-o' ).addClass( 'hide' ).off().on( 'click', function() {

				if ( kit_modal.find( '.xtra-readonly' ).length ) {
					alert( sk_aiL10n.pro );
					return false;
				}

				var $this = $( this ),
					eName = api ? en.attr( 'data-name' ).replace( /_tablet|_mobile/g, '' ) : '';

					var sk = {

						'normal' 	: api ? window[ 'xtraElementor' ][ eName ].getControlValue() || '0' : par.find( '[name="' + name + '"]' ).val(),
						'tablet' 	: api ? window[ 'xtraElementor' ][ eName + '_tablet' ].getControlValue() || '0' : par.find( '[name="' + nameT + '"]' ).val(),
						'mobile' 	: api ? window[ 'xtraElementor' ][ eName + '_mobile' ].getControlValue() || '0' : par.find( '[name="' + nameM + '"]' ).val(),
						'hover' 	: par.find( '[name="' + nameH + '"]' ).val() || '0',
						'rtl' 		: '0'

					};

				// Copy SK.
				codevzClipboard( 'copy', sk, ( data ) => {
					console.log( 'Copy StyleKit: ', data );
				});

				$this.addClass( 'cz_copied' ).attr( 'data-title', sk_aiL10n.copied );

				setTimeout( function() {
					$this.addClass( 'cz_copied' ).attr( 'data-title', sk_aiL10n.copy );
				}, 1000 );

				$( '.fa-files-o' ).addClass( 'cz_paste' ).attr( 'data-title', sk_aiL10n.paste );

			});

			// Paste.
			mTitle.find( '.fa-files-o' ).addClass( 'hide' ).off().on( 'click', function( e ) {

			if ( kit_modal.find( '.xtra-readonly' ).length ) {
				alert( sk_aiL10n.pro );
				return false;
			}

			if ( confirm( sk_aiL10n.paste_confirm ) ) {

				mTitle.find( '.fa-desktop' ).addClass( 'cz_sk_active_tab' ).siblings().removeClass( 'cz_sk_active_tab' );

				// Paste an object:
				codevzClipboard( 'paste', null, ( data ) => {

					console.log( 'Paste StyleKit: ', data );

					if ( data ) {

						var normal = data.normal,
								tablet = data.tablet,
								mobile = data.mobile,
								hover = data.hover;

						if ( api ) {

							var eName = en.attr( 'data-name' ).replace( /_tablet|_mobile/g, '' );

							window[ 'xtraElementor' ][ eName ].setValue( typeof normal == 'object' ? normal : {

								'normal': normal || '0',
								'hover': hover || '0',
								'rtl': data.rtl ? data.rtl : '0'

							} );

							window[ 'xtraElementor' ][ eName + '_tablet' ].setValue( typeof tablet == 'object' ? tablet : {

								'normal': tablet || '0',
								'hover': '0',
								'rtl': '0'

							} );

							window[ 'xtraElementor' ][ eName + '_mobile' ].setValue( typeof mobile == 'object' ? mobile : {

								'normal': mobile || '0',
								'hover': '0',
								'rtl': '0'

							} );

							$( '.ui-dialog-titlebar-close' ).trigger( 'click' );

							setTimeout( function() {
								en.trigger( 'click' );
							}, 10 );

						} else {

							normal && par.find( '[name="' + name + '"]' ).val( normal ).trigger( 'change' ).next( 'a' ).trigger( 'click' );
							tablet && par.find( '[name="' + nameT + '"]' ).val( tablet ).trigger( 'change' );
							mobile && par.find( '[name="' + nameM + '"]' ).val( mobile ).trigger( 'change' );
							hover && par.find( '[name="' + nameH + '"]' ).val( hover ).trigger( 'change' );

						}

					}

				}); // Paste.

			}

			});

			// Show all SK options
			if ( ! mTitle.find( '.fa-toggle-off' ).length ) {
				mTitle.append( '<i class="cz_advanced_mode" data-title="' + sk_aiL10n.advanced + '"><i class="fa fa-toggle-off"></i></i>' );
			}

			mTitle.find( '.cz_advanced_mode' ).off().on( 'click', function() {
				
				$( this ).toggleClass( 'cz_advanced_mode_active' ).find( 'i' ).toggleClass( 'fa-toggle-on' );
				kit_modal.toggleClass( 'cz_show_all_sk' );
				mTitle.find( '.fa-file-o,.fa-files-o' ).toggleClass( 'hide' );

				if ( $( this ).hasClass( 'cz_advanced_mode_active' ) ) {

					//$( '.cz_advance_bg, .cz_advance_typo' ).hide();
					//$( '.codevz-plus-advanced-fields, .cz_bg_advanced' ).show();

				} else {

					$( '.cz_advance_bg, .cz_advance_typo' ).show();
					$( '.codevz-plus-advanced-fields, .cz_bg_advanced' ).hide();

				}

			});

			// Customizer responsive switcher.
			var devices = $( '.devices-wrapper' );
			if ( devices.length ) {

			devices.find( 'button' ).off( 'click.cz' ).on( 'click.cz', function() {
				mTitle.find( '.fa-' + $( this ).data( 'device' ) ).trigger( 'click' );
			} );

			// Auto switch to responsive mode.
			var activeDevice = devices.find( '.active' );
			if ( activeDevice.length ) {
				mTitle.find( '.fa-' + activeDevice.data( 'device' ) ).trigger( 'click' );
			}

			} // customizer

		}); // style kit btn
		}); // each btn

	}); // reload

	body.on( 'change', '[name="background[image]"]', function() {

		var $this = $( this ), val = $this.val();

		prv.addClass( 'codevz-field-cz_sk_hidden' );

		setTimeout( function() {

			if ( val ) {

				var prev_img = val.match( /(http|https):\/\/[^ ]+(\.gif|\.jpg|\.jpeg|\.png)/gm );

				if ( prev_img && prev_img[0] ) {

					prv.removeClass( 'codevz-field-cz_sk_hidden' ).attr( 'src', prev_img[0] );

				}
			}

		}, 100 );

	}).on( 'change', '[name="border-style"]', function() {

		$( '.cz_sk_border_row .cz_hr' )[ $( this ).val() ? 'show' : 'hide' ]();

	});

	// Fix options for Appearance > Menus
	if ( $( '#menu-management' ).length ) {
		$( '#menu-management .item-edit' ).on('click', function(e) {
		$( this ).closest('li').codevz_reload_script();
		});

		// Fix for Pending new menu item
		$('ul.menu').on('change', 'li', function() {
		if ( $( this ).hasClass('pending') ) {
			$( this ).codevz_reload_script();
		}
		});
	}

});