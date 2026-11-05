jQuery( function( $ ) {

	var body = $( 'body' );

	if ( ! $( '.codevz-magic-mouse' ).length ) {
		body.append( '<div class="codevz-magic-mouse"><div></div><div></div></div>' );
		body.append( '<div class="codevz-magic-placeholder"></div>' );
	}

	var mm = $( '.codevz-magic-mouse' ),
		m1 = mm.find( 'div:first-child' ),
		m2 = mm.find( 'div:last-child' ),
		timeout,
		isThrottled = false,
		aa, bb, dd, m_x, m_y,
		magnetSelectors = codevzMagnetSelectors != 'undefined' ? codevzMagnetSelectors : '.xxx';

	$( window ).on( 'mousemove', function( e ) {

		if ( ! isThrottled ) {

			isThrottled = true;

			requestAnimationFrame( function() {

				isThrottled = false;

				mm.css( 'opacity', '1' );

				m_x = e.pageX;
				m_y = e.pageY;

				var parent = $( e.target ).closest( magnetSelectors );

				if ( parent.length ) {

					body.find( '.codevz-magic-mouse-transform' ).removeClass( 'codevz-magic-mouse-transform' ).css( 'transform', '' );

					var offset = parent.offset();

					a_x = ( offset.left || 0 ) + parent.outerWidth() / 2,
					a_y = ( offset.top || 0 ) + parent.outerHeight() / 2;

					mm.addClass( 'codevz-magic-mouse-hover' ).attr( 'data-left', a_x ).attr( 'data-top', a_y );
					parent.addClass( 'codevz-magic-mouse-transform' );

					aa = a_x - m_x;
					bb = a_y - m_y;
					dd = Math.sqrt( Math.pow( aa, 2 ) + Math.pow( bb, 2 ) );

					a_x = a_x - ( aa * 0.4 ) - m2.width() / 2,
					a_y = a_y - ( bb * 0.4 ) - m2.height() / 2;

					$( '.codevz-magic-placeholder-xxxx' ).html( $( e.target ).css( {} ).prop('outerHTML') ).css(
						{
							position: 'fixed',
							display: 'block',
							transform: 'scale(1.4)',
							'z-index': '999999999',
							top: $( e.target ).offset().top,
							left: $( e.target ).offset().left
						}
					);

				} else if ( $( e.target ).closest( 'a, i, button, input[type="button"], input[type="submit"]' ).length && ! $( e.target ).closest( magnetSelectors ).length ) {

					a_x = m_x - m2.width() / 2,
					a_y = m_y - m2.height() / 2;

					mm.addClass( 'codevz-magic-mouse-hover' );

				} else {

					aa = mm.attr( 'data-left' ) ? parseInt( mm.attr( 'data-left' ) ) - m_x : m_x;
					bb = mm.attr( 'data-top' ) ? parseInt( mm.attr( 'data-top' ) ) - m_y : m_y;
					dd = Math.sqrt( Math.pow( aa, 2 ) + Math.pow( bb, 2 ) );

					if ( dd > 100 ) {

						a_x = m_x - m2.width() / 2,
						a_y = m_y - m2.height() / 2;

						mm.removeClass( 'codevz-magic-mouse-hover' ).attr( 'data-left', '' ).attr( 'data-top', '' );

						$( '.codevz-magic-placeholder' ).html( '' );

						body.find( '.codevz-magic-mouse-transform' ).removeClass( 'codevz-magic-mouse-transform' ).css( 'transform', '' );

					} else {
						a_x = parseFloat( mm.attr( 'data-left' ) ) - ( aa * 0.4 ) - m2.width() / 2,
						a_y = parseFloat( mm.attr( 'data-top' ) ) - ( bb * 0.4 ) - m2.height() / 2;
					}

				}

				m1.css({
					top:  m_y - m1.height() / 2,
					left: m_x - m1.width() / 2
				});

				m2.css({
					top:  a_y,
					left: a_x
				});

				if ( mm.hasClass( 'codevz-magic-mouse-hover' ) ) {

					aa = mm.attr( 'data-left' ) ? m_x - parseInt( mm.attr( 'data-left' ) ) : m_x;
					bb = mm.attr( 'data-top' ) ? m_y - parseInt( mm.attr( 'data-top' ) ) : m_y;

					$( '.codevz-magic-mouse-transform' ).css( 'transform', 'translate( ' + ( aa * .2 ) + 'px, ' + ( bb * .2 ) + 'px )' );

				}

			});

		}

	});

});