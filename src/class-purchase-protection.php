<?php
/**
 * Purchase Protection class
 *
 * Static class responsible for content protection features that are powered
 * by WooCommerce.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace PTC_Trident;

defined( 'ABSPATH' ) || die();

if ( ! class_exists( __NAMESPACE__ . '\Purchase_Protection' ) ) {
  /**
   * Content protection features integrated with WooCommerce.
   */
  class Purchase_Protection {

    /**
     * Checks if WooCommerce is present and safe to use.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    static function is_woocommerce_loaded() : bool {

      if (
        class_exists( 'WooCommerce' )
        && function_exists( 'wc_get_is_paid_statuses' )
      ) {
        return TRUE;
      }

      return FALSE;

    }

    /**
     * Gets an array of all published product posts.
     *
     * @since 1.0.0
     *
     * @return \WP_Post[] The product data.
     */
    static function get_all_products() : array {

      return get_posts( [
        'post_type' => 'product',
        'numberposts' => -1,
        'post_status' => 'any',
        'orderby' => 'post_title',
      ] );

    }

    /**
     * Counts a customer's (by ID or email or both) total purchased, returned,
     * or owned quantity of a product. If a user ID is used in the search, then
     * that user's email will also be used in the search.
     *
     * @since 1.0.0
     *
     * @param int $product_id The product to count. Variation ID also works.
     *
     * @param string $qty_type Optional. The type of quantity to return:
     * 'purchased', 'returned', or 'owned'. Default 'owned'.
     *
     * @param int $user_id Optional. The customer to check. Set to 0 to not
     * search by a user ID. Default -1 to use the current user's ID.
     *
     * @param string $customer_email Optional. An additional customer email to
     * check. Default '' to only use the customer's user email.
     *
     * @return int The product quantity. Returns -1 if an error occurred.
     */
    static function get_customer_product_quantity( int $product_id, string $qty_type = 'owned', int $user_id = -1, string $customer_email = '' ) : int {

      if (
        self::is_woocommerce_loaded() === FALSE
      ) {
        return -1;
      }

      if ( $user_id < -1 ) {
        return -1;
      }

      if ( $product_id <= 0 ) {
        return -1;
      }

      if ( $user_id === -1 ) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
      }

      $customer_data = [];

      if ( $user_id > 0 ) {
        $customer_data[] = $user_id;
        $user = get_user_by( 'id', $user_id );
        if ( isset( $user->user_email ) ) {
          $customer_data[] = $user->user_email;
        }
      }

      if ( ! empty( $customer_email ) ) {
        $customer_email = filter_var( $customer_email, FILTER_SANITIZE_EMAIL );
        if ( is_email( $customer_email ) ) {
          $customer_data[] = $customer_email;
        }
      }

      $customer_data = array_map( 'esc_sql', array_filter( array_unique( $customer_data ) ) );
      if ( count( $customer_data ) === 0 ) {
        return -1;
      }

      $order_statuses   = array_map( 'esc_sql', wc_get_is_paid_statuses() );
      $order_statuses[] = 'refunded';

      global $wpdb;

      /* Purchased Qty */

      if ( $qty_type !== 'returned' ) {

        $purchased_qty = $wpdb->get_var(
          "
          SELECT
              SUM(product_qty.meta_value)
          FROM
              {$wpdb->posts} AS p
          INNER JOIN {$wpdb->postmeta} AS pm
          ON
              p.ID = pm.post_id
          INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i
          ON
              p.ID = i.order_id
          INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im
          ON
              i.order_item_id = im.order_item_id
          INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS product_qty
          ON
              im.order_item_id = product_qty.order_item_id
          WHERE
              p.post_status IN ( 'wc-" . implode( "','wc-", $order_statuses ) . "' )
              AND p.post_type = 'shop_order'
              AND pm.meta_key IN ( '_billing_email', '_customer_user' )
              AND pm.meta_value IN ( '" . implode( "','", $customer_data ) . "' )
              AND im.meta_key IN( '_product_id', '_variation_id' )
              AND im.meta_value = {$product_id}
              AND product_qty.meta_key = '_qty'
          "
        ); // WPCS: unprepared SQL ok.

        if ( NULL === $purchased_qty ) {
          $purchased_qty = 0;
        }

        if ( $qty_type === 'purchased' ) {
          return (int) $purchased_qty;
        }

      }

      /* Returned Qty */

      $returned_qty = $wpdb->get_var(
        "
        SELECT
            SUM(refund_qty.meta_value)
        FROM
            {$wpdb->posts} AS p
        INNER JOIN {$wpdb->postmeta} AS pm
        ON
            p.ID = pm.post_id
        INNER JOIN {$wpdb->posts} AS refund_orders
        ON
            p.ID = refund_orders.post_parent
        INNER JOIN {$wpdb->prefix}woocommerce_order_items AS refund_items
        ON
            refund_orders.ID = refund_items.order_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS refund_itemmeta
        ON
            refund_items.order_item_id = refund_itemmeta.order_item_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS refund_qty
        ON
            refund_itemmeta.order_item_id = refund_qty.order_item_id
        WHERE
            p.post_status IN ( 'wc-" . implode( "','wc-", $order_statuses ) . "' )
            AND p.post_type = 'shop_order'
            AND pm.meta_key IN ( '_billing_email', '_customer_user' )
            AND pm.meta_value IN ( '" . implode( "','", $customer_data ) . "' )
            AND refund_itemmeta.meta_key IN ( '_product_id', '_variation_id' )
            AND refund_itemmeta.meta_value = {$product_id}
            AND refund_qty.meta_key = '_qty'
        "
      ); // WPCS: unprepared SQL ok.

      if ( NULL === $returned_qty ) {
        $returned_qty = 0;
      }

      if ( $qty_type === 'returned' ) {
        return (int) absint( $returned_qty );
      }

      return (int) $purchased_qty + $returned_qty;

    }//end get_customer_product_quantity()

    /**
     * Checks if a customer owns any of the provided products.
     *
     * @since 1.0.0
     *
     * @uses \PTC_Trident\Purchase_Protection::get_customer_product_quantity()
     *
     * @param int[] $product_ids The product or variation IDs to check.
     *
     * @param int $user_id Optional. The customer to check. Set to 0 to not
     * search by a user ID. Default -1 to use the current user's ID.
     *
     * @param string $customer_email Optional. An additional customer email to
     * check. Default '' to only use the customer's user email.
     *
     * @return bool If the customer owns at least one of the provided products.
     */
    static function customer_owns_any_product( array $product_ids, int $user_id = -1, string $customer_email = '' ) : bool {

      if ( empty( $product_ids ) ) {
        return TRUE;
      }

      foreach ( $product_ids as $product_id ) {

        if ( ! is_numeric( $product_id ) ) {
          continue;
        }

        if ( self::get_customer_product_quantity( (int) $product_id, 'owned', $user_id, $customer_email ) > 0 ) {
          return TRUE;
        }

      }

      return FALSE;

    }

    /**
     * Checks if a customer owns all of the provided products.
     *
     * @since 1.0.0
     *
     * @uses \PTC_Trident\Purchase_Protection::get_customer_product_quantity()
     *
     * @param int[] $product_ids The product or variation IDs to check.
     *
     * @param int $user_id Optional. The customer to check. Set to 0 to not
     * search by a user ID. Default -1 to use the current user's ID.
     *
     * @param string $customer_email Optional. An additional customer email to
     * check. Default '' to only use the customer's user email.
     *
     * @return bool If the customer owns every one of the provided products.
     */
    static function customer_owns_all_products( array $product_ids, int $user_id = -1, string $customer_email = '' ) : bool {

      if ( empty( $product_ids ) ) {
        return TRUE;
      }

      foreach ( $product_ids as $product_id ) {

        if ( ! is_numeric( $product_id ) ) {
          continue;
        }

        if ( self::get_customer_product_quantity( (int) $product_id, 'owned', $user_id, $customer_email ) > 0 ) {
          continue;
        } else {
          return FALSE;
        }

      }

      return TRUE;

    }

  }//end class

}//end if class_exists
