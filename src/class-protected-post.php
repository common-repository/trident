<?php
/**
 * Protected Post class
 *
 * Evaluates content protection settings and visitor access for a given post.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace PTC_Trident;

defined( 'ABSPATH' ) || die();

global $ptc_trident;
require_once $ptc_trident->plugin_path . 'src/class-purchase-protection.php';
require_once $ptc_trident->plugin_path . 'src/class-options.php';

if ( ! class_exists( __NAMESPACE__ . '\Protected_Post' ) ) {
  /**
   * Evaluates content protection settings and visitor access for a given post.
   */
  class Protected_Post {

    public $post = NULL;

    public $required_product_ids = [];
    public $product_protect_method = 'any';
    public $required_user_state = 'user_any';

    public $redirect_url = '';
    public $inherited_redirect_url = FALSE;

    public $override_inheritance = FALSE;

    public $protect_children = FALSE;

    public $inherited_parent = NULL;

    /**
     * Loads a post and its content protection settings.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post The post to evaluate for content protection.
     *
     * @param bool $allow_inheritance Optional. If to attempt loading inherited
     * protection settings from an ancestor post. Default TRUE.
     *
     * @throws \Exception Error code 400 if provided post is invalid.
     */
    function __construct( \WP_Post $post, bool $allow_inheritance = TRUE ) {

      $this->load_the_post( $post );

      $this->load_protection_conditions();
      $this->load_additional_options();

      if ( $allow_inheritance ) {
        $this->maybe_load_inherited_conditions();
      }

    }

    /**
     * Redirects and exits the current script if the visitor is prohibited from
     * accessing the loaded post.
     *
     * @since 1.0.0
     *
     * @uses \PTC_Trident\Protected_Post::is_prohibited()
     *
     * @uses \PTC_Trident\Protected_Post::get_usable_redirect_url()
     */
    function redirect_if_prohibited() {

      if (
        $this->is_prohibited()
        && wp_redirect( $this->get_usable_redirect_url(), 302, 'PTC Trident' )
      ) {
        exit;
      }

    }

    /**
     * Retrieve the URL that will be used for redirection. If a protected post
     * does not have a specific redirect URL set explicitly or by inheritance,
     * then the site's home url is used.
     *
     * @since 1.0.0
     */
    function get_usable_redirect_url() {

      $redirect_url = Options::sanitize( Options::REDIRECT_URL, $this->redirect_url );

      if ( empty( $redirect_url ) ) {
        $redirect_url = home_url();
      }

      return $redirect_url;

    }

    /**
     * Checks if the visitor is prohibited from accessing the post.
     *
     * @since 1.0.0
     *
     * @return bool If the visitor does not meet the post's content protection
     * conditions.
     */
    function is_prohibited() : bool {

      if ( is_admin() || is_super_admin() ) {
        /* Admins always bypass content protection */
        return FALSE;
      }

      if ( Purchase_Protection::is_woocommerce_loaded() ) {
        if (
          $this->product_protect_method === 'any'
          && Purchase_Protection::customer_owns_any_product( $this->required_product_ids ) === FALSE
        ) {
          return TRUE;
        } elseif (
          $this->product_protect_method === 'all'
          && Purchase_Protection::customer_owns_all_products( $this->required_product_ids ) === FALSE
        ) {
          return TRUE;
        }
      }

      switch ( $this->required_user_state ) {
        case 'logged_in':
          return ! is_user_logged_in();
          break;
        case 'logged_out':
          return is_user_logged_in();
          break;
        case 'user_editor':
          return ! current_user_can( 'edit_post', $this->post->ID );
          break;
        case 'user_any':
          break;
        default:
          $edit_post_link = get_edit_post_link( $this->post->ID );
          error_log( "Unrecognized user state condition {$this->required_user_state} for protected post {$this->post->ID}. CONTENT MAY NOT BE PROPERLY PROTECTED! Please resave this post to update its protection settings: {$edit_post_link}" );
      }

      return FALSE;

    }

    /**
     * Checks if access conditions exist.
     *
     * @since 1.0.0
     *
     * @return bool If access conditions exist.
     */
    function is_protected() : bool {

      if (
        count( $this->required_product_ids ) > 0
        || $this->required_user_state !== 'user_any'
      ) {
        return TRUE;
      }

      return FALSE;

    }

    /**
     * Get the ancestor post from which has been or would be inherited.
     *
     * @since 1.0.0
     *
     * @return bool|\PTC_Trident\Protected_Post The Protected Post object that
     * is the nearest ancestor post with descending conditions enabled.
     * Returns FALSE if there is no ancestor from which to inherit.
     */
    function get_protector() {

      if ( ! is_post_type_hierarchical( $this->post->post_type ) ) {
        return FALSE;
      }

      if ( $this->inherited_parent !== NULL ) {
        new Protected_Post( $this->inherited_parent, FALSE );
      }

      $ancestor = $this;

      do {

        if ( $ancestor->post->post_parent <= 0 ) {
          /* No protector ancestor */
          return FALSE;
        }

        $ancestor = get_post( $ancestor->post->post_parent );
        if ( ! is_a( $ancestor, '\WP_Post' ) ) {
          error_log( "Failed to load post parent {$ancestor->post->post_parent} when checking {$this->post->post_type} {$this->post->ID} for protective ancestor." );
          return FALSE;
        }

        $ancestor = new Protected_Post( $ancestor, FALSE );

      } while ( ! $ancestor->protect_children );

      if (
        is_a( $ancestor, '\\' . self::class )
        && $ancestor->protect_children
        && $ancestor->post->ID != $this->post->ID
      ) {
        return $ancestor;
      }

      return FALSE;

    }

    /**
     * Check if the instance is using inherited content protection rules from a
     * safe inherited parent.
     *
     * @since 1.0.0
     *
     * @return bool If the inherited parent is set and safe to use.
     */
    function is_inheriting_conditions() : bool {

      if (
        $this->inherited_parent !== NULL
        && is_a( $this->inherited_parent, '\WP_Post' )
        && isset( $this->inherited_parent->ID )
        && Options::post_exists( $this->inherited_parent->ID )
      ) {
        return TRUE;
      }

      return FALSE;

    }

    /* HELPERS */

    /**
     * Sanitizes, validates, and sets this instance's post to assess for
     * content protection.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post The post to asses for content protection.
     *
     * @throws \Exception Error code 400 if provided post is invalid.
     */
    private function load_the_post( \WP_Post $post ) {

      sanitize_post( $post );

      if (
        ! isset( $post->ID )
        || ! Options::post_exists( $post->ID )
        || ! isset( $post->post_type )
        || ! isset( $post->post_parent )
      ) {
        throw new \Exception( 'Cannot load post for content protection. Missing required data.', 400 );
      }

      $this->post = $post;

    }

    /**
     * Sets this instance's protection condition members:
     * - required_product_ids
     * - product_protect_method
     * - required_user_state
     *
     * @since 1.0.0
     */
    private function load_protection_conditions() {

      $required_product_ids = Options::get( Options::REQUIRED_PRODUCT_IDS, $this->post->ID );
      if ( is_array( $required_product_ids ) ) {
        $this->required_product_ids = $required_product_ids;
      } else {
        $this->required_product_ids = Options::get_default( Options::REQUIRED_PRODUCT_IDS, $this->post->ID );
      }

      $product_protect_method = Options::get( Options::PRODUCT_PROTECT_METHOD, $this->post->ID );
      if ( 'all' === $product_protect_method ) {
        $this->product_protect_method = $product_protect_method;
      } else {
        $this->product_protect_method = Options::get_default( Options::PRODUCT_PROTECT_METHOD, $this->post->ID );
      }

      $required_user_state = Options::get( Options::REQUIRED_USER_STATE, $this->post->ID );
      if ( in_array( $required_user_state, [ 'logged_in', 'logged_out', 'user_editor', 'user_any' ] ) ) {
        $this->required_user_state = $required_user_state;
      } else {
        $this->required_user_state = Options::get_default( Options::REQUIRED_USER_STATE, $this->post->ID );
      }

    }

    /**
     * Sets this instance's additional option members:
     * - redirect_url
     * - protect_children
     *
     * @since 1.0.0
     */
    private function load_additional_options() {

      $this->redirect_url = Options::get( Options::REDIRECT_URL, $this->post->ID );
      if ( ! is_string( $this->redirect_url ) ) {
        $this->redirect_url = Options::get_default( Options::REDIRECT_URL, $this->post->ID );
      }

      $protect_children = Options::get( Options::PROTECT_CHILDREN, $this->post->ID );
      if ( is_bool( $protect_children ) ) {
        $this->protect_children = $protect_children;
      } else {
        $this->protect_children = Options::get_default( Options::PROTECT_CHILDREN, $this->post->ID );
      }

      $override_inheritance = Options::get( Options::OVERRIDE_INHERITANCE, $this->post->ID );
      if ( is_bool( $override_inheritance ) ) {
        $this->override_inheritance = $override_inheritance;
      } else {
        $this->override_inheritance = Options::get_default( Options::OVERRIDE_INHERITANCE, $this->post->ID );
      }

    }

    /**
     * Loads inherited protection settings if an ancestor post is found to use
     * descending conditions.
     *
     * @since 1.0.0
     *
     * @uses \PTC_Trident\Protected_Post::get_protector()
     *
     * @uses \PTC_Trident\Protected_Post::inherit_protection_conditions_from()
     */
    private function maybe_load_inherited_conditions() {

      if ( $this->override_inheritance === TRUE ) {
        return;
      }

      $this->inherited_parent = NULL;
      $ancestor = $this->get_protector();

      if (
        $ancestor !== FALSE
        && is_a( $ancestor, self::class )
      ) {
        $this->inherit_protection_conditions_from( $ancestor );
      }

    }

    /**
     * Uses protection conditions from another Protected_Post object. If the
     * current Protected_Post does not have a specified redirect URL, then it
     * will also inherit the passed Protected_Post's redirect URL setting.
     *
     * @since 1.0.0
     *
     * @param \PTC_Trident\Protected_Post $from The Protected_Post from which to
     * inherit protection settings.
     */
    private function inherit_protection_conditions_from( Protected_Post $from ) {

      $this->required_product_ids = $from->required_product_ids;
      $this->product_protect_method = $from->product_protect_method;

      $this->required_user_state = $from->required_user_state;

      if ( empty( $this->redirect_url ) ) {
        $this->redirect_url = $from->redirect_url;
        $this->inherited_redirect_url = TRUE;
      }

      $this->inherited_parent = $from->post;

    }

  }//end class

}//end if class_exists
