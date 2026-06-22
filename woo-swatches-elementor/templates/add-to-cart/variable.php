<?php
/**
 * Add to Cart template — Variable product (CANONICAL — v1.1.0).
 *
 * Renders the single canonical <form id="wse-form-{P}"> for a variable
 * product. Only ONE canonical form per product per page exists, regardless
 * of how many Widget 2 instances are placed (presenter instances render
 * variable-presenter.php and target this form via the HTML5 form= attribute).
 *
 * Structure:
 *   <form id="wse-form-{P}" class="variations_form cart wse-canonical-form"
 *         data-product_variations="...JSON...">
 *
 *     <hidden <select> per attribute>      ← rendered via the swatch
 *                                             renderer with both emit
 *                                             flags ON; JS dedup removes
 *                                             the visible swatch UI when
 *                                             Widget 1 is also on page.
 *
 *     <single_variation_wrap>              ← WC's standard JS targets:
 *       .woocommerce-variation-price          variation-price HTML
 *       .woocommerce-variation-availability   in/out-of-stock text
 *       .woocommerce-variation-description    variation description
 *
 *     <quantity input> + <submit button>
 *     <hidden add-to-cart / variation_id fields>
 *   </form>
 *
 * v1.1.0 changes vs v1.0.5:
 *   • The wse_skip_renderer bypass is GONE — selects are now emitted by
 *     the renderer in normal mode (B9).
 *   • The form ID is `wse-form-{$product_id}` so presenter widgets and
 *     synthetic JS wrappers can target it via the HTML5 form= attribute (B3).
 *   • No second variations_form is rendered (B3).
 *
 * Available variables (extracted by widget render()):
 *   @var \WC_Product_Variable  $product   The variable product object.
 *   @var array                 $settings  Elementor widget settings.
 *   @var string                $form_id   Canonical form ID (wse-form-{P}).
 *   @var bool                  $presenter_mode Always false on this template.
 *
 * Gap 27 — All standard WC action hooks present.
 * Gap 33 — Exact WC JS class targets for price/availability/description.
 *
 * @package WooSwatchesElementor
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

$product_id           = $product->get_id();
$show_qty             = ( $settings['show_quantity'] ?? 'yes' ) === 'yes';
$default_qty          = absint( $settings['default_quantity'] ?? 1 );
$button_text          = ! empty( $settings['button_text'] )
	? esc_html( $settings['button_text'] )
	: esc_html( $product->single_add_to_cart_text() );
$attributes           = $product->get_variation_attributes();
$available_variations = $product->get_available_variations();
$variations_json      = wp_json_encode( $available_variations );
$variations_attr      = function_exists( 'wc_esc_json' )
	? wc_esc_json( $variations_json )
	: htmlspecialchars( $variations_json, ENT_QUOTES, 'UTF-8' );
$default_attributes   = $product->get_default_attributes();
?>

<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<form id="<?php echo esc_attr( $form_id ); ?>"
	  class="variations_form cart wse-canonical-form<?php echo ( ( $settings['show_inline_price'] ?? 'yes' ) === 'yes' ) ? '' : ' wse-no-inline-price'; ?>"
	  action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
	  method="post"
	  enctype="multipart/form-data"
	  data-product_id="<?php echo absint( $product_id ); ?>"
	  data-product_variations="<?php echo $variations_attr; // phpcs:ignore ?>">

	<?php do_action( 'woocommerce_before_variations_form' ); ?>

	<?php
	/**
	 * v1.1.0 — Hidden <select> elements per attribute.
	 *
	 * The swatch renderer is now driven by emit-flag filters:
	 *   wse_renderer_emit_select   = true  (default)
	 *   wse_renderer_emit_swatches = true  (default)
	 *
	 * When Widget 1 is also on the page, swatches.js at DOMReady detects
	 * the duplicate swatch UI and removes it from the canonical form,
	 * leaving only the hidden <select>. When Widget 1 is NOT on the page,
	 * the swatch UI rendered here becomes the user's swatch interface.
	 *
	 * No more wse_skip_renderer bypass — that was a brittle work-around
	 * tracked as B9 in the v1.0.5 issue list.
	 */
	?>
	<div class="wse-canonical-attrs">
		<?php
		foreach ( $attributes as $attribute_name => $options ) :
			$selected = $default_attributes[ sanitize_title( $attribute_name ) ] ?? '';
			wc_dropdown_variation_attribute_options(
				array(
					'options'          => $options,
					'attribute'        => $attribute_name,
					'product'          => $product,
					'selected'         => $selected,
					'show_option_none' => apply_filters(
						'wse_choose_option_placeholder',
						esc_html__( 'Choose an option', 'woo-swatches-elementor' )
					),
				)
			);
		endforeach;
		?>
	</div>

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<?php
	/**
	 * Gap 33 — single_variation_wrap: exact class names WC's JS targets.
	 * wc-add-to-cart-variation.js injects content into these elements
	 * when a matching variation is found.
	 */
	?>
	<div class="single_variation_wrap">

		<?php do_action( 'woocommerce_before_single_variation' ); ?>

		<div class="woocommerce-variation single_variation">
			<div class="woocommerce-variation-price"></div>
			<div class="woocommerce-variation-availability"></div>
			<div class="woocommerce-variation-description"></div>
		</div>

		<?php do_action( 'woocommerce_after_single_variation' ); ?>

		<div class="woocommerce-variation-add-to-cart variations_button">

			<div class="wse-qty-atc-row">

				<?php if ( $show_qty ) : ?>
				<div class="wse-qty-wrap">
					<?php do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>

					<?php
					/**
					 * v1.2.0 — Replaced woocommerce_quantity_input() with the
					 * shared stepper template. Same input name + classes
					 * (with the wse-canonical-qty marker preserved so the
					 * presenter quantity-sync JS still finds this input)
					 * so wc-add-to-cart-variation.js continues to read and
					 * write the value without modification.
					 */
					$args = array(
						'min'           => apply_filters(
							'woocommerce_quantity_input_min',
							$product->get_min_purchase_quantity(),
							$product
						),
						'max'           => apply_filters(
							'woocommerce_quantity_input_max',
							$product->get_max_purchase_quantity(),
							$product
						),
						'step'          => apply_filters( 'woocommerce_quantity_input_step', 1, $product ),
						'value'         => $default_qty,
						'input_name'    => 'quantity',
						'input_classes' => array( 'input-text', 'qty', 'text', 'wse-canonical-qty' ),
					);
					include WSE_PATH . 'templates/quantity-stepper.php';
					?>

					<?php do_action( 'woocommerce_after_add_to_cart_quantity' ); ?>
				</div>
				<?php endif; ?>

				<button type="submit"
				        class="single_add_to_cart_button button alt wse-atc-button"
				        aria-label="<?php echo esc_attr( $button_text ); ?>">
					<?php echo $button_text; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>

			</div><!-- .wse-qty-atc-row -->

			<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

			<?php
			/**
			 * v1.4.5 — Buy Now button (after Add to Cart button).
			 * Renders only when the "Show Buy Now Button" toggle is ON.
			 */
			$_show_buy_now = ( $settings['show_buy_now'] ?? 'no' ) === 'yes';
			if ( $_show_buy_now ) :
				$_buy_now_text = ! empty( $settings['buy_now_text'] )
					? esc_html( $settings['buy_now_text'] )
					: esc_html__( 'Buy Now', 'woo-swatches-elementor' );
				$_buy_now_fw   = ( $settings['buy_now_full_width'] ?? 'yes' ) === 'yes';
				$_buy_now_cls  = 'wse-buy-now-btn button';
				if ( $_buy_now_fw ) {
					$_buy_now_cls .= ' wse-buy-now-full-width';
				}
			?>
			<div class="wse-buy-now-wrap">
				<button type="button"
					class="<?php echo esc_attr( $_buy_now_cls ); ?>"
					data-product-id="<?php echo absint( $product_id ); ?>"
					data-product-type="variable"
					data-quantity="1"
					data-default-text="<?php echo esc_attr( $_buy_now_text ); ?>"
					disabled="disabled"
					aria-disabled="true"
					data-needs-options="1">
					<span class="wse-buy-now-text"><?php echo $_buy_now_text; // phpcs:ignore ?></span>
				</button>
				<span class="wse-buy-now-message" role="status" aria-live="polite"></span>
			</div>
			<?php endif; ?>

			<input type="hidden" name="add-to-cart"   value="<?php echo absint( $product_id ); ?>"/>
			<input type="hidden" name="product_id"    value="<?php echo absint( $product_id ); ?>"/>
			<input type="hidden" name="variation_id"  class="variation_id" value=""/>

		</div><!-- .woocommerce-variation-add-to-cart -->

	</div><!-- .single_variation_wrap -->

	<?php do_action( 'woocommerce_after_variations_form' ); ?>

</form><!-- #<?php echo esc_html( $form_id ); ?> -->

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
