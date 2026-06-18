<?php
/**
 * Thumbnail Generator
 *
 * Provides programmatic regeneration of the wse_swatch image size (80×80
 * hard crop, registered in WSE_Plugin::register_image_sizes()) for all
 * media library images that have been assigned to swatch terms.
 *
 * Three entry points:
 *   1. AJAX  — single-attachment regen when a swatch image is first assigned
 *              via the admin term meta uploader (admin-term-meta.js calls
 *              wp_ajax_wse_regen_swatch_thumb after a successful upload).
 *   2. Admin — bulk regen page under Tools → Regen Swatch Thumbnails, with
 *              progress delivered via Server-Sent Events (SSE).
 *   3. WP-CLI — `wp wse regen-thumbs [--dry-run] [--batch=<n>]`
 *
 * @package WooSwatchesElementor
 */

defined( 'ABSPATH' ) || exit;

class WSE_Thumbnail_Generator {

	/** The image size name registered by WSE_Plugin::register_image_sizes(). */
	public const SIZE = 'wse_swatch';

	// ─────────────────────────────────────────────────────────────────────
	// Init
	// ─────────────────────────────────────────────────────────────────────

	public function __construct() {

		// AJAX: regen a single attachment when swatch image is assigned.
		add_action( 'wp_ajax_wse_regen_swatch_thumb', array( $this, 'ajax_regen_single' ) );

		// Admin page under Tools menu.
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );

		// SSE endpoint for the admin page progress stream.
		add_action( 'wp_ajax_wse_regen_thumbs_stream', array( $this, 'stream_regen' ) );

		// WP-CLI command.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wse regen-thumbs', array( $this, 'cli_regen' ) );
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Core — single attachment
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Regenerates all thumbnail sizes for one attachment using WordPress's
	 * native wp_generate_attachment_metadata().
	 *
	 * @param  int  $attachment_id
	 * @return bool True on success, false if the file cannot be found.
	 */
	public function regenerate_single( int $attachment_id ): bool {

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return false;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

		if ( is_wp_error( $metadata ) || empty( $metadata ) ) {
			return false;
		}

		return (bool) wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Core — batch
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Regenerates thumbnails for a batch of image attachments.
	 *
	 * Only processes attachments whose IDs are stored in wse_image term meta
	 * to avoid touching unrelated media.
	 *
	 * @param  int  $offset
	 * @param  int  $limit
	 * @return array{ processed: int, errors: int, ids: int[] }
	 */
	public function regenerate_batch( int $offset = 0, int $limit = 25 ): array {

		$ids = $this->get_swatch_attachment_ids( $offset, $limit );

		$processed = 0;
		$errors    = 0;

		foreach ( $ids as $id ) {
			if ( $this->regenerate_single( $id ) ) {
				++$processed;
			} else {
				++$errors;
			}
		}

		return array(
			'processed' => $processed,
			'errors'    => $errors,
			'ids'       => $ids,
		);
	}

	/**
	 * Returns the total count of unique swatch image attachment IDs.
	 *
	 * @return int
	 */
	public function get_total_count(): int {
		return count( $this->get_swatch_attachment_ids( 0, 0 ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 1. AJAX — single attachment
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * AJAX handler: regenerate one attachment.
	 * Called by admin-term-meta.js immediately after a swatch image is chosen
	 * so the wse_swatch thumbnail exists before the first frontend request.
	 */
	public function ajax_regen_single(): void {

		check_ajax_referer( 'wse_term_meta_nonce', 'security' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woo-swatches-elementor' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'woo-swatches-elementor' ) ) );
		}

		if ( ! $this->regenerate_single( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Thumbnail regeneration failed.', 'woo-swatches-elementor' ) ) );
		}

		$thumb_url = wp_get_attachment_image_url( $attachment_id, self::SIZE );

		wp_send_json_success( array( 'thumb_url' => $thumb_url ) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// 2. Admin page
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Registers a Tools → Regen Swatch Thumbnails admin page.
	 */
	public function register_admin_page(): void {

		add_management_page(
			esc_html__( 'Regen Swatch Thumbnails', 'woo-swatches-elementor' ),
			esc_html__( 'Regen Swatch Thumbs', 'woo-swatches-elementor' ),
			'manage_woocommerce',
			'wse-regen-thumbs',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Renders the regeneration admin page.
	 */
	public function render_admin_page(): void {

		$total = $this->get_total_count();
		$nonce = wp_create_nonce( 'wse_regen_thumbs' );
		?>
		<div class="wrap" id="wse-regen-page">
			<h1><?php esc_html_e( 'Regenerate Swatch Thumbnails', 'woo-swatches-elementor' ); ?></h1>

			<p>
				<?php
				printf(
					/* translators: %1$s: size name, %2$dx%2$d: dimensions */
					esc_html__( 'This will regenerate the %1$s (80×80 px) size for all %3$d swatch images in the media library.', 'woo-swatches-elementor' ),
					'<code>' . esc_html( self::SIZE ) . '</code>',
					80,
					(int) $total
				);
				?>
			</p>

			<?php if ( 0 === $total ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'No swatch images found in the media library.', 'woo-swatches-elementor' ); ?></p>
				</div>
			<?php else : ?>
				<div id="wse-regen-progress" style="display:none;">
					<progress id="wse-regen-bar" value="0" max="<?php echo esc_attr( $total ); ?>" style="width:400px;"></progress>
					<span id="wse-regen-status">0 / <?php echo esc_html( $total ); ?></span>
				</div>
				<div id="wse-regen-log" style="height:160px;overflow-y:auto;background:#f6f7f7;padding:8px;margin-top:8px;font-family:monospace;font-size:12px;display:none;"></div>

				<p>
					<button id="wse-regen-start-btn" class="button button-primary"
					        data-nonce="<?php echo esc_attr( $nonce ); ?>"
					        data-total="<?php echo esc_attr( $total ); ?>">
						<?php esc_html_e( 'Start Regeneration', 'woo-swatches-elementor' ); ?>
					</button>
				</p>

				<script>
				( function ( $ ) {
					$( '#wse-regen-start-btn' ).on( 'click', function () {
						var $btn   = $( this ).prop( 'disabled', true );
						var total  = parseInt( $btn.data( 'total' ), 10 );
						var nonce  = $btn.data( 'nonce' );
						var $bar   = $( '#wse-regen-bar' );
						var $stat  = $( '#wse-regen-status' );
						var $log   = $( '#wse-regen-log' );
						var done   = 0;
						var errors = 0;

						$( '#wse-regen-progress, #wse-regen-log' ).show();

						var url = ajaxurl + '?action=wse_regen_thumbs_stream&security=' + encodeURIComponent( nonce );
						var es  = new EventSource( url );

						es.addEventListener( 'progress', function ( e ) {
							var d = JSON.parse( e.data );
							done   += d.processed;
							errors += d.errors;
							$bar.val( done );
							$stat.text( done + ' / ' + total + ( errors ? ' (' + errors + ' errors)' : '' ) );
							$log.append( '<div>' + $( '<span>' ).text( d.file ).html() + ( d.ok ? ' ✓' : ' ✗' ) + '</div>' );
							$log.scrollTop( $log[ 0 ].scrollHeight );
						} );

						es.addEventListener( 'done', function () {
							es.close();
							$stat.text( '<?php echo esc_js( __( 'Done!', 'woo-swatches-elementor' ) ); ?> ' + done + ' / ' + total );
							$btn.prop( 'disabled', false );
						} );

						es.onerror = function () {
							es.close();
							$btn.prop( 'disabled', false );
						};
					} );
				} )( jQuery );
				</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * SSE endpoint: streams regeneration progress to the admin page.
	 */
	public function stream_regen(): void {

		check_ajax_referer( 'wse_regen_thumbs', 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( '', 403 );
		}

		// Prevent output buffering and timeouts.
		@set_time_limit( 0 );     // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@ob_end_clean();           // phpcs:ignore WordPress.PHP.NoSilencedErrors
		ini_set( 'output_buffering', 'off' ); // phpcs:ignore WordPress.PHP.IniSet

		header( 'Content-Type: text/event-stream; charset=UTF-8' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );

		$ids    = $this->get_swatch_attachment_ids( 0, 0 );
		$batch  = 5;
		$chunks = array_chunk( $ids, $batch );

		foreach ( $chunks as $chunk ) {
			$processed = 0;
			$errors    = 0;
			$file      = '';

			foreach ( $chunk as $id ) {
				$f   = get_attached_file( $id );
				$ok  = $this->regenerate_single( $id );
				$file = $f ? basename( $f ) : "ID:{$id}";
				$ok ? ++$processed : ++$errors;

				echo 'event: progress' . "\n";
				echo 'data: ' . wp_json_encode( array(
					'processed' => 1,
					'errors'    => $ok ? 0 : 1,
					'file'      => $file,
					'ok'        => $ok,
				) ) . "\n\n";
			}

			// phpcs:ignore WordPress.XSS.EscapeOutput
			ob_flush();
			flush();
		}

		echo "event: done\n";
		echo "data: {}\n\n";
		ob_flush();
		flush();
		exit;
	}

	// ─────────────────────────────────────────────────────────────────────
	// 3. WP-CLI
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * WP-CLI command: regenerate swatch thumbnails.
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<n>]
	 * : Number of images to process per batch. Default: 25.
	 *
	 * [--dry-run]
	 * : Count images without regenerating.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wse regen-thumbs
	 *     wp wse regen-thumbs --batch=50
	 *     wp wse regen-thumbs --dry-run
	 *
	 * @param  array $args
	 * @param  array $assoc_args
	 */
	public function cli_regen( array $args, array $assoc_args ): void {

		$batch   = isset( $assoc_args['batch'] ) ? max( 1, (int) $assoc_args['batch'] ) : 25;
		$dry_run = isset( $assoc_args['dry-run'] );
		$total   = $this->get_total_count();

		WP_CLI::log( sprintf( 'Found %d swatch images.', $total ) );

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run — no changes made.' );
			return;
		}

		if ( 0 === $total ) {
			WP_CLI::success( 'Nothing to do.' );
			return;
		}

		$offset    = 0;
		$processed = 0;
		$errors    = 0;

		$progress = WP_CLI\Utils\make_progress_bar( 'Regenerating', $total );

		do {
			$result     = $this->regenerate_batch( $offset, $batch );
			$processed += $result['processed'];
			$errors    += $result['errors'];
			$count      = count( $result['ids'] );
			$offset    += $batch;

			for ( $i = 0; $i < $count; $i++ ) {
				$progress->tick();
			}
		} while ( $count >= $batch );

		$progress->finish();

		WP_CLI::success( sprintf(
			'Done. Processed: %d | Errors: %d',
			$processed,
			$errors
		) );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Retrieves unique attachment IDs stored in wse_image term meta across
	 * all product attribute taxonomies.
	 *
	 * @param  int $offset 0 = all records (used for total count).
	 * @param  int $limit  0 = all records.
	 * @return int[]
	 */
	private function get_swatch_attachment_ids( int $offset = 0, int $limit = 25 ): array {

		global $wpdb;

		$sql = "SELECT DISTINCT CAST(meta_value AS UNSIGNED)
		        FROM {$wpdb->termmeta}
		        WHERE meta_key = 'wse_image'
		          AND meta_value > 0";

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_col( $sql );

		return array_map( 'intval', $results ?: array() );
	}
}
