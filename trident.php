<?php
/**
 * Trident
 *
 * @author            Michelle Blanchette
 * @copyright         2020 Michelle Blanchette
 * @license           GPL-3.0-or-later
 *
 * Plugin Name:       Trident
 * Description:       Simple, powerful content protection for your site with WooCommerce integration.
 * Version:           1.0.0
 * Requires at least: 4.7.1
 * Requires PHP:      7.0
 * Author:            Purple Turtle Creative
 * Author URI:        https://purpleturtlecreative.com/
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 */

/*
Trident is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, version 3 of the License.

Trident is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Trident. If not, see https://www.gnu.org/licenses/gpl-3.0.txt.
*/

defined( 'ABSPATH' ) || die();

if ( ! class_exists( '\PTC_Trident' ) ) {
  /**
   * Provides helper functions and information relevant to this plugin for use
   * in the global space.
   *
   * @since 1.0.0
   */
  class PTC_Trident {

    /**
     * This plugin's basename.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    public $plugin_title;

    /**
     * The full file path to this plugin's directory ending with a slash.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    public $plugin_path;

    /**
     * Sets plugin member variables.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    function __construct() {
      $this->plugin_title = plugin_basename( __FILE__ );
      $this->plugin_path = plugin_dir_path( __FILE__ );
    }

    /**
     * Hook code into WordPress.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    function register() {
      add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
      add_action( 'save_post', [ $this, 'save_post_meta' ] );
      add_action( 'wp_ajax_ptc_trident_refresh_content_protection', [ $this, 'content_protection_metabox_html_ajax_refresh' ] );
      add_action( 'template_redirect', [ $this, 'template_redirect' ] );
      add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts' ] );

    }

    /**
     * Add metaboxes.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    function add_meta_boxes() {
      add_meta_box(
        'ptc-trident_content-protection',
        'Content Protection',
        [ $this, 'content_protection_metabox_html' ],
        NULL,
        'side'
      );
    }

    /**
     * Content for the Content Protection metabox.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    function content_protection_metabox_html() {
      include_once $this->plugin_path . 'view/html-metabox-content-protection.php';
    }

    /**
     * Process settings for post metabox.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    function save_post_meta() {
      include_once $this->plugin_path . 'src/script-save-metabox-content-protection.php';
    }

    /**
     * AJAX handler for refreshing the Page Relatives metabox in Gutenberg.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    function content_protection_metabox_html_ajax_refresh() {
      require_once $this->plugin_path . 'src/ajax-refresh-metabox-content-protection.php';
    }

    /**
     * Redirect from post if visitor is prohibited.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    function template_redirect() {
      $the_post = get_post();
      if ( is_a( $the_post, '\WP_Post' ) ) {
        require_once $this->plugin_path . 'src/class-protected-post.php';
        ( new \PTC_Trident\Protected_Post( $the_post ) )->redirect_if_prohibited();
      }
    }

    /**
     * Register and enqueue plugin CSS and JS.
     *
     * @since 1.0.0
     *
     * @ignore
     */
    function register_scripts( $hook_suffix ) {

      wp_register_style(
        'fontawesome',
        'https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
        [],
        '4.7.0'
      );

      switch ( $hook_suffix ) {
        case 'post.php':
          wp_enqueue_script(
            'ptc-trident_metabox-content-protection-js',
            plugins_url( 'assets/js/metabox-content-protection.js', __FILE__ ),
            [ 'jquery' ],
            '0.0.0'
          );
          wp_localize_script(
            'ptc-trident_metabox-content-protection-js',
            'ptc_trident_content_protection',
            [
              'nonce' => wp_create_nonce( 'ptc_trident_content_protection' ),
            ]
          );
          wp_enqueue_style(
            'ptc-trident_metabox-content-protection-css',
            plugins_url( 'assets/css/metabox-content-protection.css', __FILE__ ),
            [ 'fontawesome' ],
            '0.0.0'
          );
          break;
      }

    }//end register_scripts()

  }//end class

  $ptc_trident = new PTC_Trident();
  $ptc_trident->register();

}//end if class_exists
