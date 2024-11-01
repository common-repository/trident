<?php
/**
 * Options class
 *
 * Manages data stored in various WordPress tables such as options, usermeta,
 * and postmeta.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace PTC_Trident;

defined( 'ABSPATH' ) || die();

if ( ! class_exists( __NAMESPACE__ . '\Options' ) ) {
  /**
   * Static class to manage blog-level options.
   */
  class Options {

    /**
     * Postmeta key for allowed user conditions. Value may be WooCommerce
     * product post ID. Multiple entries per post are expected. Default [].
     *
     * @since 1.0.0
     *
     * @var string REQUIRED_PRODUCT_IDS
     */
    const REQUIRED_PRODUCT_IDS = '_ptc_trident_required_product';

    /**
     * Postmeta key for allowed user conditions. Value may be "logged_in",
     * "logged_out", "user_editor", or "user_any" (default).
     *
     * @since 1.0.0
     *
     * @var string REQUIRED_USER_STATE
     */
    const REQUIRED_USER_STATE = '_ptc_trident_required_user_state';

    /**
     * Postmeta key for blocked user redirects. If the content protection
     * conditions are not met, the page will redirect to this URL meta value.
     * Value must be a valid URL. Default ''.
     *
     * @since 1.0.0
     *
     * @var string REDIRECT_URL
     */
    const REDIRECT_URL = '_ptc_trident_redirect_url';

    /**
     * Postmeta key for the logical operator to compare protection conditions.
     * Value may be "any" (default) or "all".
     *
     * @since 1.0.0
     *
     * @var string PRODUCT_PROTECT_METHOD
     */
    const PRODUCT_PROTECT_METHOD = '_ptc_trident_product_method';

    /**
     * Postmeta key for if a content's protection settings should be cascaded
     * down to all children pages without their own content protection settings.
     * Value may be "yes" or "no" (default), returned as a boolean.
     *
     * @since 1.0.0
     *
     * @var string PROTECT_CHILDREN
     */
    const PROTECT_CHILDREN = '_ptc_trident_descend';

    /**
     * Postmeta key for if a content's protection settings act as overrides to
     * inherited content protection settings.
     * Value may be "yes" or "no" (default), returned as a boolean.
     *
     * @since 1.0.0
     *
     * @var string OVERRIDE_INHERITANCE
     */
    const OVERRIDE_INHERITANCE = '_ptc_trident_override_inheritance';

    /**
     * Gets a sanitized value for an option of this class returned in the key's
     * format as documented on this class's constants. Data self-healing will
     * occur if the sanitized value is unusable.
     *
     * @since 1.0.0
     *
     * @param string $key The key name. Use this class's constant members.
     *
     * @param int $object_id Optional. The relevant user or post id for which to
     * retrieve. Default 0 to use current object, if available.
     *
     * @return mixed The value returned from the database, sanitized and
     * formatted as documented on option key constant members in this class.
     * Default '' if no default value has been specified for the option key
     * constant member.
     */
    static function get( string $key, int $object_id = 0 ) {

      self::process_object_id( $key, $object_id );

      switch ( $key ) {

        case self::REQUIRED_PRODUCT_IDS:
          if ( $object_id === 0 ) {
            return self::get_default( $key );
          }
          $values = get_post_meta( $object_id, $key, FALSE );
          $sanitized_values = [];
          foreach ( $values as $i => $value ) {
            $sanitized_value = self::sanitize( $key, $value );
            if ( $value != $sanitized_value ) {
              error_log( "ALERT: Sanitization occurred. Saved meta is corrupt for post $object_id: $key" );
            }
            if ( ! is_numeric( $sanitized_value ) ) {
              if ( $value != '' && $object_id > 0 && self::delete( $key, $object_id, $value ) ) {
                error_log( "Deleted invalid meta value for post $object_id: $key" );
              }
              continue;
            }
            $sanitized_values[] = (int) $sanitized_value;
          }
          return $sanitized_values;

        /*-----*/

        case self::REQUIRED_USER_STATE:
          if ( $object_id === 0 ) {
            return self::get_default( $key );
          }
          $value = get_post_meta( $object_id, $key, TRUE );
          $sanitized_value = self::sanitize( $key, $value );
          if ( $value != $sanitized_value ) {
            error_log( "ALERT: Sanitization occurred. Saved meta is corrupt for post $object_id: $key" );
          }
          if ( ! in_array( $sanitized_value, [ 'logged_in', 'logged_out', 'user_editor', 'user_any' ] ) ) {
            if ( $value != '' && $object_id > 0 && self::delete( $key, $object_id, $value ) ) {
              error_log( "Deleted invalid meta value for post $object_id: $key" );
            }
            return self::get_default( $key );
          }
          return $sanitized_value;

        /*-----*/

        case self::REDIRECT_URL:
          if ( $object_id === 0 ) {
            return self::get_default( $key );
          }
          $value = get_post_meta( $object_id, $key, TRUE );
          $sanitized_value = self::sanitize( $key, $value );
          if ( $value != $sanitized_value ) {
            error_log( "ALERT: Sanitization occurred. Saved meta is corrupt for post $object_id: $key" );
          }
          if ( filter_var( $sanitized_value, FILTER_VALIDATE_URL ) === FALSE ) {
            if ( $value != '' && $object_id > 0 && self::delete( $key, $object_id, $value ) ) {
              error_log( "Deleted invalid meta value for post $object_id: $key" );
            }
            return self::get_default( $key );
          }
          return $sanitized_value;

        /*-----*/

        case self::PRODUCT_PROTECT_METHOD:
          if ( $object_id === 0 ) {
            return self::get_default( $key );
          }
          $value = get_post_meta( $object_id, $key, TRUE );
          $sanitized_value = self::sanitize( $key, $value );
          if ( $value != $sanitized_value ) {
            error_log( "ALERT: Sanitization occurred. Saved meta is corrupt for post $object_id: $key" );
          }
          if ( ! ( $sanitized_value == 'any' || $sanitized_value == 'all' ) ) {
            if ( $value != '' && $object_id > 0 && self::delete( $key, $object_id, $value ) ) {
              error_log( "Deleted invalid meta value for post $object_id: $value, $key" );
            }
            return self::get_default( $key );
          }
          return $sanitized_value;

        /*-----*/

        case self::PROTECT_CHILDREN:
        case self::OVERRIDE_INHERITANCE:
          if ( $object_id === 0 ) {
            return self::get_default( $key );
          }
          $value = get_post_meta( $object_id, $key, TRUE );
          $sanitized_value = self::sanitize( $key, $value );
          if ( $value != $sanitized_value ) {
            error_log( "ALERT: Sanitization occurred. Saved meta is corrupt for post $object_id: $key" );
          }
          if ( ! ( $sanitized_value == 'yes' || $sanitized_value == 'no' ) ) {
            if ( $value != '' && $object_id > 0 && self::delete( $key, $object_id, $value ) ) {
              error_log( "Deleted invalid meta value for post $object_id: $value, $key" );
            }
            return self::get_default( $key );
          }
          return filter_var( $sanitized_value, FILTER_VALIDATE_BOOLEAN );

      }//end switch key

      error_log( 'Invalid key to get value: ' . $key );
      return '';

    }

    /**
     * Get the default value for an option key.
     *
     * @since 1.0.0
     *
     * @param string $key The key name. Use this class's constant members.
     *
     * @return mixed The default value for the option key.
     */
    static function get_default( string $key ) {

      switch ( $key ) {

        case self::REQUIRED_PRODUCT_IDS:
          return [];

        case self::REQUIRED_USER_STATE:
          return 'user_any';

        case self::REDIRECT_URL:
          return '';

        case self::PRODUCT_PROTECT_METHOD:
          return 'any';

        case self::PROTECT_CHILDREN:
        case self::OVERRIDE_INHERITANCE:
          return FALSE;

      }//end switch key

      error_log( 'Invalid key to get default value: ' . $key );
      return FALSE;

    }

    /**
     * Saves a sanitized value for an option of this class.
     *
     * @since 1.0.0
     *
     * @param string $key The key name. Use this class's constant members.
     *
     * @param string $value The value to attempt to save.
     *
     * @param bool $force Optional. If to force saving when sanitization occurs.
     * Default FALSE to throw \Exceptions.
     *
     * @param int $object_id Optional. The relevant user or post id for which to
     * save. Default 0 to use current object, if available.
     *
     * @return bool If the option was updated.
     *
     * @throws \Exception The possible exception codes are:
     * - 404: If the provided object ID is invalid.
     * - 400: If $force is FALSE, throws when sanitized value to save is
     * different than passed value. If $force is TRUE, only throws when
     * an invalid value would be saved.
     */
    static function save( string $key, string $value, bool $force = FALSE, int $object_id = 0 ) : bool {

      self::process_object_id( $key, $object_id );
      if ( $object_id === 0 ) {
        throw new \Exception( 'ERROR: Invalid object ID to save value for key: ' . $key, 404 );
      }

      switch ( $key ) {

        case self::REQUIRED_PRODUCT_IDS:
          $sanitized_value = self::sanitize( $key, $value );
          if ( ! $force && $value != $sanitized_value ) {
            throw new \Exception( 'ERROR: Refused to save different value for postmeta: ' . $key, 400 );
          }
          return self::maybe_add_postmeta( $key, $sanitized_value, $object_id );

        case self::REQUIRED_USER_STATE:
        case self::REDIRECT_URL:
        case self::PRODUCT_PROTECT_METHOD:
        case self::PROTECT_CHILDREN:
        case self::OVERRIDE_INHERITANCE:
          $sanitized_value = self::sanitize( $key, $value );
          if ( ! $force && $value != $sanitized_value ) {
            throw new \Exception( 'ERROR: Refused to save different value for postmeta: ' . $key, 400 );
          }
          return self::maybe_update_postmeta( $key, $sanitized_value, $object_id );

      }

      error_log( 'Invalid key to save value: ' . $key );
      return FALSE;

    }

    /**
     * Deletes an option of this class.
     *
     * @since 1.0.0
     *
     * @param string $key The key name. Use this class's
     * constant members when specifying the desired key to delete.
     *
     * @param int $object_id Optional. The relevant user or post id for which to
     * delete the key. Set to -1 to delete for all objects. Default 0 to delete
     * for current object, if available.
     *
     * @param string $value Optional. The meta value to be deleted. If provided,
     * only metadata entries matching the key and value will be deleted.
     * Default '' to delete all key entries, regardless of value. TAKE CAUTION
     * WHEN PASSING A VARIABLE. FIRST CHECK IF EMPTY TO AVOID UNEXPECTED
     * DELETION OF ALL KEY INSTANCES.
     *
     * @return bool If the option was deleted. FALSE if key is invalid.
     */
    static function delete( string $key, int $object_id = 0, string $value = '' ) : bool {

      switch ( $key ) {

        case self::REQUIRED_PRODUCT_IDS:
        case self::REQUIRED_USER_STATE:
        case self::REDIRECT_URL:
        case self::PRODUCT_PROTECT_METHOD:
        case self::PROTECT_CHILDREN:
        case self::OVERRIDE_INHERITANCE:
          if ( $object_id === -1 ) {
            return delete_metadata( 'post', 0, $key, '', TRUE );
          } else {
            self::process_object_id( $key, $object_id );
            if ( $object_id > 0 ) {
              return delete_post_meta( $object_id, $key, $value );
            } else {
              return FALSE;
            }
          }

      }

      return FALSE;

    }

    /**
     * Deletes all options for the object.
     *
     * @since 1.0.0
     *
     * @uses \PTC_Trident\Options::delete()
     *
     * @param int $object_id Optional. The relevant user or post id for which to
     * delete each key. Set to 0 to delete for the current object. Default -1 to
     * delete for all objects, if available.
     */
    static function delete_all( int $object_id = -1 ) {

      if ( $object_id !== -1 ) {
        self::process_object_id( $key, $object_id );
        if ( $object_id <= 0 ) {
          return;
        }
      }

      $constants_reflection = new \ReflectionClass( self::class );
      $constants = $constants_reflection->getConstants();
      foreach ( $constants as $name => $value ) {
        self::delete( $value, $object_id );
      }

    }

    /**
     * Sanitizes a value based on the given context.
     *
     * @since 1.0.0
     *
     * @param string $context The data context for sanitizing. Use this class's
     * constant members when specifying the desired option context to use. Other
     * possible values are 'id', 'datetime', 'date', 'string', 'html', 'url'.
     *
     * @param string $value The value to sanitize.
     *
     * @return string The sanitized string. Default ''.
     */
    static function sanitize( string $context, string $value ) : string {

      $value = trim( $value );

      switch ( $context ) {

        case 'id':
        case self::REQUIRED_PRODUCT_IDS:
          $filtered_integer_string = filter_var(
            $value,
            FILTER_SANITIZE_NUMBER_INT
          );
          $sanitized_integer_string = preg_replace( '/[^0-9]+/', '', $filtered_integer_string );
          return (string) $sanitized_integer_string;

        case 'datetime':
          $filtered_datetime = filter_var(
            $value,
            FILTER_SANITIZE_STRING,
            FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
          );
          $sanitized_datetime = preg_replace( '/[^0-9:\- ]+/', '', $filtered_datetime );
          /* should be string in format Y-m-d H:i:s */
          $dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date );
          if ( $dt !== FALSE && array_sum( $dt::getLastErrors() ) === 0 ) {
            $dt_string = $dt->format('Y-m-d H:i:s');
            return ( $dt_string !== FALSE ) ? $dt_string : '';
          } else {
            return '';
          }

        case 'date':
          $filtered_date = filter_var(
            $value,
            FILTER_SANITIZE_STRING,
            FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
          );
          $sanitized_date = preg_replace( '/[^0-9\-]+/', '', $filtered_date );
          /* should be string in format yyyy-mm-dd */
          $dt = \DateTime::createFromFormat( 'Y-m-d', $sanitized_date );
          if ( $dt !== FALSE && array_sum( $dt::getLastErrors() ) === 0 ) {
            $dt_string = $dt->format('Y-m-d');
            return ( $dt_string !== FALSE ) ? $dt_string : '';
          } else {
            return '';
          }

        case 'string':
        case self::REQUIRED_USER_STATE:
        case self::PRODUCT_PROTECT_METHOD:
        case self::PROTECT_CHILDREN:
        case self::OVERRIDE_INHERITANCE:
          $filtered_value = filter_var(
            $value,
            FILTER_SANITIZE_STRING,
            FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
          );
          return ( $filtered_value !== FALSE ) ? $filtered_value : '';

        case 'html':
          $sanitized_value = wp_kses(
            $value,
            [
              'a' => [
                'href' => [],
                'title' => [],
                'target' => [],
                'id' => [],
                'class' => [],
              ],
              'br' => [],
              'em' => [],
              'strong' => [],
              'i' => [
                'class' => [],
              ],
              'b' => [],
            ],
            [ 'http', 'https', 'mailto' ]
          );
          return $sanitized_value;

        case 'url':
        case self::REDIRECT_URL:
          return esc_url_raw( $value, [ 'http', 'https' ] );


      }

      error_log( 'Invalid sanitization context: ' . $context );
      return '';

    }

    /**
     * Checks if a post exists.
     *
     * @since 1.0.0
     *
     * @param int $post_id The post's ID to check.
     *
     * @return bool If the post exists.
     */
    static function post_exists( int $post_id ) : bool {

      global $wpdb;
      $res = $wpdb->get_var( $wpdb->prepare(
          "
          SELECT ID
          FROM {$wpdb->posts}
          WHERE ID = %d
          ",
          $post_id
        ) );

      if ( $res === NULL ) {
        return FALSE;
      }

      return TRUE;

    }

    /**
     * Checks if a postmeta key-value pair exists.
     *
     * @since 1.0.0
     *
     * @param string $key The meta key name.
     *
     * @param string $value The value to search.
     *
     * @param int $post_id Optional. The post's id. Set to 0 to use current
     * post. Default -1 for any post.
     *
     * @return bool Returns TRUE if the postmeta key-value pair exists.
     */
    static function postmeta_exists( string $key, string $value, int $post_id = -1 ) : bool {

      if ( $post_id === 0 ) {
        $post_id = get_the_ID();
        if ( $post_id === 0 || $post_id === FALSE ) {
          return FALSE;
        }
      }

      global $wpdb;

      if ( $post_id < 0 ) {
        $res = $wpdb->get_var( $wpdb->prepare(
            "
            SELECT meta_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s
              AND meta_value = %s
            ",
            $key,
            $value
          ) );
      } else {
        $res = $wpdb->get_var( $wpdb->prepare(
            "
            SELECT meta_id
            FROM {$wpdb->postmeta}
            WHERE post_id = %d
              AND meta_key = %s
              AND meta_value = %s
            ",
            $post_id,
            $key,
            $value
          ) );
      }

      if ( $res === NULL ) {
        return FALSE;
      }

      return TRUE;

    }

    /**
     * Encrypts or decrypts a string.
     *
     * @since 1.0.0
     *
     * @param string $value The value to encrypt or decrypt.
     *
     * @param string $mode Optional. The action to take on the provided value.
     * 'e' to encrypt or 'd' to decrypt. Default 'e'.
     *
     * @return string The encrypted or decrypted result. Default '' if failure.
     */
    static function crypt( string $value, string $mode = 'e' ) : string {

      $key = AUTH_SALT;
      $iv = NONCE_SALT;
      $method = 'aes-256-ctr';

      $iv = substr( $iv, 0, openssl_cipher_iv_length( $method ) );

      if ( $mode === 'e' ) {
        $encrypted = openssl_encrypt( $value, $method, $key, 0, $iv );
        if ( FALSE === $encrypted ) {
          error_log( 'OpenSSL encryption failed.' );
          return '';
        }
        return base64_encode( $encrypted );
      } elseif ( $mode === 'd' ) {
        $decrypted = openssl_decrypt( base64_decode( $value ), $method, $key, 0, $iv );
        if ( FALSE === $decrypted ) {
          error_log( 'OpenSSL decryption failed.' );
          return '';
        }
        return $decrypted;
      }

      error_log( "Invalid crypt mode '$mode'. Accepted values are 'e' and 'd'." );
      return '';

    }

    /* HELPERS */

    /**
     * Sets the object ID to the current object for the key's context and/or
     * validates the object ID.
     *
     * @since 1.0.0
     *
     * @param string $key The key context for processing.
     *
     * @param int &$object_id The object ID to process. If the object ID is 0
     * when passed, the global current object for the key's context is used.
     * If the object ID is 0 after processing, the object ID was invalid.
     */
    private static function process_object_id( string $key, int &$object_id ) {

      switch ( $key ) {

        case 'post':
        case self::REQUIRED_PRODUCT_IDS:
        case self::REQUIRED_USER_STATE:
        case self::REDIRECT_URL:
        case self::PRODUCT_PROTECT_METHOD:
        case self::PROTECT_CHILDREN:
        case self::OVERRIDE_INHERITANCE:
          if ( $object_id === 0 ) {
            $object_id = get_the_ID();
            if ( $object_id === 0 || $object_id === FALSE || ! is_numeric( $object_id ) ) {
              error_log( "Failed to get current post for key: {$key}\n" .
                print_r( debug_backtrace( 0, 5 ), TRUE ) );
              $object_id = 0;
              return;
            }
          }
          if ( self::post_exists( $object_id ) ) {
            return;
          } else {
            error_log( "Post {$object_id} does not exist for key: {$key}\n" .
              print_r( debug_backtrace( 0, 5 ), TRUE ) );
            $object_id = 0;
            return;
          }

      }

      error_log( 'Invalid key to process object id: ' . $key );
      $object_id = 0;

    }

    /**
     * Updates option value only if different.
     *
     * @since 1.0.0
     *
     * @param string $key The option key name.
     *
     * @param string $value The value to be saved.
     *
     * @param bool $autoload Optional. If the option should be loaded when
     * WordPress starts up. Default FALSE.
     *
     * @return bool If the option value was updated.
     */
    private static function maybe_update_option( string $key, string $value, bool $autoload = FALSE ) : bool {

      if ( get_option( $key, '' ) === $value ) {
        return FALSE;
      }

      return update_option( $key, $value, $autoload );

    }

    /**
     * Updates the usermeta value only if different.
     *
     * @since 1.0.0
     *
     * @param string $key The meta key name.
     *
     * @param string $value The value to be saved.
     *
     * @param int $user_id Optional. The user's id. Default 0 for current user.
     *
     * @return bool If the user's meta value was updated.
     */
    private static function maybe_update_usermeta( string $key, string $value, int $user_id = 0 ) : bool {

      if ( $user_id === 0 ) {
        $user_id = get_current_user_id();
      }

      if (
        $user_id === 0
        || get_user_meta( $user_id, $key, TRUE ) === $value
      ) {
        return FALSE;
      }

      return update_user_meta( $user_id, $key, $value ) ? TRUE : FALSE;

    }

    /**
     * Inserts the postmeta value only if it does not already exist.
     *
     * @since 1.0.0
     *
     * @param string $key The meta key name.
     *
     * @param string $value The value to be saved.
     *
     * @param int $post_id Optional. The post's id. Default 0 for current post.
     *
     * @return bool Returns TRUE if the post's meta value was inserted.
     */
    private static function maybe_add_postmeta( string $key, string $value, int $post_id = 0 ) : bool {

      if ( ! self::postmeta_exists( $key, $value, $post_id ) ) {
        return add_post_meta( $post_id, $key, $value ) ? TRUE : FALSE;
      }

      return FALSE;

    }

    /**
     * Updates the postmeta value only if it does not already exist.
     *
     * @since 1.0.0
     *
     * @param string $key The meta key name.
     *
     * @param string $value The value to be saved.
     *
     * @param int $post_id Optional. The post's id. Default 0 for current post.
     *
     * @return bool Returns TRUE if the post's meta value was added or updated.
     */
    private static function maybe_update_postmeta( string $key, string $value, int $post_id = 0 ) : bool {

      if ( ! self::postmeta_exists( $key, $value, $post_id ) ) {
        $res = update_post_meta( $post_id, $key, $value );
        return ( $res === TRUE || $res > 0 ) ? TRUE : FALSE;
      }

      return FALSE;

    }

  }//end class
}//end if class_exists
