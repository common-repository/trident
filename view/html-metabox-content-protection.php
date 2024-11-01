<?php
/**
 * Content Protection metabox content
 *
 * Displays content protection options in post edit metabox.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace PTC_Trident;

defined( 'ABSPATH' ) || die();

global $ptc_trident;
require_once $ptc_trident->plugin_path . 'src/class-purchase-protection.php';
require_once $ptc_trident->plugin_path . 'src/class-protected-post.php';

/* Use passed post if AJAX Gutenberg refresh, else use global $post */
if (
  isset( $the_post_id )
  && isset( $the_post )
  && isset( $res )
) {

  if (
    NULL === $the_post
  ) {
    $res['status'] = 'fail';
    return;
  }

  $res['status'] = 'success';

} else {

  global $post;
  $the_post = $post;

}

/* Metabox Content */
if ( isset( $the_post ) && is_a( $the_post, '\WP_Post' ) ) {

  $protected_post = new Protected_Post( $the_post );
  echo '<input type="hidden" name="ptc_trident_content_protection_nonce" value="' . esc_attr( wp_create_nonce( 'ptc_trident_content_protection' ) ) . '">';

  $usable_redirect_url = $protected_post->get_usable_redirect_url();
  $default_redirect_url = home_url();

  $is_inheriting = $protected_post->is_inheriting_conditions();
  $has_protective_ancestor = FALSE;

  $disable_conditions_html = '';

  $protective_ancestor = $protected_post->get_protector();
  if ( $protective_ancestor !== FALSE && is_a( $protective_ancestor, '\\' . Protected_Post::class ) ) {

    $has_protective_ancestor = TRUE;
    $default_redirect_url = $protective_ancestor->get_usable_redirect_url();

    if ( $is_inheriting ) {
      $disable_conditions_html = 'disabled="disabled"';
      /* is inheriting protective ancestor rules */
      $inheritance_overrides_button_label = 'Apply Overrides';
      $inheritance_note_head = 'Inheriting protection from:';
      $inheritance_input_value = 'inherit';
    } else {
      /* is overriding protective ancestor rules */
      $inheritance_overrides_button_label = 'Clear Overrides';
      $inheritance_note_head = 'Overriding protection from:';
      $inheritance_input_value = 'override';
    }
    ?>

    <header class="ptc-trident-protection-inheritance">
      <div>
        <p>
          <?php echo esc_html( $inheritance_note_head ); ?>
          <a href="<?php echo esc_url( get_edit_post_link( $protective_ancestor->post ) ); ?>"><?php echo esc_html( $protective_ancestor->post->post_title ); ?></a>
        <button class="ptc-trident-inheritance-toggle" type="button"><?php echo esc_html( $inheritance_overrides_button_label ); ?></button>
        <input type="hidden" name="ptc_trident_conditions_inheritance" value="<?php echo esc_attr( $inheritance_input_value ); ?>">
        </p>
      </div>
    </header>

    <?php
  }//end if protective ancestor

  if ( Purchase_Protection::is_woocommerce_loaded() ) {

    $all_products = Purchase_Protection::get_all_products();

    if ( is_array( $all_products ) && count( $all_products ) > 0 ) {

      $ancestor_conditions_method = ( $has_protective_ancestor && $protective_ancestor->product_protect_method === 'all' ) ? 'all' : 'any';
      $ancestor_product_setting = ( $has_protective_ancestor && is_array( $protective_ancestor->required_product_ids ) ) ? $protective_ancestor->required_product_ids : [];
      ?>

      <fieldset class="ptc-trident-conditions-products" <?php echo $disable_conditions_html;//phpcs:ignore ?> data-ancestor-value="<?php echo esc_attr( json_encode( $ancestor_product_setting ) ); ?>">

        <legend>Product Ownership</legend>

        <p>
          Visitor must own
          <select id="ptc-trident-conditions-method" name="ptc_trident_product_conditions_method" data-ancestor-value="<?php echo esc_attr( $ancestor_conditions_method ); ?>">
            <option value="any" <?php echo ( $protected_post->product_protect_method === 'any' ) ? 'selected="selected"' : '';//phpcs:ignore ?>>ANY</option>
            <option value="all" <?php echo ( $protected_post->product_protect_method === 'all' ) ? 'selected="selected"' : '';//phpcs:ignore ?>>ALL</option>
          </select>
        </p>

        <?php
        foreach ( $all_products as $product ) {
          $product_id = $product->ID;
          $product_title = $product->post_title;
          $checked_html = ( in_array( $product_id, $protected_post->required_product_ids ) ) ? 'checked="checked"' : '';
          ?>
          <div class="ptc-trident-conditions-product-row">
            <input id="ptc-trident-conditions-product-<?php echo esc_attr( $product_id ); ?>" type="checkbox" name="ptc_trident_conditions_products[]" value="<?php echo esc_attr( $product_id ); ?>"  <?php echo $checked_html;//phpcs:ignore ?>>
            <label for="ptc-trident-conditions-product-<?php echo esc_attr( $product_id ); ?>"><?php echo esc_html( $product_title ); ?></label>
          </div>
        <?php }//end foreach product ?>

      </fieldset>

      <?php
    } else {
      echo '<p class="ptc-trident-warning"><i class="fa fa-exclamation-triangle"></i>No WooCommerce products were found.</p>';
    }

  } else {
    echo '<p class="ptc-trident-error"><i class="fa fa-lock"></i>WooCommerce was not detected. Protection by product ownership is currently unavailable.</p>';
  }//end if woocommerce is loaded

  $user_state_suffixes = [ 'in', 'out', 'editor', 'any' ];
  $user_state_values = [ 'logged_in', 'logged_out', 'user_editor', 'user_any' ];
  $user_state_labels = [ 'Logged in', 'Logged out', 'Editors only', 'Any user' ];

  $ancestor_user_state = ( $has_protective_ancestor ) ? $protective_ancestor->required_user_state : 'user_any';
  ?>

  <fieldset class="ptc-trident-conditions-user-states" <?php echo $disable_conditions_html;//phpcs:ignore ?> data-ancestor-value="<?php echo esc_attr( $ancestor_user_state ); ?>">

    <legend>User States</legend>

    <?php
    foreach ( $user_state_values as $i => $user_state_value ) {
      $checked_html = ( $protected_post->required_user_state === $user_state_value ) ? 'checked="checked"' : '';
      ?>
      <div class="ptc-trident-conditions-user-state-row">
        <input id="ptc-trident-conditions-user-state-<?php echo esc_attr( $user_state_suffixes[ $i ] ); ?>" type="radio" name="ptc_trident_conditions_user_state" value="<?php echo esc_attr( $user_state_value ); ?>" <?php echo $checked_html;//phpcs:ignore ?>>
        <label for="ptc-trident-conditions-user-state-<?php echo esc_attr( $user_state_suffixes[ $i ] ); ?>"><?php echo esc_html( $user_state_labels[ $i ] ); ?></label>
      </div>
    <?php }//end foreach user state ?>

  </fieldset>

  <fieldset class="ptc-trident-conditions-options">

    <legend>Options</legend>

    <div class="ptc-trident-conditions-options-row-descend" <?php echo ( is_post_type_hierarchical( $the_post->post_type ) && ! $is_inheriting ) ? '' : 'style="display:none;"'; ?>>
      <input id="ptc-trident-conditions-options-descend" type="checkbox" name="ptc_trident_conditions_options_descend" value="yes" <?php echo ( $protected_post->protect_children ) ? 'checked="checked"' : ''; ?>>
      <label for="ptc-trident-conditions-options-descend">Apply protection to children?</label>
    </div>

    <div class="ptc-trident-conditions-options-row-redirect">
      <label for="ptc-trident-conditions-options-redirect">Redirect URL</label>
      <input id="ptc-trident-conditions-options-redirect" name="ptc_trident_conditions_options_redirect" type="url" placeholder="<?php echo esc_url( $default_redirect_url ); ?>" value="<?php echo esc_attr( $protected_post->inherited_redirect_url ? '' : $protected_post->redirect_url ); ?>">
      <p>Currently<?php echo $protected_post->inherited_redirect_url ? ' <span>(inherited)</span>' : '';//phpcs:ignore ?>: <a href="<?php echo esc_url( $usable_redirect_url ); ?>"><?php echo esc_html( $usable_redirect_url ); ?></a></p>
    </div>

  </fieldset>

  <?php
} else {
  /* ERROR STATE HANDLING */
  $request_uri = isset( $_SERVER['REQUEST_URI'] ) ?
                 filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL ) :
                 '[Not Set]';
  $http_referer = isset( $_SERVER['HTTP_REFERER'] ) ?
                  filter_var( wp_unslash( $_SERVER['HTTP_REFERER'] ), FILTER_SANITIZE_URL ) :
                  '[Not Set]';
  error_log( "Failed to identify post for content protection settings pane.\nURI: $request_uri\nREFERER: $http_referer" );
  return;
}
