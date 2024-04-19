<?php

if ( ! class_exists( 'GFForms' ) ) {
	exit();
}

/**
 * Gravity Forms AWeber Add-On API library.
 *
 * @since     4.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2023, Rocketgenius
 */
class GF_Aweber_API {

	/**
	 * AWeber API URL.
	 *
	 * @since 4.0
	 *
	 * @var string $api_url AWeber API URL.
	 */
	protected $api_url = 'https://api.aweber.com/1.0';

	/**
	 * Gravity API URL.
	 *
	 * @since 4.0
	 *
	 * @var string $gravity_api_url Gravity API URL.
	 */
	protected static $gravity_api_url = 'https://gravityapi.com/wp-json/gravityapi/v1';

	/**
	 * Auth tokens.
	 *
	 * @since 4.0
	 *
	 * @var array $auth_data Tokens and expiry information.
	 */
	protected $auth_data = array();

	/**
	 * The legacy SDK object.
	 *
	 * @since 4.0
	 *
	 * @var null|AWeberAPI Null or the legacy SDK object.
	 */
	protected $sdk = null;

	/**
	 * Initialize AWeber API library.
	 *
	 * @since 4.0
	 *
	 * @param array $auth_data array with tokens and expiry information.
	 */
	public function __construct( $sdk_or_auth_data = null ) {

		if ( $sdk_or_auth_data instanceof AWeberAPI ) {
			$this->sdk = $sdk_or_auth_data;
		} elseif ( is_array( $sdk_or_auth_data ) ) {
			$this->auth_data = $sdk_or_auth_data;
		}

	}

	/**
	 * Get Gravity API URL for path.
	 *
	 * @since 4.0
	 *
	 * @param string $path Endpoint path.
	 *
	 * @return string URL for Gravity API endpoint.
	 */
	public static function get_gravity_api_url( $path = '' ) {

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		return defined( 'GRAVITY_API_URL' ) ? GRAVITY_API_URL . '/auth/aweber' . $path : self::$gravity_api_url . '/auth/aweber' . $path;

	}

	/**
	 * Use refresh token to get new access token.
	 *
	 * @since 4.0
	 *
	 * @return array|WP_Error Return auth data on success, otherwise error.
	 */
	public function refresh_token() {

		$auth_data = $this->auth_data;

		// If refresh token is not in settings, return exception.
		if ( ! rgar( $this->auth_data, 'refresh_token' ) ) {
			return new WP_Error( 'aweber_refresh_token_error', esc_html__( 'Refresh token must be provided.', 'gravityformsaweber' ) );
		}

		$response = $this->make_request( '/refresh', array( 'refreshtoken' => rgar( $this->auth_data, 'refresh_token' ) ), 'POST', true );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Store and return new access token.
		$auth_data['access_token'] = rgar( $response, 'access_token' );
		$auth_data['expires_in']   = rgar( $response, 'expires_in' );
		$auth_data['time_created'] = time();

		$this->auth_data = $auth_data;

		return $auth_data;
	}


	/**
	 * Revoke AWeber refresh token
	 *
	 * @since 4.0
	 *
	 * @return boolean|WP_Error Result of the revoking request, if network request failed return error.
	 */
	public function revoke_refresh_token() {

		$request_options = array(
			'token_type_hint' => 'refresh_token',
			'token'           => rgar( $this->auth_data, 'refresh_token', '' ),
		);

		return $this->make_request( '/disconnect', $request_options, 'POST', true );

	}

	/**
	 * Indicates if requests should use the legacy SDK to interact with the AWeber API.
	 *
	 * @since 4.0
	 *
	 * @return bool
	 */
	public function is_legacy_connection() {
		return ! empty( $this->sdk );
	}

	/**
	 * Gets the accounts.
	 *
	 * @since 4.0
	 *
	 * @return array|WP_Error
	 */
	public function get_accounts() {
		return $this->make_request( '/accounts' );
	}

	/**
	 * Gets the lists for the specified account.
	 *
	 * @since 4.0
	 *
	 * @param int $account_id The account ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_lists( $account_id ) {
		return $this->make_request( "/accounts/{$account_id}/lists" );
	}

	/**
	 * Gets the specified list for the specified account.
	 *
	 * @since 4.0
	 *
	 * @param int $account_id The account ID.
	 * @param int $list_id    The list ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_list( $account_id, $list_id ) {
		return $this->make_request( "/accounts/{$account_id}/lists/{$list_id}" );
	}

	/**
	 * Gets the custom fields for the specified account and list.
	 *
	 * @since 4.0
	 *
	 * @param int $account_id The account ID.
	 * @param int $list_id    The list ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_custom_fields( $account_id, $list_id ) {
		return $this->make_request( "/accounts/{$account_id}/lists/{$list_id}/custom_fields" );
	}

	/**
	 * Adds a subscriber to the specified account and list.
	 *
	 * @since 4.0
	 *
	 * @param int   $account_id The account ID.
	 * @param int   $list_id    The list ID.
	 * @param array $subscriber The subscriber properties.
	 *
	 * @return array|WP_Error
	 */
	public function add_subscriber( $account_id, $list_id, $subscriber ) {
		$path = "/accounts/{$account_id}/lists/{$list_id}/subscribers";

		if ( $this->is_legacy_connection() ) {
			$subscribers = $this->make_legacy_get_request( $path . '?ws.size=1', true );
			if ( is_wp_error( $subscribers ) ) {
				return $subscribers;
			}

			try {
				$result = $subscribers->create( $subscriber );
			} catch ( \AWeberAPIException $e ) {
				return new WP_Error( rgobj( $e, 'status' ), rgobj( $e, 'message' ) );
			}

			return empty( $result->data ) ? array() : $result->data;
		}

		return $this->make_request( $path, $subscriber, 'POST', false, true );
	}


	// # REQUEST METHODS -----------------------------------------------------------------------------------------------

	/**
	 * Make API request.
	 *
	 * @since 4.0
	 *
	 * @param string  $path            Path of endpoint.
	 * @param array   $options         Options for endpoint.
	 * @param string  $method          HTTP method. Defaults to GET.
	 * @param boolean $use_gravity_api If true, send request to Gravity API server, otherwise use AWeber API.
	 * @param boolean $get_created     If true, an extra request will be made to get the created object.
	 *
	 * @return array|AWeberCollection|AWeberEntry|false|WP_Error return result data, if request failed return error.
	 */
	private function make_request( $path, $options = array(), $method = 'GET', $use_gravity_api = false, $get_created = false ) {
		if ( $this->is_legacy_connection() ) {
			return $this->make_legacy_get_request( $path );
		}

		if ( '/' !== substr( $path, 0, 1 ) ) {
			$path = '/' . $path;
		}

		// Build request options string.
		$request_options = 'GET' === $method ? '?' . http_build_query( $options ) : null;

		// Use AWeber API or Gravity API?
		$request_url = ( true === $use_gravity_api ) ? $this->get_gravity_api_url( $path ) : $this->api_url . $path;

		// Build request URL.
		$request_url = $request_url . $request_options;

		// Build request arguments.
		$request_args = array(
			'method'    => $method,
			'body'      => 'GET' !== $method ? wp_json_encode( $options ) : '',
			'headers'   => array(
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . rgar( $this->auth_data, 'access_token', '' ),
			),
			/**
			 * Filters if SSL verification should occur.
			 *
			 * @since 4.0
			 *
			 * @param bool $local_ssl_verify false If the SSL certificate should be verified. Defaults to false.
			 *
			 * @return bool
			 */
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			/**
			 * Sets the HTTP timeout, in seconds, for the request.
			 *
			 * @since 4.0
			 *
			 * @param int    $request_timeout The timeout limit, in seconds. Defaults to 30.
			 * @param string $request_url     The request URL.
			 *
			 * @return int
			 */
			'timeout'   => apply_filters( 'http_request_timeout', 30, $request_url ),
		);

		// Execute API request.
		$response = wp_remote_request( $request_url, $request_args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code === 201 && empty( $response_body ) && $get_created ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( ! empty( $location ) && strpos( $location, $this->api_url ) === 0 ) {
				return $this->make_request( str_replace( $this->api_url, '', $location ) );
			}
		}

		if ( $response_code < 300 ) {
			// Return result.
			return json_decode( $response_body, true );
		}

		// Return WP_Error.
		$error_details = json_decode( $response_body, true );

		if ( ! is_null( $error_details ) ) {

			$message = rgars( $error_details, 'error/message' );
			if ( empty( $message ) ) {
				$message = rgar( $error_details, 'title' ) . ': ' . rgar( $error_details, 'detail' );
			}

			return new WP_Error(
				$response_code,
				$message,
				wp_remote_retrieve_body( $response )
			);

		} else {

			// In the unlikely event Aweber didn't return an error.
			return new WP_Error(
				$response_code,
				/* translators: A request sent to Aweber returned an error, no error details available.  */
				esc_html__( 'Your request can not be processed by Aweber, no error details available.', 'gravityformsaweber' ),
				wp_remote_retrieve_body( $response )
			);

		}
	}

	/**
	 * Uses the legacy SDK to make a GET request to the specified API path.
	 *
	 * @since 4.0
	 *
	 * @param string $path          The endpoint path.
	 * @param bool   $return_object Indicates if the full AWeber response, an object, should be returned instead of just the data property.
	 *
	 * @return array|AWeberCollection|AWeberEntry|false|WP_Error
	 */
	private function make_legacy_get_request( $path, $return_object = false ) {
		try {
			$result = $this->sdk->loadFromUrl( $path );
		} catch ( \AWeberAPIException $e ) {
			return new WP_Error( rgobj( $e, 'status' ), rgobj( $e, 'message' ) );
		}

		if ( $return_object ) {
			return $result;
		}

		return empty( $result->data ) ? array() : $result->data;
	}

}

