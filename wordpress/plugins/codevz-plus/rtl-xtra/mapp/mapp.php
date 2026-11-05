<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

// Persian map.ir
function codevz_persian_map( $atts ) {

	// Settings.
	$atts[ 'map_id' ] 		= !empty( $atts[ 'map_id' ] ) 		? $atts[ 'map_id' ] 		: 'codevz_mapp_' . rand( 999, 9999 );
	$atts[ 'api_key' ] 		= !empty( $atts[ 'api_key' ] ) 		? $atts[ 'api_key' ] 		: '';
	$atts[ 'lat' ] 			= !empty( $atts[ 'lat' ] ) 			? $atts[ 'lat' ] 			: '35.700511';
	$atts[ 'lng' ] 			= !empty( $atts[ 'lng' ] ) 			? $atts[ 'lng' ] 			: '51.365479';
	$atts[ 'zoom' ] 		= !empty( $atts[ 'zoom' ] ) 		? $atts[ 'zoom' ] 			: '17';
	$atts[ 'dark_mode' ] 	= !empty( $atts[ 'dark_mode' ] ) 	? $atts[ 'dark_mode' ] 		: false;
	$atts[ 'gray_mode' ] 	= !empty( $atts[ 'gray_mode' ] ) 	? $atts[ 'gray_mode' ] 		: false;
	$atts[ 'fullscreen' ] 	= !empty( $atts[ 'fullscreen' ] ) 	? $atts[ 'fullscreen' ] 	: false;
	$atts[ 'zoom_controls' ]= !empty( $atts[ 'zoom_controls' ] )? $atts[ 'zoom_controls' ] 	: true;
	$atts[ 'my_location' ] 	= !empty( $atts[ 'my_location' ] ) 	? $atts[ 'my_location' ] 	: false;
	$atts[ 'right_click' ] 	= !empty( $atts[ 'right_click' ] ) 	? $atts[ 'right_click' ] 	: true;
	$atts[ 'marker' ] 		= !empty( $atts[ 'marker' ] ) 		? $atts[ 'marker' ] 		: '';
	$atts[ 'marker_title' ] = !empty( $atts[ 'marker_title' ] ) ? $atts[ 'marker_title' ] 	: '';
	$atts[ 'marker_desc' ] 	= !empty( $atts[ 'marker_desc' ] ) 	? $atts[ 'marker_desc' ] 	: '';

	// Content.
	ob_start();

	$url = Codevz_Plus::$url . 'rtl-xtra/mapp/assets';

	echo '<link rel="stylesheet" href="' . $url . '/css/mapp.min.css">';
	echo '<style>#' . $atts[ 'map_id' ] . '{width:1260px;height:500px}a.mapp-logo{display: none !important}</style>';
	echo '<link rel="stylesheet" href="' . $url . '/css/fa/style.css" data-locale="true">';
	echo '<script src="' . $url . '/js/mapp.env.js"></script>';
	echo '<script src="' . $url . '/js/mapp.min.js"></script>';

	echo '<script>jQuery( function( $ ) {setTimeout( function() {';

	echo 'var app = new Mapp({';
	echo 'element: "#' . $atts[ 'map_id' ] . '",';
	echo 'presets: {zoom: ' . $atts[ 'zoom' ] . '},';
	echo 'locale: "fa",';
	echo 'apiKey: "' . $atts[ 'api_key' ] . '"';
	echo '});';

	if ( $atts[ 'dark_mode' ] ) {

		echo 'app.addVectorLayers({base:{default:{style:"https://map.ir/vector/styles/main/mapir-style-dark.json"}}});';

	} else {

		echo 'app.addLayers();';

	}

	if ( $atts[ 'gray_mode' ] ) {

		echo 'app.addVectorLayers({base: {default: {style: "https://map.ir/vector/styles/main/mapir-xyz-light-style.json"}}});';

	}

	if ( $atts[ 'fullscreen' ] ) {
		echo 'app.addFullscreen();';
	}

	if ( $atts[ 'zoom_controls' ] ) {
		echo 'app.addZoomControls();';
	}

	if ( $atts[ 'my_location' ] ) {
		echo 'app.addGeolocation();';
	}

	if ( $atts[ 'right_click' ] ) {
		echo 'app.addContextmenu({here: true,distance: true,area: true,copy: true,static: true});';
	}

	if ( $atts[ 'marker' ] ) {
		echo 'app.addContextmenu({here: true,distance: true,area: true,copy: true,static: true});';
	}

	echo 'var marker = app.addMarker({';
	echo 'name: "marker",';
	echo 'latlng: {';
	echo 'lat: ' . $atts[ 'lat' ] . ',';
	echo 'lng: ' . $atts[ 'lng' ];
	echo '},';

	if ( $atts[ 'marker' ] ) {

		$image_id = attachment_url_to_postid( $atts[ 'marker' ] );

		$width = $height = '20';

		if ( $image_id ) {

			$image_sizes = wp_get_attachment_image_src( $image_id, 'full' );

			if ( $image_sizes ) {
				$width = $image_sizes[1];
				$height = $image_sizes[2];
			}
		}

		echo 'icon: {iconUrl:"' . $atts[ 'marker' ] . '",iconSize: [' . $width . ', ' . $height . '],iconAnchor: [' . ( $width / 2 ) . ', ' . $height . ']},';

	} else {

		echo 'icon: app.icons.red,';

	}

	echo 'popup: ' . ( $atts[ 'marker_title' ] ? 'true' : 'false ') . ',';
	echo 'pan: true';
	echo '});';

	if ( $atts[ 'marker_title' ] ) {

		echo 'var popup = app.generatePopupHtml({';
		echo 'title: {i18n: "' . $atts[ 'marker_title' ] . '"},';
		echo 'description: {i18n: "' . $atts[ 'marker_desc' ] . '"},';
		echo 'open: true,';
		echo '});';
		echo 'marker.bindPopup(popup);';

	}

	echo '}, 2000);});</script>';

	echo '<div id="' . $atts[ 'map_id' ] . '" class="codevz_mapp" style="background-color:#67676708"></div>';

	return ob_get_clean();

}
add_shortcode( 'codevz_persian_map', 'codevz_persian_map' );

// Register elementor persian map widget.
function codevz_persian_map_elementor_widget( $widgets_manager ) {

	require_once( Codevz_Plus::$dir . 'rtl-xtra/mapp/mapp-elementor.php' );

	$widgets_manager->register( new Xtra_Elementor_Widget_Mapp() );

}
add_action( 'elementor/widgets/register', 'codevz_persian_map_elementor_widget', 11 );

// Register WPBakery persian map widget.
function codevz_persian_map_wpbakery() {

	require_once( Codevz_Plus::$dir . 'rtl-xtra/mapp/mapp-wpbakery.php' );

	$mapp = new Codevz_WPBakery_Mapp();
	$mapp->in( true );

}
add_action( 'vc_before_init', 'codevz_persian_map_wpbakery' );
