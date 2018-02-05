<?php
namespace Elementor;

use Elementor\Core\Settings\Manager as SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor frontend class.
 *
 * Elementor frontend handler class is responsible for initializing Elementor in
 * the frontend.
 *
 * @since 1.0.0
 */
class Frontend {

	/**
	 * The priority of the content filter.
	 */
	const THE_CONTENT_FILTER_PRIORITY = 9;

	/**
	 * Post ID.
	 *
	 * Holds the ID of the current post.
	 *
	 * @access private
	 *
	 * @var int Post ID.
	 */
	private $post_id;

	/**
	 * Google fonts.
	 *
	 * Holds the list of google fonts that are being used in the current page.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @var array Google fonts. Default is an empty array.
	 */
	private $google_fonts = [];

	/**
	 * Google early access fonts.
	 *
	 * Holds the list of google early access fonts that are being used in the current page.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @var array Registered fonts. Default is an empty array.
	 */
	private $google_early_access_fonts = [];

	/**
	 * Registered fonts.
	 *
	 * Holds the list of enqueued fonts in the current page.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @var array Registered fonts. Default is an empty array.
	 */
	private $registered_fonts = [];

	/**
	 * Whether the front end mode is active.
	 *
	 * Used to determine whether we are in front end mode.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @var bool Whether the front end mode is active. Default is false.
	 */
	private $_is_frontend_mode = false;

	/**
	 * Whether the page is using Elementor.
	 *
	 * Used to determine whether the current page is using Elementor.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @var bool Whether Elementor is being used. Default is false.
	 */
	private $_has_elementor_in_page = false;

	/**
	 * Whether the excerpt is being called.
	 *
	 * Used to determine whether the call to `the_content()` came from `get_the_excerpt()`.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @var bool Whether the excerpt is being used. Default is false.
	 */
	private $_is_excerpt = false;

	/**
	 * Filters removed from the content.
	 *
	 * Hold the list of filters removed from `the_content()`. Used to hold the filters that
	 * conflicted with Elementor while Elementor process the content.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @var array Filters removed from the content. Default is an empty array.
	 */
	private $content_removed_filters = [];

	/**
	 * Init.
	 *
	 * Initialize Elementor front end. Hooks the needed actions to run Elementor
	 * in the front end, including script and style registration.
	 *
	 * Fired by `template_redirect` action.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function init() {
		if ( Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		add_filter( 'body_class', [ $this, 'body_class' ] );

		if ( Plugin::$instance->preview->is_preview_mode() ) {
			return;
		}

		$this->post_id = get_the_ID();
		$this->_is_frontend_mode = true;

		if ( is_singular() && Plugin::$instance->db->is_built_with_elementor( $this->post_id ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		}

		add_action( 'wp_head', [ $this, 'print_google_fonts' ] );
		add_action( 'wp_footer', [ $this, 'wp_footer' ] );

		// Add Edit with the Elementor in Admin Bar.
		add_action( 'admin_bar_menu', [ $this, 'add_menu_in_admin_bar' ], 200 );
	}

	/**
	 * Print elements.
	 *
	 * Used to generate the element final HTML on the frontend.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @param array $elements_data Element data.
	 */
	protected function _print_elements( $elements_data ) {
		foreach ( $elements_data as $element_data ) {
			$element = Plugin::$instance->elements_manager->create_element_instance( $element_data );

			if ( ! $element ) {
				continue;
			}

			$element->print_element();
		}
	}

	/**
	 * Body tag classes.
	 *
	 * Add new elementor classes to the body tag.
	 *
	 * Fired by `body_class` filter.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array $classes Optional. One or more classes to add to the body tag class list.
	 *                       Default is an empty array.
	 *
	 * @return array Body tag classes.
	 */
	public function body_class( $classes = [] ) {
		$classes[] = 'elementor-default';

		$id = get_the_ID();

		if ( is_singular() && Plugin::$instance->db->is_built_with_elementor( $id ) ) {
			$classes[] = 'elementor-page elementor-page-' . $id;
		}

		return $classes;
	}

	/**
	 * Add content filter.
	 *
	 * Remove plain content and render the content generated by Elementor.
	 *
	 * @since 1.8.0
	 * @access public
	 */
	public function add_content_filter() {
		add_filter( 'the_content', [ $this, 'apply_builder_in_content' ], self::THE_CONTENT_FILTER_PRIORITY );
	}

	/**
	 * Remove content filter.
	 *
	 * When the Elementor generated content rendered, we remove the filter to prevent multiple
	 * accuracies. This way we make sure Elementor renders the content only once.
	 *
	 * @since 1.8.0
	 * @access public
	 */
	public function remove_content_filter() {
		remove_filter( 'the_content', [ $this, 'apply_builder_in_content' ], self::THE_CONTENT_FILTER_PRIORITY );
	}

	/**
	 * Registers scripts.
	 *
	 * Registers all the frontend scripts.
	 *
	 * Fired by `wp_enqueue_scripts` action.
	 *
	 * @since 1.2.1
	 * @access public
	 */
	public function register_scripts() {
		/**
		 * Before frontend register scripts.
		 *
		 * Fires before Elementor frontend scripts are registered.
		 *
		 * @since 1.2.1
		 */
		do_action( 'elementor/frontend/before_register_scripts' );

		$suffix = Utils::is_script_debug() ? '' : '.min';

		wp_register_script(
			'elementor-waypoints',
			ELEMENTOR_ASSETS_URL . 'lib/waypoints/waypoints' . $suffix . '.js',
			[
				'jquery',
			],
			'4.0.2',
			true
		);

		wp_register_script(
			'flatpickr',
			ELEMENTOR_ASSETS_URL . 'lib/flatpickr/flatpickr' . $suffix . '.js',
			[
				'jquery',
			],
			'4.1.4',
			true
		);

		wp_register_script(
			'imagesloaded',
			ELEMENTOR_ASSETS_URL . 'lib/imagesloaded/imagesloaded' . $suffix . '.js',
			[
				'jquery',
			],
			'4.1.0',
			true
		);

		wp_register_script(
			'jquery-numerator',
			ELEMENTOR_ASSETS_URL . 'lib/jquery-numerator/jquery-numerator' . $suffix . '.js',
			[
				'jquery',
			],
			'0.2.1',
			true
		);

		wp_register_script(
			'jquery-swiper',
			ELEMENTOR_ASSETS_URL . 'lib/swiper/swiper.jquery' . $suffix . '.js',
			[
				'jquery',
			],
			'3.4.2',
			true
		);

		wp_register_script(
			'jquery-slick',
			ELEMENTOR_ASSETS_URL . 'lib/slick/slick' . $suffix . '.js',
			[
				'jquery',
			],
			'1.6.0',
			true
		);

		wp_register_script(
			'elementor-dialog',
			ELEMENTOR_ASSETS_URL . 'lib/dialog/dialog' . $suffix . '.js',
			[
				'jquery-ui-position',
			],
			'4.1.0',
			true
		);

		wp_register_script(
			'elementor-frontend',
			ELEMENTOR_ASSETS_URL . 'js/frontend' . $suffix . '.js',
			[
				'elementor-dialog',
				'elementor-waypoints',
				'jquery-swiper',
			],
			ELEMENTOR_VERSION,
			true
		);

		/**
		 * After frontend register scripts.
		 *
		 * Fires after Elementor frontend scripts are registered.
		 *
		 * @since 1.2.1
		 */
		do_action( 'elementor/frontend/after_register_scripts' );
	}

	/**
	 * Registers styles.
	 *
	 * Registers all the frontend styles.
	 *
	 * Fired by `wp_enqueue_scripts` action.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function register_styles() {
		/**
		 * Before frontend register styles.
		 *
		 * Fires before Elementor frontend styles are registered.
		 *
		 * @since 1.2.0
		 */
		do_action( 'elementor/frontend/before_register_styles' );

		$suffix = Utils::is_script_debug() ? '' : '.min';

		$direction_suffix = is_rtl() ? '-rtl' : '';

		wp_register_style(
			'elementor-icons',
			ELEMENTOR_ASSETS_URL . 'lib/eicons/css/elementor-icons' . $suffix . '.css',
			[],
			ELEMENTOR_VERSION
		);

		wp_register_style(
			'font-awesome',
			ELEMENTOR_ASSETS_URL . 'lib/font-awesome/css/font-awesome' . $suffix . '.css',
			[],
			'4.7.0'
		);

		wp_register_style(
			'elementor-animations',
			ELEMENTOR_ASSETS_URL . 'lib/animations/animations.min.css',
			[],
			ELEMENTOR_VERSION
		);

		wp_register_style(
			'flatpickr',
			ELEMENTOR_ASSETS_URL . 'lib/flatpickr/flatpickr' . $suffix . '.css',
			[],
			'4.1.4'
		);

		wp_register_style(
			'elementor-frontend',
			ELEMENTOR_ASSETS_URL . 'css/frontend' . $direction_suffix . $suffix . '.css',
			[],
			ELEMENTOR_VERSION
		);

		/**
		 * After frontend register styles.
		 *
		 * Fires after Elementor frontend styles are registered.
		 *
		 * @since 1.2.0
		 */
		do_action( 'elementor/frontend/after_register_styles' );
	}

	/**
	 * Enqueue scripts.
	 *
	 * Enqueue all the frontend scripts.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function enqueue_scripts() {
		/**
		 * Before frontend enqueue scripts.
		 *
		 * Fires before Elementor frontend scripts are enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'elementor/frontend/before_enqueue_scripts' );

		wp_enqueue_script( 'elementor-frontend' );

		$elementor_frontend_config = [
			'isEditMode' => Plugin::$instance->preview->is_preview_mode(),
			'settings' => SettingsManager::get_settings_frontend_config(),
			'is_rtl' => is_rtl(),
			'urls' => [
				'assets' => ELEMENTOR_ASSETS_URL,
			],
		];

		if ( is_singular() ) {
			$post = get_post();
			$elementor_frontend_config['post'] = [
				'id' => $post->ID,
				'title' => $post->post_title,
				'excerpt' => $post->post_excerpt,
			];
		} else {
			$elementor_frontend_config['post'] = [
				'id' => 0,
				'title' => wp_get_document_title(),
				'excerpt' => '',
			];
		}

		if ( Plugin::$instance->preview->is_preview_mode() ) {
			$elements_manager = Plugin::$instance->elements_manager;

			$elements_frontend_keys = [
				'section' => $elements_manager->get_element_types( 'section' )->get_frontend_settings_keys(),
				'column' => $elements_manager->get_element_types( 'column' )->get_frontend_settings_keys(),
			];

			$elements_frontend_keys += Plugin::$instance->widgets_manager->get_widgets_frontend_settings_keys();

			$elementor_frontend_config['elements'] = [
				'data' => (object) [],
				'editSettings' => (object) [],
				'keys' => $elements_frontend_keys,
			];
		}

		wp_localize_script( 'elementor-frontend', 'elementorFrontendConfig', $elementor_frontend_config );

		/**
		 * After frontend enqueue scripts.
		 *
		 * Fires after Elementor frontend scripts are enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'elementor/frontend/after_enqueue_scripts' );
	}

	/**
	 * Enqueue styles.
	 *
	 * Enqueue all the frontend styles.
	 *
	 * Fired by `wp_enqueue_scripts` action.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function enqueue_styles() {
		/**
		 * Before frontend enqueue styles.
		 *
		 * Fires before Elementor frontend styles are enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'elementor/frontend/before_enqueue_styles' );

		wp_enqueue_style( 'elementor-icons' );
		wp_enqueue_style( 'font-awesome' );
		wp_enqueue_style( 'elementor-animations' );
		wp_enqueue_style( 'elementor-frontend' );

		if ( ! Plugin::$instance->preview->is_preview_mode() ) {
			$this->parse_global_css_code();

			$css_file = new Post_CSS_File( get_the_ID() );
			$css_file->enqueue();
		}

		/**
		 * After frontend enqueue styles.
		 *
		 * Fires after Elementor frontend styles are enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'elementor/frontend/after_enqueue_styles' );
	}

	/**
	 * Elementor footer scripts and styles.
	 *
	 * Handle styles and scripts that are not printed in the header.
	 *
	 * Fired by `wp_footer` action.
	 *
	 * @since 1.0.11
	 * @access public
	 */
	public function wp_footer() {
		if ( ! $this->_has_elementor_in_page ) {
			return;
		}

		$this->enqueue_styles();
		$this->enqueue_scripts();

		$this->print_google_fonts();
	}

	/**
	 * Print Google fonts.
	 *
	 * Enqueue all the frontend Google fonts.
	 *
	 * Fired by `wp_head` action.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function print_google_fonts() {
		$print_google_fonts = true;

		/**
		 * Print frontend google fonts.
		 *
		 * Filters whether to enqueue Google fonts in the frontend.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $print_google_fonts Whether to enqueue Google fonts. Default is true.
		 */
		$print_google_fonts = apply_filters( 'elementor/frontend/print_google_fonts', $print_google_fonts );

		if ( ! $print_google_fonts ) {
			return;
		}

		// Print used fonts
		if ( ! empty( $this->google_fonts ) ) {
			foreach ( $this->google_fonts as &$font ) {
				$font = str_replace( ' ', '+', $font ) . ':100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic';
			}

			$fonts_url = sprintf( 'https://fonts.googleapis.com/css?family=%s', implode( rawurlencode( '|' ), $this->google_fonts ) );

			$subsets = [
				'ru_RU' => 'cyrillic',
				'bg_BG' => 'cyrillic',
				'he_IL' => 'hebrew',
				'el' => 'greek',
				'vi' => 'vietnamese',
				'uk' => 'cyrillic',
				'cs_CZ' => 'latin-ext',
				'ro_RO' => 'latin-ext',
				'pl_PL' => 'latin-ext',
			];
			$locale = get_locale();

			if ( isset( $subsets[ $locale ] ) ) {
				$fonts_url .= '&subset=' . $subsets[ $locale ];
			}

			echo '<link rel="stylesheet" type="text/css" href="' . $fonts_url . '">';
			$this->google_fonts = [];
		}

		if ( ! empty( $this->google_early_access_fonts ) ) {
			foreach ( $this->google_early_access_fonts as $current_font ) {
				printf( '<link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/earlyaccess/%s.css">', strtolower( str_replace( ' ', '', $current_font ) ) );
			}
			$this->google_early_access_fonts = [];
		}
	}

	/**
	 * Enqueue fonts.
	 *
	 * Enqueue all the frontend fonts.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function enqueue_font( $font ) {
		$font_type = Fonts::get_font_type( $font );
		$cache_id = $font_type . $font;

		if ( in_array( $cache_id, $this->registered_fonts ) ) {
			return;
		}

		switch ( $font_type ) {
			case Fonts::GOOGLE:
				if ( ! in_array( $font, $this->google_fonts ) ) {
					$this->google_fonts[] = $font;
				}
				break;

			case Fonts::EARLYACCESS:
				if ( ! in_array( $font, $this->google_early_access_fonts ) ) {
					$this->google_early_access_fonts[] = $font;
				}
				break;
		}

		$this->registered_fonts[] = $cache_id;
	}

	/**
	 * Parse global CSS.
	 *
	 * Enqueue the global CSS file.
	 *
	 * @since 1.2.0
	 * @access protected
	 */
	protected function parse_global_css_code() {
		$scheme_css_file = new Global_CSS_File();

		$scheme_css_file->enqueue();
	}

	/**
	 * Apply builder in content.
	 *
	 * Used to apply the Elementor page editor on the post content.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $content The post content.
	 *
	 * @return string The post content.
	 */
	public function apply_builder_in_content( $content ) {
		$this->restore_content_filters();

		if ( ! $this->_is_frontend_mode || $this->_is_excerpt ) {
			return $content;
		}

		// Remove the filter itself in order to allow other `the_content` in the elements
		$this->remove_content_filter();

		$post_id = get_the_ID();
		$builder_content = $this->get_builder_content( $post_id );

		if ( ! empty( $builder_content ) ) {
			$content = $builder_content;
			$this->remove_content_filters();
		}

		// Add the filter again for other `the_content` calls
		$this->add_content_filter();

		return $content;
	}

	/**
	 * Retrieve builder content.
	 *
	 * Used to render and return the post content with all the Elementor elements.
	 *
	 * Note that this method is an internal method, please use `get_builder_content_for_display()`.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param int  $post_id  The post ID.
	 * @param bool $with_css Optional. Whether to retrieve the content with CSS
	 *                       or not. Default is false.
	 *
	 * @return string The post content.
	 */
	public function get_builder_content( $post_id, $with_css = false ) {
		if ( post_password_required( $post_id ) ) {
			return '';
		}

		if ( ! Plugin::$instance->db->is_built_with_elementor( $post_id ) ) {
			return '';
		}

		if ( is_preview() ) {
			$preview_post = Utils::get_post_autosave( $post_id, get_current_user_id() );
			$status = DB::STATUS_DRAFT;
		} else {
			$preview_post = false;
			$status = DB::STATUS_PUBLISH;
		}

		$data = Plugin::$instance->db->get_plain_editor( $post_id, $status );

		/**
		 * Frontend builder content data.
		 *
		 * Filters the builder content in the frontend.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data    The builder content.
		 * @param int   $post_id The post ID.
		 */
		$data = apply_filters( 'elementor/frontend/builder_content_data', $data, $post_id );

		if ( empty( $data ) ) {
			return '';
		}

		if ( ! $this->_is_excerpt ) {
			if ( $preview_post ) {
				$css_file = new Post_Preview_CSS( $preview_post->ID );
			} else {
				$css_file = new Post_CSS_File( $post_id );
			}

			$css_file->enqueue();
		}

		ob_start();

		// Handle JS and Customizer requests, with CSS inline.
		if ( is_customize_preview() || Utils::is_ajax() ) {
			$with_css = true;
		}

		if ( ! empty( $css_file ) && $with_css ) {
			echo '<style>' . $css_file->get_css() . '</style>';
		}

		?>
		<div class="elementor elementor-<?php echo $post_id; ?>">
			<div class="elementor-inner">
				<div class="elementor-section-wrap">
					<?php $this->_print_elements( $data ); ?>
				</div>
			</div>
		</div>
		<?php
		$content = ob_get_clean();

		/**
		 * Frontend content.
		 *
		 * Filters the content in the frontend.
		 *
		 * @since 1.0.0
		 *
		 * @param string $content The content.
		 */
		$content = apply_filters( 'elementor/frontend/the_content', $content );

		if ( ! empty( $content ) ) {
			$this->_has_elementor_in_page = true;
		}

		return $content;
	}

	/**
	 * Add Elementor menu to admin bar.
	 *
	 * Add new admin bar item only on singular pages, to display a link that
	 * allows the user to edit with Elementor.
	 *
	 * Fired by `admin_bar_menu` action.
	 *
	 * @since 1.3.4
	 * @access public
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance, passed by reference.
	 */
	public function add_menu_in_admin_bar( \WP_Admin_Bar $wp_admin_bar ) {
		$post_id = get_the_ID();

		$is_builder_mode = is_singular() && User::is_current_user_can_edit( $post_id ) && Plugin::$instance->db->is_built_with_elementor( $post_id );

		if ( ! $is_builder_mode ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id' => 'elementor_edit_page',
			'title' => __( 'Edit with Elementor', 'elementor' ),
			'href' => Utils::get_edit_link( $post_id ),
		] );
	}

	/**
	 * Retrieve builder content for display.
	 *
	 * Used to render and return the post content with all the Elementor elements.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string The post content.
	 */
	public function get_builder_content_for_display( $post_id ) {
		if ( ! get_post( $post_id ) ) {
			return '';
		}

		$editor = Plugin::$instance->editor;

		// Avoid recursion
		if ( get_the_ID() === (int) $post_id ) {
			$content = '';
			if ( $editor->is_edit_mode() ) {
				$content = '<div class="elementor-alert elementor-alert-danger">' . __( 'Invalid Data: The Template ID cannot be the same as the currently edited template. Please choose a different one.', 'elementor' ) . '</div>';
			}

			return $content;
		}

		// Set edit mode as false, so don't render settings and etc. use the $is_edit_mode to indicate if we need the CSS inline
		$is_edit_mode = $editor->is_edit_mode();
		$editor->set_edit_mode( false );

		// Change the global post to current library post, so widgets can use `get_the_ID` and other post data
		Plugin::$instance->db->switch_to_post( $post_id );

		$content = $this->get_builder_content( $post_id, $is_edit_mode );

		Plugin::$instance->db->restore_current_post();

		// Restore edit mode state
		Plugin::$instance->editor->set_edit_mode( $is_edit_mode );

		return $content;
	}

	/**
	 * Start excerpt flag.
	 *
	 * Flags when `the_excerpt` is called. Used to avoid enqueueing CSS in the excerpt.
	 *
	 * @since 1.4.3
	 * @access public
	 *
	 * @param string $post_excerpt The post excerpt.
	 *
	 * @return string The post excerpt.
	 */
	public function start_excerpt_flag( $excerpt ) {
		$this->_is_excerpt = true;
		return $excerpt;
	}

	/**
	 * End excerpt flag.
	 *
	 * Flags when `the_excerpt` call ended.
	 *
	 * @since 1.4.3
	 * @access public
	 *
	 * @param string $post_excerpt The post excerpt.
	 *
	 * @return string The post excerpt.
	 */
	public function end_excerpt_flag( $excerpt ) {
		$this->_is_excerpt = false;
		return $excerpt;
	}

	/**
	 * Remove content filters.
	 *
	 * Remove WordPress default filters that conflicted with Elementor.
	 *
	 * @since 1.5.0
	 * @access public
	 */
	public function remove_content_filters() {
		$filters = [
			'wpautop',
			'shortcode_unautop',
			'wptexturize',
		];

		foreach ( $filters as $filter ) {
			// Check if another plugin/theme do not already removed the filter.
			if ( has_filter( 'the_content', $filter ) ) {
				remove_filter( 'the_content', $filter );
				$this->content_removed_filters[] = $filter;
			}
		}
	}

	/**
	 * Restore content filters.
	 *
	 * Restore removed WordPress filters that conflicted with Elementor.
	 *
	 * @since 1.5.0
	 * @access private
	 */
	private function restore_content_filters() {
		foreach ( $this->content_removed_filters as $filter ) {
			add_filter( 'the_content', $filter );
		}
		$this->content_removed_filters = [];
	}

	/**
	 * Front End constructor.
	 *
	 * Initializing Elementor front end. Make sure we are not in admin, not and
	 * redirect from old URL structure of Elementor editor.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function __construct() {
		// We don't need this class in admin side, but in AJAX requests.
		if ( is_admin() && ! Utils::is_ajax() ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'init' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_styles' ], 5 );

		$this->add_content_filter();

		// Hack to avoid enqueue post CSS while it's a `the_excerpt` call.
		add_filter( 'get_the_excerpt', [ $this, 'start_excerpt_flag' ], 1 );
		add_filter( 'get_the_excerpt', [ $this, 'end_excerpt_flag' ], 20 );
	}
}
