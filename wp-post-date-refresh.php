<?php
/**
 * Plugin Name: WP Post Date Refresh
 * Description: Adds a small editor meta box for refreshing post and page dates to the current site time, with an optional hours offset.
 * Version: 1.0.0
 * Author: ildani.dev
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * Text Domain: wp-post-date-refresh
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package WPPostDateRefresh
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quick Date Refresh admin functionality.
 */
final class WP_Post_Date_Refresh {
	const VERSION          = '1.0.0';
	const AJAX_ACTION      = 'wp_post_date_refresh_update_date';
	const NONCE_ACTION     = 'wp_post_date_refresh_update_date';
	const MAX_HOURS_OFFSET = 87600; // 10 years.

	/**
	 * Post types supported by this plugin.
	 *
	 * @var string[]
	 */
	private $post_types = array( 'post', 'page' );

	/**
	 * Register admin hooks.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_update' ) );
	}

	/**
	 * Add the meta box to posts and pages.
	 */
	public function add_meta_box() {
		foreach ( $this->post_types as $post_type ) {
			add_meta_box(
				'quick-date-refresh',
				__( 'Quick Date Refresh', 'wp-post-date-refresh' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Enqueue the admin script only on supported edit screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->post_types, true ) ) {
			return;
		}

		wp_enqueue_script(
			'quick-date-refresh-admin',
			plugin_dir_url( __FILE__ ) . 'assets/admin.js',
			array(),
			self::VERSION,
			true
		);

		wp_localize_script(
			'quick-date-refresh-admin',
			'WPPostDateRefresh',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'i18n'    => array(
					'updating' => __( 'Updating date...', 'wp-post-date-refresh' ),
					'error'    => __( 'Unable to update the date. Please try again.', 'wp-post-date-refresh' ),
				),
			)
		);
	}

	/**
	 * Render the Quick Date Refresh meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		?>
		<div class="quick-date-refresh-box">
			<?php wp_nonce_field( self::NONCE_ACTION, 'quick_date_refresh_nonce' ); ?>
			<p>
				<label for="quick-date-refresh-hours">
					<?php esc_html_e( 'Hours offset', 'wp-post-date-refresh' ); ?>
				</label>
				<input
					type="number"
					id="quick-date-refresh-hours"
					class="widefat"
					name="quick_date_refresh_hours"
					value="0"
					min="0"
					step="1"
					inputmode="numeric"
				/>
			</p>
			<p class="description">
				<?php esc_html_e( 'Use 0 for current time, or enter a number to set the date to now minus X hours.', 'wp-post-date-refresh' ); ?>
			</p>
			<p>
				<button
					type="button"
					class="button button-primary quick-date-refresh-button"
					data-post-id="<?php echo esc_attr( $post->ID ); ?>"
				>
					<?php esc_html_e( 'Set date', 'wp-post-date-refresh' ); ?>
				</button>
			</p>
			<div class="quick-date-refresh-message" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Handle the AJAX request that updates the post date.
	 */
	public function handle_ajax_update() {
		if ( false === check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the editor and try again.', 'wp-post-date-refresh' ) ),
				403
			);
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Missing post ID.', 'wp-post-date-refresh' ) ),
				400
			);
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, $this->post_types, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'This content type is not supported.', 'wp-post-date-refresh' ) ),
				400
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to edit this content.', 'wp-post-date-refresh' ) ),
				403
			);
		}

		$hours_offset = $this->get_hours_offset_from_request();
		if ( is_wp_error( $hours_offset ) ) {
			wp_send_json_error(
				array( 'message' => $hours_offset->get_error_message() ),
				400
			);
		}

		$site_datetime = current_datetime();
		if ( $hours_offset > 0 ) {
			$site_datetime = $site_datetime->sub( new DateInterval( 'PT' . $hours_offset . 'H' ) );
		}

		$date_local = $site_datetime->format( 'Y-m-d H:i:s' );
		$date_gmt   = get_gmt_from_date( $date_local );

		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->posts,
			array(
				'post_date'         => $date_local,
				'post_date_gmt'     => $date_gmt,
				'post_modified'     => $date_local,
				'post_modified_gmt' => $date_gmt,
			),
			array( 'ID' => $post_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error(
				array( 'message' => __( 'Database update failed. Please try again.', 'wp-post-date-refresh' ) ),
				500
			);
		}

		clean_post_cache( $post_id );

		wp_send_json_success(
			array(
				'message'      => __( 'Date updated successfully.', 'wp-post-date-refresh' ),
				'postDate'     => $date_local,
				'postDateGmt'  => $date_gmt,
				'displayDate'  => wp_date(
					sprintf(
						'%s %s',
						get_option( 'date_format' ),
						get_option( 'time_format' )
					),
					$site_datetime->getTimestamp()
				),
				'hoursOffset'  => $hours_offset,
				'modifiedDate' => $date_local,
			)
		);
	}

	/**
	 * Read and validate the hours offset from the AJAX request.
	 *
	 * @return int|WP_Error
	 */
	private function get_hours_offset_from_request() {
		$raw_hours = isset( $_POST['hours_offset'] ) ? sanitize_text_field( wp_unslash( $_POST['hours_offset'] ) ) : '0';
		$raw_hours = trim( $raw_hours );

		if ( '' === $raw_hours ) {
			return 0;
		}

		if ( ! ctype_digit( $raw_hours ) ) {
			return new WP_Error(
				'quick_date_refresh_invalid_hours',
				__( 'Hours offset must be a whole number of 0 or greater.', 'wp-post-date-refresh' )
			);
		}

		$hours_offset = (int) $raw_hours;
		if ( $hours_offset > self::MAX_HOURS_OFFSET ) {
			return new WP_Error(
				'quick_date_refresh_hours_too_large',
				sprintf(
					/* translators: %d: Maximum allowed hours offset. */
					__( 'Hours offset must be %d or less.', 'wp-post-date-refresh' ),
					self::MAX_HOURS_OFFSET
				)
			);
		}

		return $hours_offset;
	}
}

new WP_Post_Date_Refresh();
