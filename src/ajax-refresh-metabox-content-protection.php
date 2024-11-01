<?php
/**
 * Reload the Content Protection metabox content
 *
 * Refresh the metabox content on Gutenberg editor AJAX request.
 *
 * @since 1.2.0
 */

declare(strict_types=1);

namespace PTC_Trident;

defined( 'ABSPATH' ) || die();

global $ptc_trident;

$res['status'] = 'error';
$res['data'] = 'Missing expected data.';

if (
  isset( $_POST['ptc_trident_content_protection_nonce'] )
  && wp_verify_nonce( $_POST['ptc_trident_content_protection_nonce'], 'ptc_trident_content_protection' ) !== FALSE//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
  && isset( $_POST['post_id'] )
) {

  try {

    $the_post_id = (int) filter_var( wp_unslash( $_POST['post_id'] ), FILTER_SANITIZE_NUMBER_INT );
    $the_post = get_post( $the_post_id );
    if ( NULL === $the_post ) {
      throw new \Exception( "Post with id $the_post_id does not exist." );
    }

    ob_start();
    require $ptc_trident->plugin_path . 'view/html-metabox-content-protection.php';
    $contents = ob_get_contents();
    ob_end_clean();

    if (
      ! empty( $contents )
      && $res['status'] === 'success'
    ) {
      $res['data'] = $contents;
    } else {
      throw new \Exception( 'There was an issue retrieving the updated content.' );
    }

  } catch ( \Exception $e ) {
    $res['status'] = 'error';
    $res['data'] = $e->getMessage();
  }

}

echo json_encode( $res );
wp_die();
