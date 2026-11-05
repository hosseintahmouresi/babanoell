<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.
/**
 *
 * Setup Framework Class
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if( ! class_exists( 'Codevz_Framework' ) ) {

  class Codevz_Framework {

    /**
     *
     * instance
     * @access private
     * @var class
     *
     */
    private static $instance = null;

    public function __construct() {

      $this->constants();
      $this->includes();

    }

    // instance
    public static function instance() {
      if ( is_null( self::$instance ) ) {
        self::$instance = new self();
      }
      return self::$instance;
    }

    public static function locate_template( $template, $load = true ) {

      $located  = '';
      $override = apply_filters( 'codevz/override/framework', 'codevz-override' );

      if( file_exists( get_stylesheet_directory() .'/'. $override .'/'. $template ) ) {
        $located = get_stylesheet_directory() .'/'. $override .'/'. $template;
      } elseif ( file_exists( get_template_directory() .'/'. $override .'/'. $template ) ) {
        $located = get_template_directory() .'/'. $override .'/'. $template;
      } elseif ( file_exists( CODEVZ_FRAMEWORK_DIR .'/'. $template ) ) {
        $located = CODEVZ_FRAMEWORK_DIR .'/'. $template;
      }

      if( $load && ! empty( $located ) ) {

        global $wp_query;

        if( is_object( $wp_query ) && function_exists( 'load_template' ) ) {
          load_template( $located, true );
        } else {
          require_once( $located );
        }

      }

      if( ! $load ) {
        return CODEVZ_FRAMEWORK_DIR .'/'. $template;
      }

    }

    // Define constants
    public function constants() {
      define( 'CODEVZ_FRAMEWORK_DIR', Codevz_Plus::$dir . 'admin' );
      define( 'CODEVZ_FRAMEWORK_URL', Codevz_Plus::$url . 'admin' );
    }

    // Includes framework files
    public function includes() {

      // includes helpers
      $this->locate_template( 'functions/fallback.php' );
      $this->locate_template( 'functions/helpers.php' );
      $this->locate_template( 'functions/actions.php' );
      $this->locate_template( 'functions/enqueue.php' );

      // includes classes
      $this->locate_template( 'classes/abstract.class.php' );
      $this->locate_template( 'classes/fields.class.php' );
      $this->locate_template( 'classes/framework.class.php' );
      $this->locate_template( 'classes/metabox.class.php' );
      $this->locate_template( 'classes/taxonomy.class.php' );
      $this->locate_template( 'classes/customize.class.php' );

      do_action( 'codevz/includes' );

    }

  }

  Codevz_Framework::instance();
}
