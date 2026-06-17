<?php
/**
 * Add to Cart template — Variable product.
 *
 * Widget 2 for variable products. Outputs:
 *   1. A variations_form with hidden attribute <select> elements (NOT swatches).
 *      These selects are synced by add-to-cart.js from Widget 1's swatch
 *      selections, giving wc-add-to-cart-variation.js the values it needs.
 *   2. The exact woocommerce-variation wrapper elements that WC's JS targets
 *      to update price, stock status, and description (Gap 33).
 *   3. The quantity stepper and add-to-cart button.
 *
 * Hidden select bypass:
 *   We add 'wse_skip_renderer' filter before outputting hidden selects
 *   so WSE_Swatch_Renderer returns the native <select> instead of swatches.
 *   The selects are wrapped in .wse-hidden-selects (display:none) so they
 *   are invisible, but jQuery's $form.find() can still read/write them.
 *
 * Available variables:
 *   @var \WC_Product_Variable  $product   The variable product object.
 *   @var array                 $settings  Elementor widget settings from Widget 2.
 *
 * Gap 27 — All standard WC action hooks present.
 * Gap 33 — Exact WC JS class targets for price/availability/description.
 * Gap 37 — Variable product type routing from Widget 2.
 *
 * @package WooSwatchesElementor
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

<form class="variations_form cart wse-atc-form wse-variable-cart"
	  action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
	  method="post"
	  enctype="multipart/form-data"
	  data-product_id="<?php echo absint( $product_id ); ?>"
	  data-product_variations="<?php echo $variations_attr; // phpcs:ignore ?>">

	<?php do_action( 'woocommerce_before_variations_form' ); ?>

	<?php
	/**
	 * Hidden native <select> elements for each attribute.
	 *
	 * We use 'wse_skip_renderer' to ensure WSE_Swatch_Renderer returns the
	 * plain WC <select> HTML here (not swatches). These selects are:
	 *   - Invisible (parent has display:none)
	 *   - Synced by add-to-cart.js when Widget 1 swatch is clicked
	 *   - Read by wc-add-to-cart-variation.js to match a variation
	 *   - Submitted with the form to add the correct variation to cart
	 */
	?>
	<div class="wse-hidden-selects variations" style="display:none" aria-hidden="true">
		<?php
		// Bypass swatch rendering for these selects
		add_filter( 'wse_skip_renderer', '__return_true' );

		foreach ( $attributes as $attribute_name => $options ) :
			$selected = $default_attributes[ sanitize_title( $attribute_name ) ] ?? '';
			wc_dropdown_variation_attribute_options(
				array(
					'options'          => $options,
					'attribute'        => $attribute_name,
					'product'          => $product,
					'selected'         => $selected,
					'show_option_none' => esc_html__( 'Choose an option', 'woo-swatches-elementor' ),
				)
			);
		endforeach;

		// Re-enable swatch rendering
		remove_filter( 'wse_skip_renderer', '__return_true' );
		?>
	</div><!-- .wse-hidden-selects -->

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<?php
	/**
	 * Gap 33 — single_variation_wrap: exact class names WC's JS targets.
	 *
	 * wc-add-to-cart-variation.js injects content into these elements
	 * when a matching variation is found:
	 *   .woocommerce-variation-price        → variation price HTML
	 *   .woocommerce-variation-availability → in/out of stock text
	 *   .woocommerce-variation-description  → variation description
	 *
	 * The .woocommerce-variation-add-to-cart wrapper enables/disables
	 * the add-to-cart button based on variation availability.
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
					woocommerce_quantity_input(
						array(
							'min_value'   => apply_filters(
								'woocommerce_quantity_input_min',
								$product->get_min_purchase_quantity(),
								$product
							),
							'max_value'   => apply_filters(
								'woocommerce_quantity_input_max',
								$product->get_max_purchase_quantity(),
								$product
							),
							'input_value' => $default_qty,
							'step'        => apply_filters( 'woocommerce_quantity_input_step', 1, $product ),
						),
						$product
					);
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
			 * Required hidden fields for WC form submission.
			 * variation_id: populated by wc-add-to-cart-variation.js
			 *               when a variation is found.
			 */
			?>
			<input type="hidden" name="add-to-cart"   value="<?php echo absint( $product_id ); ?>"/>
			<input type="hidden" name="product_id"    value="<?php echo absint( $product_id ); ?>"/>
			<input type="hidden" name="variation_id"  class="variation_id" value=""/>

		</div><!-- .woocommerce-variation-add-to-cart -->

	</div><!-- .single_variation_wrap -->

	<?php do_action( 'woocommerce_after_variations_form' ); ?>

</form><!-- .wse-variable-cart -->

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
