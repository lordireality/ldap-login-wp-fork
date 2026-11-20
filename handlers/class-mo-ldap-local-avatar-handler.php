<?php
/**
 * Store LDAP profile photos and expose them to WordPress and wpForo.
 *
 * @package miniOrange_LDAP_AD_Integration
 * @subpackage handlers
 */

namespace MO_LDAP\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mo_Ldap_Local_Avatar_Handler {

	const META_KEY = 'mo_ldap_local_avatar';
	const MAX_BYTES = 5242880;

	public function __construct() {
		add_filter( 'get_avatar_data', array( $this, 'filter_avatar_data' ), 100, 2 );
		add_filter( 'wpforo_avatar_url', array( $this, 'filter_wpforo_avatar_url' ), 100, 2 );
		add_filter( 'wpforo_avatar_cache', array( $this, 'filter_wpforo_avatar_cache' ), 100, 2 );
		add_action( 'delete_user', array( $this, 'delete_user_photo' ) );
		add_action( 'wpmu_delete_user', array( $this, 'delete_user_photo' ) );
	}

	/**
	 * Called only after successful LDAP authentication and user creation/update.
	 * An absent/unreadable photo leaves the previous avatar untouched.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $photo   Raw LDAP binary value, never a URL or base64 text.
	 * @return bool Whether a valid photo is stored for this user.
	 */
	public static function sync_photo( $user_id, $photo ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! is_string( $photo ) || '' === $photo || strlen( $photo ) > self::MAX_BYTES ) {
			return false;
		}

		// Accept only raster formats that browsers and WordPress can use as avatars.
		$image = @getimagesizefromstring( $photo ); // Invalid directory data must not interrupt login.
		$extensions = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif' );
		if ( ! $image || empty( $image['mime'] ) || ! isset( $extensions[ $image['mime'] ] ) ||
			$image[0] < 1 || $image[1] < 1 || $image[0] > 4096 || $image[1] > 4096 ||
			$image[0] * $image[1] > 16000000 ) {
			return false;
		}

		$hash = hash( 'sha256', $photo );
		$previous = get_user_meta( $user_id, self::META_KEY, true );
		if ( is_array( $previous ) && isset( $previous['hash'] ) && $hash === $previous['hash'] && self::get_photo_url( $user_id ) ) {
			return true;
		}

		if ( ! function_exists( 'wp_upload_bits' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$filename = 'mo-ldap-avatar-' . $user_id . '-' . substr( $hash, 0, 16 ) . '.' . $extensions[ $image['mime'] ];
		$upload = wp_upload_bits( $filename, null, $photo );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) || empty( $upload['url'] ) ) {
			return false;
		}

		$avatar = array(
			'hash' => $hash,
			'file' => wp_normalize_path( $upload['file'] ),
			'url'  => esc_url_raw( $upload['url'] ),
		);
		// Metadata APIs unslash their input, including Windows file paths/URLs.
		$saved = update_user_meta( $user_id, self::META_KEY, wp_slash( $avatar ) );
		if ( ! $saved && get_user_meta( $user_id, self::META_KEY, true ) !== $avatar ) {
			self::delete_photo_file( $avatar );
			return false;
		}

		if ( is_array( $previous ) && isset( $previous['file'] ) && $previous['file'] !== $avatar['file'] ) {
			self::delete_photo_file( $previous );
		}
		return true;
	}

	/**
	 * A missing uploads file falls back to the site's existing avatar system.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string
	 */
	public static function get_photo_url( $user_id ) {
		if ( ! $user_id ) {
			return '';
		}
		$avatar = get_user_meta( absint( $user_id ), self::META_KEY, true );
		if ( ! is_array( $avatar ) || empty( $avatar['url'] ) || empty( $avatar['file'] ) || ! is_file( $avatar['file'] ) ) {
			return '';
		}
		return esc_url_raw( $avatar['url'] );
	}

	/**
	 * wpForo 2.x/3.x pass either a user ID or a member array to this filter.
	 */
	public function filter_wpforo_avatar_url( $url, $user ) {
		$photo_url = self::get_photo_url( self::resolve_user_id( $user ) );
		return $photo_url ? $photo_url : $url;
	}

	/**
	 * wpForo returns its persistent avatar cache before applying the URL filter.
	 */
	public function filter_wpforo_avatar_cache( $cache, $user_id ) {
		return self::get_photo_url( $user_id ) ? false : $cache;
	}

	/**
	 * Also covers wpForo's WordPress-avatar fallback and site comments/profiles.
	 */
	public function filter_avatar_data( $args, $id_or_email ) {
		if ( ! empty( $args['force_default'] ) ) {
			return $args;
		}
		$photo_url = self::get_photo_url( self::resolve_user_id( $id_or_email ) );
		if ( $photo_url ) {
			$args['url'] = $photo_url;
			$args['found_avatar'] = true;
		}
		return $args;
	}

	/**
	 * Resolve the user representations accepted by WordPress and wpForo.
	 */
	private static function resolve_user_id( $user ) {
		if ( is_int( $user ) || ( is_string( $user ) && ctype_digit( $user ) ) ) {
			return absint( $user );
		}
		if ( $user instanceof \WP_User ) {
			return absint( $user->ID );
		}
		if ( $user instanceof \WP_Post ) {
			return absint( $user->post_author );
		}
		if ( $user instanceof \WP_Comment ) {
			$allowed_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
			if ( ! in_array( get_comment_type( $user ), (array) $allowed_types, true ) ) {
				return 0;
			}
			if ( $user->user_id ) {
				return absint( $user->user_id );
			}
			$user = $user->comment_author_email;
		}
		if ( is_array( $user ) ) {
			if ( ! empty( $user['userid'] ) ) {
				return absint( $user['userid'] );
			}
			if ( ! empty( $user['ID'] ) ) {
				return absint( $user['ID'] );
			}
			$user = isset( $user['user_email'] ) ? $user['user_email'] : '';
		}
		if ( is_string( $user ) && is_email( $user ) ) {
			$wp_user = get_user_by( 'email', $user );
			return $wp_user ? absint( $wp_user->ID ) : 0;
		}
		return 0;
	}

	public function delete_user_photo( $user_id ) {
		$avatar = get_user_meta( $user_id, self::META_KEY, true );
		if ( is_array( $avatar ) ) {
			self::delete_photo_file( $avatar );
		}
	}

	/**
	 * Delete only our generated files inside the current site's uploads directory.
	 */
	private static function delete_photo_file( $avatar ) {
		if ( empty( $avatar['file'] ) || ! preg_match( '/^mo-ldap-avatar-\d+-[a-f0-9]{16}(?:-\d+)?\.(jpg|png|gif)$/D', basename( $avatar['file'] ) ) ) {
			return;
		}
		$uploads = wp_upload_dir( null, false );
		$base = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		$file = realpath( $avatar['file'] );
		if ( $base && $file && 0 === strpos( wp_normalize_path( $file ), trailingslashit( wp_normalize_path( $base ) ) ) ) {
			wp_delete_file( $file );
		}
	}
}
