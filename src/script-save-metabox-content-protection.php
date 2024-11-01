<?php
/**
 * Content Protection save settings
 *
 * Processes Content Protection post edit metabox settings.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace PTC_Trident;

defined( 'ABSPATH' ) || die();

global $ptc_trident;
require_once $ptc_trident->plugin_path . 'src/class-options.php';

if (
  isset( $_POST['ptc_trident_content_protection_nonce'] )
  && wp_verify_nonce( $_POST['ptc_trident_content_protection_nonce'], 'ptc_trident_content_protection' ) !== FALSE//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
  && isset( $_POST['post_ID'] )
) {

  $the_post_id = (int) Options::sanitize( 'id', $_POST['post_ID'] );//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
  if ( empty( $the_post_id ) || $the_post_id <= 0 ) {
    return;
  }

  if ( isset( $_POST['ptc_trident_conditions_options_descend'] ) ) {
    try {
      Options::save( Options::PROTECT_CHILDREN, $_POST['ptc_trident_conditions_options_descend'], FALSE, $the_post_id );
    } catch ( \Exception $e ) {
      /* Refused to save invalid option value */
    }
  } else {
    Options::delete( Options::PROTECT_CHILDREN, $the_post_id );
  }

  if ( isset( $_POST['ptc_trident_conditions_options_redirect'] ) ) {
    try {
      Options::save( Options::REDIRECT_URL, $_POST['ptc_trident_conditions_options_redirect'], FALSE, $the_post_id );
    } catch ( \Exception $e ) {
      /* Refused to save invalid option value */
    }
  }

  /* CHECK INHERITANCE SETTING BEFORE SAVING (OVERRIDE) CONDITIONS */
  if ( isset( $_POST['ptc_trident_conditions_inheritance'] ) ) {
    $inheritance_option = Options::sanitize( 'string', $_POST['ptc_trident_conditions_inheritance'] );//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    if ( 'inherit' === $inheritance_option ) {
      Options::delete( Options::REQUIRED_PRODUCT_IDS, $the_post_id );
      Options::delete( Options::REQUIRED_USER_STATE, $the_post_id );
      Options::delete( Options::PRODUCT_PROTECT_METHOD, $the_post_id );
      Options::delete( Options::PROTECT_CHILDREN, $the_post_id );
      try {
        Options::save( Options::OVERRIDE_INHERITANCE, 'no', FALSE, $the_post_id );
      } catch ( \Exception $e ) {
        /* Refused to save invalid option value */
      }
      return;
    } elseif ( 'override' === $inheritance_option ) {
      try {
        Options::save( Options::OVERRIDE_INHERITANCE, 'yes', FALSE, $the_post_id );
      } catch ( \Exception $e ) {
        /* Refused to save invalid option value */
      }
    }
  }

  if ( isset( $_POST['ptc_trident_product_conditions_method'] ) ) {
    try {
      Options::save( Options::PRODUCT_PROTECT_METHOD, $_POST['ptc_trident_product_conditions_method'], FALSE, $the_post_id );
    } catch ( \Exception $e ) {
      /* Refused to save invalid option value */
    }
  }

  if (
    isset( $_POST['ptc_trident_conditions_products'] )
    && is_array( $_POST['ptc_trident_conditions_products'] )
  ) {
    /* clear all previously required product ids so only new settings remain */
    Options::delete( Options::REQUIRED_PRODUCT_IDS, $the_post_id );
    foreach ( $_POST['ptc_trident_conditions_products'] as $product_id ) {
      try {
        Options::save( Options::REQUIRED_PRODUCT_IDS, $product_id, FALSE, $the_post_id );
      } catch ( \Exception $e ) {
        /* Refused to save invalid option value */
      }
    }//end foreach product
  } else {
    Options::delete( Options::REQUIRED_PRODUCT_IDS, $the_post_id );
  }

  if ( isset( $_POST['ptc_trident_conditions_user_state'] ) ) {
    try {
      Options::save( Options::REQUIRED_USER_STATE, $_POST['ptc_trident_conditions_user_state'], FALSE, $the_post_id );
    } catch ( \Exception $e ) {
      /* Refused to save invalid option value */
    }
  }

}
