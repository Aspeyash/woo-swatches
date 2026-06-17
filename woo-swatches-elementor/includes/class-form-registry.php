<?php
/**
 * Form Registry — per-page canonical form coordination (v1.1.0).
 *
 * Coordinates which widget instance owns the canonical <form class="variations_form">
 * for a given product on the current request. Solves the v1.0.5 issue
 * (Bug B3) where Widget 1 and Widget 2 each emitted their own
 * variations_form, doubling the WC variation engine and the variation JSON.
 *
 * Lifecycle (per request):
 *   1. Each widget calls register_widget1() / claim_canonical() at render-start.
 *   2. The first Widget 2 instance for a product becomes CANONICAL.
 *   3. Subsequent Widget 2 instances for the same product become PRESENTER
 *      (button + quantity only, attached to the canonical form via the
 *      HTML5 form= attribute).
 *   4. Variation JSON is emitted exactly once per product per page —
 *      whichever widget reaches should_emit_json($pid) first wins.
 *
 * Scope:
 *   • Per request — properties reset on every page load (no persistence).
 *   • Per product — keyed by product post ID, so multi-product pages
 *     (Elementor product cards) get independent canonical forms.
 *
 * @package WooSwatchesElementor
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

class WSE_Form_Registry {

	/** Canonical claim status returned by claim_canonical(). */
	const STATUS_CANONICAL = 'CANONICAL';

	/** Presenter status — second/Nth Widget 2 instance for a product. */
	const STATUS_PRESENTER = 'PRESENTER';

	// ─────────────────────────────────────────────────────────────────────
	// Singleton
	// ─────────────────────────────────────────────────────────────────────

	protected static ?WSE_Form_Registry $instance = null;

	/**
	 * Per-product registry state for the current request.
	 *
	 * Shape:
	 *   [
	 *     {product_id} => [
	 *       'canonical_widget_id' => string|null,  // first canonical Widget 2 instance ID
	 *       'presenter_widget_ids' => string[],    // subsequent Widget 2 instances
	 *       'widget1_widget_ids'  => string[],     // every Widget 1 instance
	 *       'json_emitted'        => bool,
	 *     ]
	 *   ]
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $forms = array();

	public static function instance(): static {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	private function __construct() {}

	private function __clone() {}

	// ─────────────────────────────────────────────────────────────────────
	// Public API
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Records a Widget 1 (Swatches) render for a product.
	 *
	 * @param int    $product_id Product post ID.
	 * @param string $widget_id  Elementor widget instance ID.
	 */
	public function register_widget1( int $product_id, string $widget_id ): void {
		$this->ensure_product( $product_id );
		$this->forms[ $product_id ]['widget1_widget_ids'][] = $widget_id;
	}

	/**
	 * Attempts to claim canonical-form ownership for a product.
	 *
	 * Returns STATUS_CANONICAL the first time it's called for this product,
	 * STATUS_PRESENTER on every subsequent call (any later Widget 2 instance
	 * placed on the same page becomes a presenter — sticky bar / quick-view
	 * pattern).
	 *
	 * @param  int    $product_id Product post ID.
	 * @param  string $widget_id  Elementor widget instance ID.
	 * @return string             STATUS_CANONICAL or STATUS_PRESENTER.
	 */
	public function claim_canonical( int $product_id, string $widget_id ): string {
		$this->ensure_product( $product_id );

		// First Widget 2 to arrive becomes canonical.
		if ( null === $this->forms[ $product_id ]['canonical_widget_id'] ) {
			$this->forms[ $product_id ]['canonical_widget_id'] = $widget_id;
			return self::STATUS_CANONICAL;
		}

		// Anyone else is a presenter.
		$this->forms[ $product_id ]['presenter_widget_ids'][] = $widget_id;
		return self::STATUS_PRESENTER;
	}

	/**
	 * Returns true if the variation JSON has not been emitted yet for this
	 * product, and flips the flag so the next caller gets false.
	 *
	 * Use case: whichever widget renders first for product P emits the
	 * <script class="wse-variations-json"> payload once. JS reads from it
	 * during DOMReady reconciliation when wrapping standalone Widget 1
	 * swatches in a synthetic form (scenario c).
	 *
	 * @param int $product_id Product post ID.
	 */
	public function should_emit_json( int $product_id ): bool {
		$this->ensure_product( $product_id );

		if ( $this->forms[ $product_id ]['json_emitted'] ) {
			return false;
		}

		$this->forms[ $product_id ]['json_emitted'] = true;
		return true;
	}

	/**
	 * Returns the canonical form ID for a product.
	 *
	 * The same ID is generated regardless of registration state so PHP
	 * render templates and JS reconciliation share the exact same string.
	 *
	 * @param int $product_id Product post ID.
	 */
	public function get_form_id( int $product_id ): string {
		return 'wse-form-' . absint( $product_id );
	}

	// ─────────────────────────────────────────────────────────────────────
	// Read-only helpers (mostly for tests / debugging)
	// ─────────────────────────────────────────────────────────────────────

	/**
	 * Returns true if any Widget 2 has claimed canonical ownership for this product.
	 */
	public function is_canonical_claimed( int $product_id ): bool {
		return isset( $this->forms[ $product_id ]['canonical_widget_id'] )
			&& null !== $this->forms[ $product_id ]['canonical_widget_id'];
	}

	/**
	 * Returns true if at least one Widget 1 has registered for this product.
	 */
	public function has_widget1( int $product_id ): bool {
		return ! empty( $this->forms[ $product_id ]['widget1_widget_ids'] );
	}

	/**
	 * Returns the full registry state for the current request.
	 * Provided for tests / debugging only.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_state(): array {
		return $this->forms;
	}

	/**
	 * Resets the registry. Useful in unit tests; never called in normal flow.
	 */
	public function reset(): void {
		$this->forms = array();
	}

	// ─────────────────────────────────────────────────────────────────────
	// Internal
	// ─────────────────────────────────────────────────────────────────────

	private function ensure_product( int $product_id ): void {
		if ( ! isset( $this->forms[ $product_id ] ) ) {
			$this->forms[ $product_id ] = array(
				'canonical_widget_id'  => null,
				'presenter_widget_ids' => array(),
				'widget1_widget_ids'   => array(),
				'json_emitted'         => false,
			);
		}
	}
}
