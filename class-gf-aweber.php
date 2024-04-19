<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

GFForms::include_feed_addon_framework();

/**
 * Gravity Forms AWeber Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2017, Rocketgenius
 */
class GFAWeber extends GFFeedAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  Unknown
	 * @access private
	 * @var    GFAWeber|null $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the AWeber Add-On.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_version Contains the version, defined from aweber.php
	 */
	protected $_version = GF_AWEBER_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '1.9.11';

	/**
	 * Defines the plugin slug.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsaweber';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_path The path of the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsaweber/aweber.php';

	/**
	 * Defines the full path to the class file.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_full_path The full path to this file.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_url The URL of the Add-On.
	 */
	protected $_url = 'https://www.gravityforms.com';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_title The title of the Add-On.
	 */
	protected $_title = 'AWeber Add-On';

	/**
	 * Defines the short title of this Add-On.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_short_title The short title of the Add-On.
	 */
	protected $_short_title = 'AWeber';

	/**
	 * Defines if Add-On should use Gravity Forms server for update data.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_aweber';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_aweber';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_aweber_uninstall';

	/**
	 * Defines the capabilities needed for the AWeber Add-On.
	 *
	 * @since  Unknown
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On.
	 */
	protected $_capabilities = array( 'gravityforms_aweber', 'gravityforms_aweber_uninstall' );

	/**
	 * Enabling background feed processing to prevent performance issues delaying form submission completion.
	 *
	 * @since 2.12
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = true;

	/**
	 * Version of this add-on which requires reauthentication with the API.
	 *
	 * Anytime updates are made that requires a site to reauthenticate with AWeber, this
	 * constant should be updated to match the release value of GF_AWEBER_VERSION.
	 *
	 * @since 4.0.0
	 */
	const LAST_REAUTHENTICATION_VERSION = '4.0.0';

	/**
	 * An instance of the Aweber API object.
	 *
	 * @since 4.0
	 *
	 * @var null|false|GF_Aweber_API An instance of the API object.
	 */
	private $api = null;

	/**
	 * Get an instance of this class.
	 *
	 * @since  Unknown
	 * @access public
	 * @static
	 *
	 * @return GFAWeber
	 */
	public static function get_instance() {

		if ( self::$_instance == null ) {
			self::$_instance = new self;
		}

		return self::$_instance;

	}

	/**
	 * Plugin starting point.
	 * Handles hooks, loading of language files and PayPal delayed payment support.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFFeedAddOn::add_delayed_payment_support()
	 */
	public function init() {

		parent::init();

		$this->add_delayed_payment_support( array(
			'option_label' => esc_html__( 'Subscribe user to AWeber only when payment is received.', 'gravityformsaweber' ),
		) );

	}

	/**
	 * Updates the auth token before the renderer is initialized with the settings from the db.
	 *
	 * @since 4.0
	 */
	public function plugin_settings_init() {
		$this->maybe_update_auth_tokens();
		parent::plugin_settings_init();
	}

	/**
	 * Register admin initialization hooks.
	 *
	 * @since 4.0
	 */
	public function init_admin() {
		parent::init_admin();
		add_action( 'admin_notices', array( $this, 'maybe_show_authentication_warning' ) );
	}

	/**
	 * Add Ajax callback.
	 *
	 * @since 4.0
	 */
	public function init_ajax() {

		parent::init_ajax();

		// Ajax callback to disconnect Aweber account.
		add_action( 'wp_ajax_gfaweber_deauthorize', array( $this, 'ajax_deauthorize' ) );

	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 2.11
	 *
	 * @return string
	 */
	public function get_menu_icon() {

		return file_get_contents( $this->get_base_path() . '/images/menu-icon.svg' );

	}

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @since 4.0
	 *
	 * @return array Scripts to be enqueued.
	 */
	public function scripts() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$scripts = array(
			array(
				'handle'  => 'gform_aweber_pluginsettings',
				'deps'    => array( 'jquery' ),
				'src'     => $this->get_base_url() . "/js/plugin_settings{$min}.js",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->get_slug(),
					),
				),
				'strings' => array(
					/* translators: Confirmation question displayed when user clicks button to disconnect Aweber */
					'disconnect' => wp_strip_all_tags( __( 'Are you sure you want to disconnect AWeber?', 'gravityformsaweber' ) ),
					'ajax_nonce' => wp_create_nonce( 'gf_aweber_ajax' ),
					'is_legacy'  => ! $this->is_gravityforms_supported( '2.5-beta' ) ? 'true' : 'false',
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Register needed styles.
	 *
	 * @since 4.0
	 *
	 * @return array $styles
	 */
	public function styles() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$styles = array(
			array(
				'handle'  => 'gform_aweber_pluginsettings',
				'src'     => $this->get_base_url() . "/css/plugin_settings{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
			),
		);

		return array_merge( parent::styles(), $styles );

	}




	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Define the settings which should appear on the Forms > Settings > AWeber tab.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		$auth_url   = 'https://auth.aweber.com/1.0/oauth/authorize_app/' . $this->get_app_id();
		$auth_a_tag = sprintf( '<a onclick="window.open(this.href,\'\',\'resizable=yes,location=no,width=750,height=525,status\'); return false" href="%s">', esc_url( $auth_url ) );

		return array(
			array(
				'title'       => esc_html__( 'AWeber Account Information', 'gravityformsaweber' ),
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: 1: Open link tag, 2: Close link tag. */
						esc_html__( 'AWeber is an email marketing software for designers and their clients. Use Gravity Forms to collect customer information and automatically add it to your client\'s AWeber subscription list. If you don\'t have an AWeber account, you can %1$ssign up for one here%2$s', 'gravityformsaweber' ),
						'<a href="http://www.aweber.com" target="_blank">',
						'</a>.'
					)
				),
				'fields'      => array(
					array(
						'name'              => 'authorizationCode',
						'type'              => 'hidden',
						'class'             => 'medium',
						'description'       => esc_html__( 'You can find your unique Authorization code by clicking on the link above and logging into your AWeber account.', 'gravityformsaweber' ),
						'feedback_callback' => array( $this, 'is_valid_key' ),
					),
					array(
						'name'  => 'auth_button',
						'label' => '',
						'type'  => 'oauth_connect_button',
					),
				),
			),
		);

	}

	/**
	 * Initialize Aweber API.
	 *
	 * @since 4.0
	 *
	 * @return boolean|GF_Aweber_API False if the API failed to be initialized, or instance of the API object.
	 */
	public function initialize_api() {
		if ( ! is_null( $this->api ) ) {
			return is_object( $this->api );
		}

		// Load the API library file if necessary.
		if ( ! class_exists( 'GF_Aweber_API' ) ) {
			require_once 'includes/class-gf-aweber-api.php';
		}

		$arg      = false;
		$settings = $this->get_plugin_settings();
		if ( empty( $settings ) ) {
			$this->api = false;

			return false;
		}

		if ( ! empty( $settings['auth_token'] ) ) {
			$arg = $settings['auth_token'];
			if ( is_array( $arg ) && ! empty( $arg['refresh_token'] ) && ! empty( $arg['time_created'] ) ) {
				$arg = $this->maybe_renew_token( $arg );
			}
		} elseif ( ! empty( $settings['authorizationCode'] ) ) {
			$arg = $this->get_aweber_object();
		}

		if ( empty( $arg ) ) {
			$this->api = false;

			return false;
		}

		$this->api = new GF_Aweber_API( $arg );
		$this->log_debug( __METHOD__ . '(): API initialized.' );

		return $this->api;
	}

	/**
	 * Checks if token should be renewed and tries to renew it.
	 *
	 * @since 4.0
	 *
	 * @param array $auth_data The stored authentication data.
	 *
	 * @return false|array False or auth data.
	 */
	public function maybe_renew_token( $auth_data ) {

		if ( time() < ( $auth_data['time_created'] + $auth_data['expires_in'] ) ) {
			return $auth_data;
		}

		// Call the refresh endpoint on Gravity API.
		$args     = array(
			'body' => array(
				'refreshtoken' => $auth_data['refresh_token'],
			),
		);
		$response = wp_remote_post(
			GF_Aweber_API::get_gravity_api_url( 'refresh' ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( __METHOD__ . '(): Unable to refresh token; ' . $response->get_error_message() );
		}

		// Check if the request was successful.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code === 200 ) {
			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $response_body['access_token'] ) && ! empty( $response_body['refresh_token'] ) ) {
				$this->log_debug( __METHOD__ . '(): Token was refreshed successfully.' );

				return $this->update_auth_data( $response_body, true );
			} else {
				$this->log_error( __METHOD__ . '(): Missing auth_payload; ' . print_r( $response, true ) );

				return false;
			}
		}

		// Log that token could not be renewed.
		$details = wp_remote_retrieve_body( $response );
		$this->log_error( __METHOD__ . '(): Unable to refresh token; ' . $details );

		return false;
	}

	/**
	 * Generate HTML for the button to start the OAuth process, or to disconnect an Aweber account.
	 *
	 * @since 4.0
	 *
	 * @param array $field Field settings.
	 * @param bool  $echo  Display field. Defaults to true.
	 *
	 * @return string HTML for the button to start the OAuth process, or to disconnect a Aweber account.
	 */
	public function settings_oauth_connect_button( $field, $echo = true ) {

		// Check if Aweber API is available.
		if ( ! $this->initialize_api() || $this->api->is_legacy_connection() ) {
			if ( ! is_ssl() ) {
				$settings_url = admin_url( 'admin.php?page=gf_settings&subview=' . $this->get_slug(), 'https' );
				$alert_class  = $this->is_gravityforms_supported( '2.5-beta' ) ? 'alert gforms_note_error' : 'alert_red';
				ob_start();
				?>
				<div class="<?php echo esc_attr( $alert_class ); ?>">
					<h4><?php esc_html_e( 'SSL Certificate Required', 'gravityformsaweber' ); ?></h4>
					<?php
					/* translators: 1: Open link tag, 2: Close link tag. */
					printf( esc_html__( 'Make sure you have an SSL certificate installed and enabled, then %1$sclick here to continue%2$s.', 'gravityformsaweber' ), '<a href="' . $settings_url . '">', '</a>' );
					?>
				</div>
				<?php
				$html = ob_get_clean();
			} else {
				// Aweber API not initialized, display OAuth connect button.
				$nonce        = wp_create_nonce( $this->get_authentication_state_action() );
				$settings_url = rawurlencode(
					add_query_arg(
						array(
							'page'    => 'gf_settings',
							'subview' => $this->get_slug(),
						),
						admin_url( 'admin.php' )
					)
				);

				if ( get_transient( "gravityapi_request_{$this->_slug}" ) ) {
					delete_transient( "gravityapi_request_{$this->_slug}" );
				}

				set_transient( "gravityapi_request_{$this->_slug}", $nonce, 10 * MINUTE_IN_SECONDS );

				// Generate a random string and store it in the Add-On settings, it will be returned with the redirect.
				$settings = $this->get_plugin_settings();

				// Load the API library file if necessary.
				if ( ! class_exists( 'GF_Aweber_API' ) ) {
					require_once 'includes/class-gf-aweber-api.php';
				}

				$oauth_url = add_query_arg(
					array(
						'redirect_to' => $settings_url,
						'license'     => GFCommon::get_key(),
						'state'       => $nonce,
					),
					GF_Aweber_API::get_gravity_api_url()
				);

				$connect_button = esc_html__( 'Connect to AWeber', 'gravityformsaweber' );

				$html = sprintf(
					'<a href="%1$s" class="primary button large" id="gform_aweber_connect_button">%2$s</a>',
					$oauth_url,
					/* translators: SVG button connect Aweber account */
					$connect_button
				);
			}
		} else {
			$html = sprintf(
				'<a href="#" class="button" id="gform_aweber_disconnect_button">%1$s</a>',
				esc_html__( 'Disconnect from AWeber', 'gravityformsaweber' )
			);
		}

		$html = sprintf( '<p class="connected_to_aweber_text">%s</p>', $html );

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * When users are redirected back to the website after finishing the onboarding, get the auth tokens.
	 *
	 * @since 4.0
	 */
	public function maybe_update_auth_tokens() {

		$payload = $this->get_oauth_payload();

		if ( ! $payload || $this->is_save_postback() ) {
			return;
		}

		// Verify state.
		if ( rgpost( 'state' ) && ! wp_verify_nonce( rgar( $payload, 'state' ), $this->get_authentication_state_action() ) ) {
			GFCommon::add_error_message( esc_html__( 'Unable to connect to AWeber due to mismatched state.', 'gravityformsaweber' ) );
			return;
		}

		// If error is provided, display message.
		if ( rgpost( 'auth_error' ) || isset( $payload['auth_error'] ) || empty( $payload['auth_payload'] ) ) {
			// Add error message.
			GFCommon::add_error_message( esc_html__( 'Unable to connect your AWeber account.', 'gravityformsaweber' ) );
		}

		$auth_data = json_decode( base64_decode( rgar( $payload, 'auth_payload' ) ), true );
		$code      = rgar( $auth_data, 'code' );
		// If authorization state and code are provided, attempt to create an access token.
		if ( $code && $this->is_plugin_settings( $this->_slug ) ) {

			// Get current plugin settings.
			$settings     = $this->get_plugin_settings();
			$access_token = rgars( $settings, 'auth_token/access_token', '' );
			if ( ( '' === $access_token ) ) {

				if ( ! $this->exchange_code_for_access_token( $code ) ) {
					// Add error message.
					GFCommon::add_error_message( esc_html__( 'Authentication with Aweber was not successful.', 'gravityformsaweber' ) );
					return;
				}

				$this->log_debug( __METHOD__ . '(): Auth code was exchanged for token successfully.' );

				wp_redirect(
					add_query_arg(
						array(
							'page'    => 'gf_settings',
							'subview' => $this->get_slug(),
						),
						admin_url( 'admin.php' )
					)
				);

				exit();
			}
		}
	}

	/**
	 * Get the authorization payload data.
	 *
	 * Returns the auth POST request if it's present, otherwise attempts to return a recent transient cache.
	 *
	 * @since 4.0
	 *
	 * @return array
	 */
	private function get_oauth_payload() {
		$payload = array_filter(
			array(
				'auth_payload' => rgpost( 'auth_payload' ),
				'auth_error'   => rgpost( 'auth_error' ),
				'state'        => rgpost( 'state' ),
			)
		);

		if ( count( $payload ) === 2 || isset( $payload['auth_error'] ) ) {
			return $payload;
		}

		$payload = get_transient( "gravityapi_response_{$this->_slug}" );

		if ( rgar( $payload, 'state' ) !== get_transient( "gravityapi_request_{$this->_slug}" ) ) {
			return array();
		}

		delete_transient( "gravityapi_response_{$this->_slug}" );

		return is_array( $payload ) ? $payload : array();
	}

	/**
	 * Get action name for authentication state.
	 *
	 * @since 4.0
	 *
	 * @return string
	 */
	public function get_authentication_state_action() {

		return 'gform_aweber_authentication_state';

	}

	/**
	 * Adds a warning message if the add-on is still connected using OAuth 1.
	 *
	 * @since 4.0
	 */
	public function maybe_show_authentication_warning() {

		$settings = $this->get_plugin_settings();

		if ( empty( $settings ) || version_compare( rgar( $settings, 'reauth_version' ), self::LAST_REAUTHENTICATION_VERSION, '>=' ) ) {
			return;
		};

		$message = sprintf(
			/* translators: 1: Open link tag, 2: Close link tag. */
			esc_html__( 'You are connected to AWeber using an authentication method that might be deprecated soon, please connect using the new authentication method to avoid any foreseeable issues. %1$sLearn more.%2$s', 'gravityformsaweber' ),
			'<a target="_blank" href="https://docs.gravityforms.com/category/add-ons-gravity-forms/aweber-add-on">',
			'</a>'
		);
		echo sprintf( '<div class="notice notice-error gf-notice"><p>%s</p></div>', $message );

	}

	/**
	 * Exchange code for access token and refresh token.
	 *
	 * @since 4.0
	 *
	 * @param string $code code provided by Aweber API to exchange for access token.
	 *
	 * @return boolean true if tokens successfully saved.
	 */
	private function exchange_code_for_access_token( $code = '' ) {

		// Load the API library file if necessary.
		if ( ! class_exists( 'GF_Aweber_API' ) ) {
			require_once 'includes/class-gf-aweber-api.php';
		}

		$redirect_url = GF_Aweber_API::get_gravity_api_url( '/code' );

		$response = wp_remote_post(
			$redirect_url,
			array( 'body' => array( 'code' => $code ) )
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( __METHOD__ . '(): Exchange of code for tokens returned error: ' . $response->get_error_message() );

			return false;
		}

		// Save new access token.
		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		if ( ! $response_data ) {
			$this->log_error( __METHOD__ . '(): Request to exchange code for tokens returned no data.' );

			return false;
		}

		if ( ! rgempty( 'error', $response_data ) ) {
			$this->log_error( __METHOD__ . '(): Request to exchange code for tokens returned error: ' . $response_data['error_description'] );

			return false;
		}

		// If access and refresh token are provided, store in settings.
		if ( ! rgempty( 'access_token', $response_data ) && ! rgempty( 'refresh_token', $response_data ) ) {
			$this->update_auth_data( $response_data );
			return true;
		}

		// Gravity API returned no tokens.
		$this->log_error( __METHOD__ . '(): Request to exchange code for tokens returned no tokens.' );

		return false;
	}

	/**
	 * Updates the stored authentication data with a new set of tokens
	 *
	 * @since 4.0
	 *
	 * @param array $new_auth_data The new tokens.
	 *
	 * @return array The updated auth data.
	 */
	public function update_auth_data( $new_auth_data, $is_refresh = false ) {
			$settings = $this->get_plugin_settings();

			$auth_data = array(
				'access_token'  => rgar( $new_auth_data, 'access_token' ),
				'refresh_token' => rgar( $new_auth_data, 'refresh_token' ),
				'expires_in'    => rgar( $new_auth_data, 'expires_in' ),
				'time_created'  => time(),
			);

			$settings['auth_token'] = $auth_data;
			// aweber_state was for one time use only.
			unset( $settings['aweber_state'] );

			// Set the API authentication version.
			if ( ! $is_refresh ) {
				$settings['reauth_version'] = self::LAST_REAUTHENTICATION_VERSION;
			}

			// Save plugin settings.
			$this->update_plugin_settings( $settings );

			return $auth_data;
	}

	/**
	 * Revoke refresh token and remove tokens from Settings. Then send JSON error object or { 'success' => true } .
	 *
	 * @since 4.0
	 */
	public function ajax_deauthorize() {
		check_ajax_referer( 'gf_aweber_ajax', 'nonce' );

		if ( ! GFCommon::current_user_can_any( $this->_capabilities_settings_page ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformsaweber' ) ) );
		}

		// If API not available return empty array.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to get methods because API is not initialized.' );
			wp_send_json_error( array( 'message' => esc_html__( 'Unable to deauthorize because API could not be initialized.', 'gravityformsaweber' ) ) );
		}

		$result = $this->api->revoke_refresh_token();

		if ( is_wp_error( $result ) ) {
			$this->log_error( __METHOD__ . '(): Unable to revoke refresh token; ' . $result->get_error_message() );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Call parent method to prevent adding back of tokens.
		parent::update_plugin_settings( array() );

		GFCache::delete( 'aweber_accounts' );

		wp_send_json_success();
	}

	/**
	 * Returns the AWeber app id to be used when authorizing the add-on with AWeber.
	 *
	 * @since 2.7.1
	 *
	 * @return string
	 */
	public function get_app_id() {

		/**
		 * Allows a custom AWeber app id to be defined for use when authorizing the add-on with AWeber.
		 *
		 * @since 2.7.1
		 *
		 * @param string $app_id The AWeber app id.
		 */
		$app_id = apply_filters( 'gform_aweber_app_id', '2ad0d7d5' );

		return $app_id;
	}

	/**
	 * Migrate the plugin settings.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array $settings Plugin settings.
	 *
	 * @uses   GFAddOn::get_plugin_settings()
	 * @uses   GFAWeber::get_aweber_tokens()
	 */
	public function update_plugin_settings( $settings ) {

		if ( rgblank( rgar( $settings, 'authorizationCode' ) ) ) {
			parent::update_plugin_settings( $settings );
			return;
		}
		$saved_settings  = $this->get_plugin_settings();
		$requires_tokens = empty( $saved_settings['access_token'] ) || $saved_settings['authorizationCode'] != $settings['authorizationCode'];

		if ( $requires_tokens ) {
			$aweber_token                    = $this->get_aweber_tokens( $settings['authorizationCode'] );
			$settings['access_token']        = $aweber_token['access_token'];
			$settings['access_token_secret'] = $aweber_token['access_token_secret'];
		} else {
			$settings['access_token']        = $saved_settings['access_token'];
			$settings['access_token_secret'] = $saved_settings['access_token_secret'];
		}

		parent::update_plugin_settings( $settings );

	}





	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAWeber::create_list_field_map()
	 * @uses   GFAWeber::get_aweber_accounts()
	 * @uses   GFAWeber::is_accounts_hidden()
	 *
	 * @return array
	 */
	public function feed_settings_fields() {

		return array(
			array(
				'title'       => esc_html__( 'AWeber Feed', 'gravityformsaweber' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'gravityformsaweber' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Name', 'gravityformsaweber' ),
							esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformsaweber' )
						),
					),
					array(
						'name'          => 'account',
						'label'         => esc_html__( 'Account', 'gravityformsaweber' ),
						'type'          => 'select',
						'onchange'      => 'jQuery(this).parents("form").submit();',
						'hidden'        => $this->is_accounts_hidden(),
						'choices'       => $this->get_aweber_accounts(),
						'default_value' => $this->get_default_account(),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Account', 'gravityformsaweber' ),
							esc_html__( 'Select the AWeber account you would like to add your contacts to.', 'gravityformsaweber' )
						),
					),
					array(
						'name'       => 'contactList',
						'label'      => esc_html__( 'Contact List', 'gravityformsaweber' ),
						'type'       => 'select',
						'choices'    => $this->get_lists_as_choices(),
						'onchange'   => 'jQuery(this).parents("form").submit();',
						'dependency' => array( $this, 'has_selected_account' ),
						'no_choices' => esc_html__( 'Unable to get lists.', 'gravityformsaweber' ),
						'required'   => true,
						'tooltip'    => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Contact List', 'gravityformsaweber' ),
							esc_html__( 'Select the AWeber list you would like to add your contacts to.', 'gravityformsaweber' )
						),
					),
					array(
						'name'       => 'listFields',
						'label'      => esc_html__( 'Map Fields', 'gravityformsaweber' ),
						'type'       => 'field_map',
						'dependency' => 'contactList',
						'field_map'  => $this->create_list_field_map(),
						'tooltip'    => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Map Fields', 'gravityformsaweber' ),
							esc_html__( 'Associate your AWeber fields to the appropriate Gravity Form fields by selecting the appropriate form field from the list.', 'gravityformsaweber' )
						),
					),
					array(
						'name'    => 'tags',
						'type'    => 'text',
						'dependency' => 'contactList',
						'class'   => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label'   => esc_html__( 'Tags', 'gravityformsaweber' ),
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Tags', 'gravityformsaweber' ),
							esc_html__( 'Associate tags to your AWeber subscribers with a comma separated list. (e.g. new lead, Gravity Forms, web source)', 'gravityformsaweber' )
						),
					),
					array(
						'name'       => 'optin',
						'label'      => esc_html__( 'Conditional Logic', 'gravityformsaweber' ),
						'type'       => 'feed_condition',
						'dependency' => 'contactList',
						'tooltip'    => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'gravityformsaweber' ),
							esc_html__( 'When conditional logic is enabled, form submissions will only be exported to AWeber when the condition is met. When disabled all form submissions will be exported.', 'gravityformsaweber' )
						),
					),
				),
			),
		);

	}

	/**
	 * Check if the account setting should be displayed.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAddOn::get_setting()
	 * @uses   GFAWeber::get_default_account()
	 * @uses   GFAWeber::has_multiple_accounts()
	 * @uses   GFAWeber::is_valid_account_id()
	 *
	 * @return bool
	 */
	public function is_accounts_hidden() {

		// Get account ID.
		$account_id = $this->get_setting( 'account', $this->get_default_account() );

		if ( ( ! empty( $account_id ) && ! $this->is_valid_account_id( $account_id ) ) || $this->has_multiple_accounts() ) {
			return false;
		}

		return true;

	}

	/**
	 * Has a choice been selected for the account setting?
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAddOn::get_setting()
	 * @uses   GFAWeber::get_default_account()
	 * @uses   GFAWeber::is_valid_account_id()
	 *
	 * @return bool
	 */
	public function has_selected_account() {

		// Get account ID.
		$account_id = $this->get_setting( 'account', $this->get_default_account() );

		return $this->is_valid_account_id( $account_id );

	}

	/**
	 * If there are multiple AWeber accounts, return an array of choices for the account setting.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAWeber::get_accounts()
	 * @uses   GFAWeber::has_multiple_accounts()
	 * @uses   GFAWeber::is_valid_account_id()
	 *
	 * @return array
	 */
	public function get_aweber_accounts() {

		// Get AWeber accounts.
		$aweber_accounts = $this->get_accounts();

		// If no accounts were found, return.
		if ( empty( $aweber_accounts['entries'] ) ) {
			return array();
		}

		// Get account ID.
		$account_id = $this->get_setting( 'account' );

		// Initialize choices array.
		$choices = array();

		// Add initial choice.
		if ( ( ! empty( $account_id ) && ! $this->is_valid_account_id( $account_id ) ) || $this->has_multiple_accounts() ) {

			$choices[] = array(
				'label' => esc_html__( 'Select Account', 'gravityformsaweber' ),
				'value' => '',
			);

		}

		// Loop through accounts.
		foreach ( $aweber_accounts['entries'] as $account ) {

			// Add account as choice.
			$choices[] = array(
				'label' => esc_html( rgar( $account, 'id' ) ),
				'value' => esc_attr( rgar( $account, 'id' ) ),
			);

		}

		return $choices;

	}

	/**
	 * Returns AWeber lists as a collection of drop down choices.
	 *
	 * @since  2.11
	 * @access public
	 *
	 * @return array
	 */
	public function get_lists_as_choices() {

		// Get account ID.
		$account_id = $this->get_setting( 'account', $this->get_default_account() );

		// If account ID is invalid, return.
		if ( ! $this->is_valid_account_id( $account_id ) ) {
			$this->log_error( __METHOD__ . '(): Invalid account ID: ' . $account_id );

			return array();
		}

		$cache_key = sprintf( 'aweber_account_%d_lists', $account_id );

		$lists = GFCache::get( $cache_key );

		if ( empty( $lists ) ) {
			if ( ! $this->initialize_api() ) {
				return array();
			}

			$lists = $this->api->get_lists( $account_id );
			if ( is_wp_error( $lists ) ) {
				$this->log_error( __METHOD__ . sprintf( '(): Unable to get lists for account ID: %d; code: %d; message: %s', $account_id, $lists->get_error_code(), $lists->get_error_message() ) );

				return array();
			}

			GFCache::set( $cache_key, $lists, true, HOUR_IN_SECONDS );
		}

		if ( empty( $lists['entries'] ) ) {
			$this->log_error( __METHOD__ . '(): No lists returned for account ID: ' . $account_id );

			return array();
		}

		// Add initial choice.
		$choices = array(
			array(
				'label' => esc_html__( 'Select List', 'gravityformsaweber' ),
				'value' => '',
			),
		);

		// Loop through lists.
		foreach ( $lists['entries'] as $list ) {

			// Add list as choice.
			$choices[] = array(
				'label' => esc_html( rgar( $list, 'name' ) ),
				'value' => esc_attr( rgar ( $list, 'id' ) ),
			);

		}

		return $choices;

	}

	/**
	 * Return an array of AWeber fields which can be mapped to the Form fields/entry meta.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAddOn::get_setting()
	 * @uses   GFAWeber::get_custom_fields()
	 * @uses   GFAWeber::is_valid_account_id()
	 *
	 * @return array
	 */
	public function create_list_field_map() {

		// Get account and list IDs.
		$account_id = $this->get_setting( 'account' );
		$list_id    = $this->get_setting( 'contactList' );

		// If no list is selected or the account ID is invalid, return.
		if ( empty( $list_id ) || ! $this->is_valid_account_id( $account_id ) ) {
			return array();
		}

		return $this->get_custom_fields( $list_id, $account_id );

	}

	/**
	 * Prevent feeds being listed or created if the AWeber auth code isn't valid.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAWeber::is_valid_key()
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		return $this->is_valid_key();

	}

	/**
	 * Enable feed duplication.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {

		return true;

	}





	// # FEED LIST -----------------------------------------------------------------------------------------------------

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feedName'    => esc_html__( 'Name', 'gravityformsaweber' ),
			'account'     => esc_html__( 'AWeber Account', 'gravityformsaweber' ),
			'contactList' => esc_html__( 'AWeber List', 'gravityformsaweber' ),
		);

	}

	/**
	 * Returns the value to be displayed in the AWeber Account column.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array $feed The current Feed object.
	 *
	 * @uses   GFAWeber::is_valid_account_id()
	 *
	 * @return string
	 */
	public function get_column_value_account( $feed ) {

		// Get account ID.
		$account_id = rgars( $feed, 'meta/account' );

		return $this->is_valid_account_id( $account_id ) ? esc_html( $account_id ) : esc_html__( 'Invalid ID', 'gravityformsaweber' );

	}

	/**
	 * Returns the value to be displayed in the AWeber List column.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array $feed The current Feed Object.
	 *
	 * @uses   AWeberAPIBase::loadFromUrl()
	 * @uses   GFAWeber::get_aweber_object()
	 * @uses   GFAWeber::is_valid_account_id()
	 *
	 * @return string
	 */
	public function get_column_value_contactList( $feed ) {

		// Get account and list IDs.
		$account_id = rgars( $feed, 'meta/account' );
		$list_id    = rgars( $feed, 'meta/contactList' );

		if ( empty( $account_id ) || empty( $list_id ) ) {
			return '';
		}

		$cache_key = sprintf( 'aweber_account_%d_list_%d', $account_id, $list_id );
		$list      = GFCache::get( $cache_key );

		if ( empty( $list ) ) {
			$list = $this->api->get_list( $account_id, $list_id );
			if ( is_wp_error( $list ) || empty( $list ) ) {
				return $list_id . ' (' . esc_html__( 'List not found in AWeber', 'gravityformsaweber' ) . ')';
			}

			GFCache::set( $cache_key, $list, true, HOUR_IN_SECONDS );
		}

		return rgar( $list, 'name' );

	}






	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process the feed, subscribe the user to the AWeber list.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param array $feed  The current Feed object.
	 * @param array $entry The current Entry object.
	 * @param array $form  The current Form object.
	 *
	 * @uses   AWeberAPIBase::loadFromUrl()
	 * @uses   GFAddOn::get_field_map_fields()
	 * @uses   GFAddOn::get_field_value()
	 * @uses   GFAddOn::log_debug
	 * @uses   GFAWeber::get_aweber_object()
	 * @uses   GFAWeber::get_custom_fields()
	 * @uses   GFAWeber::is_valid_account_id()
	 * @uses   GFAWeber::is_valid_key()
	 * @uses   GFCommon::is_invalid_or_empty_email()
	 * @uses   GFFeedAddOn::add_feed_error()
	 *
	 * @return array
	 */
	public function process_feed( $feed, $entry, $form ) {

		// If API credentials are invalid, exit.
		if ( ! $this->is_valid_key() ) {
			$this->add_feed_error( esc_html__( 'Unable to subscribe user because API could not be initialized.', 'gravityformsaweber' ), $feed, $entry, $form );

			return $entry;
		}

		// Get email address.
		$email = $this->get_field_value( $form, $entry, rgars( $feed, 'meta/listFields_email' ) );

		// If email address is invalid, exit.
		if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
			$this->add_feed_error( esc_html__( 'Unable to subscribe user because email address was invalid.', 'gravityformsaweber' ), $feed, $entry, $form );

			return $entry;
		}

		// Get account ID.
		$account_id = rgars( $feed, 'meta/account' );

		// If account ID is invalid, exit.
		if ( ! $this->is_valid_account_id( $account_id ) ) {
			$this->add_feed_error( esc_html__( 'Unable to subscribe user because account ID was invalid.', 'gravityformsaweber' ), $feed, $entry, $form );

			return $entry;
		}

		// Get list ID.
		$list_id = rgars( $feed, 'meta/contactList' );

		// Prepare merge vars array.
		$merge_vars = array( '' );

		// Add field map fields to merge vars array.
		$field_maps = $this->get_field_map_fields( $feed, 'listFields' );
		foreach ( $field_maps as $var_tag => $field_id ) {
			$merge_vars[ $var_tag ] = $this->get_field_value( $form, $entry, $field_id );
		}

		// Get custom fields.
		$custom_fields = $this->get_custom_fields( $list_id, $account_id );

		// Removing email and full name from list of custom fields as they are handled separately.
		unset( $custom_fields[0] );
		unset( $custom_fields[1] );
		$custom_fields = array_values( $custom_fields );

		// Add custom fields.
		$list_custom_fields = array();
		foreach ( $custom_fields as $cf ) {
			$key                                = $cf['name'];
			$list_custom_fields[ $cf['label'] ] = (string) $merge_vars[ $key ];
		}

		// Prepare subscriber arguments.
		$params = array(
			'email'       => $email,
			'name'        => $this->get_field_value( $form, $entry, rgars( $feed, 'meta/listFields_fullname' ) ),
			'ad_tracking' => gf_apply_filters( 'gform_aweber_ad_tracking', $form['id'], $form['title'], $entry, $form, $feed ),
		);

		// If custom fields were found, add to subscriber arguments.
		if ( ! empty( $list_custom_fields ) ) {
			$params['custom_fields'] = $list_custom_fields;
		}

		// Ad tracking has a max size of 20 characters.
		if ( strlen( $params['ad_tracking'] ) > 20 ) {
			$params['ad_tracking'] = substr( $params['ad_tracking'], 0, 20 );
		}

		// Get tags.
		$tags = explode(',', rgars( $feed, 'meta/tags' ) );
		$tags = array_map( 'trim', $tags );

		// Prepare tags.
		if ( ! empty( $tags ) ) {

			// Loop through tags, replace merge tags.
			foreach ( $tags as &$tag ) {
				$tag = GFCommon::replace_variables( $tag, $form, $entry, false, false, false, 'text' );
				$tag = trim( $tag );
			}

			// Clean array of duplicate and empty items.
			$tags = array_values( array_unique( array_filter( $tags ) ) );

		}

		// Add tags.
		if ( ! empty( $tags ) ) {
			$params['tags'] = $tags;
		}

		$params = gf_apply_filters( 'gform_aweber_args_pre_subscribe', $form['id'], $params, $form, $entry, $feed );

		$this->log_debug( __METHOD__ . sprintf( '(): Adding subscriber to list #%d on account #%d: ', $list_id, $account_id ) . print_r( $params, true ) );

		$subscriber = $this->api->add_subscriber( $account_id, $list_id, $params );

		if ( is_wp_error( $subscriber ) ) {
			$this->add_feed_error( sprintf( 'Unable to add subscriber; code: %d; message: %s', $subscriber->get_error_code(), $subscriber->get_error_message() ), $feed, $entry, $form );

			return $entry;
		}

		$this->log_debug( __METHOD__ . '(): Subscriber successfully added. ' . print_r( $subscriber, true ) );

		/**
		 * Perform a custom action when a subscriber is successfully added to the list.
		 *
		 * @since 2.4.1
		 *
         * @param array $subscriber The subscriber properties.
		 * @param array $form       The form currently being processed.
		 * @param array $entry      The entry currently being processed.
		 * @param array $feed       The feed currently being processed.
		 */
		do_action( 'gform_aweber_post_subscriber_created', $subscriber, $form, $entry, $feed );

		return $entry;

	}

	/**
	 * Use the legacy gform_aweber_field_value filter instead of the framework gform_SLUG_field_value filter.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string $field_value The field value.
	 * @param array  $form        The current Form object.
	 * @param array  $entry       The current Entry object.
	 * @param string $field_id    The ID of the field being processed.
	 *
	 * @return string
	 */
	public function maybe_override_field_value( $field_value, $form, $entry, $field_id ) {

		return gf_apply_filters( 'gform_aweber_field_value', array(
			$form['id'],
			$field_id,
		), $field_value, $form['id'], $field_id, $entry );

	}





	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Return the ID of the default account.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAWeber::get_accounts()
	 *
	 * @return string
	 */
	public function get_default_account() {
		$accounts = $this->get_accounts();

		if ( is_wp_error( $accounts ) ) {
			return '';
		}

		return rgars( $accounts, 'entries/0/id', '' );
	}

	/**
	 * Check if the account ID is valid.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string $account_id The AWeber account ID.
	 *
	 * @uses   GFAWeber::get_accounts()
	 *
	 * @return bool
	 */
	public function is_valid_account_id( $account_id ) {

		// If account ID is empty, return.
		if ( empty( $account_id ) ) {
			return false;
		}

		// Get AWeber accounts.
		$accounts = $this->get_accounts();

		if ( is_wp_error( $accounts ) || empty( $accounts['entries'] ) ) {
			return false;
		}

		foreach ( $accounts['entries'] as $account ) {

			// If this is the account we are validating, return.
			if ( $account_id == $account['id'] ) {
				return true;
			}

		}

		return false;

	}

	/**
	 * Do multiple accounts exist?
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAWeber::get_accounts()
	 *
	 * @return bool
	 */
	public function has_multiple_accounts() {

		// Get AWeber accounts.
		$accounts = $this->get_accounts();

		// If only one account was found, return.
		if ( is_wp_error( $accounts ) || rgar( $accounts, 'total_size' ) == 1 ) {
			return false;
		}

		return true;

	}

	/**
	 * Return the AWeber accounts.
	 *
	 * @since Unknown
	 * @since 4.0     Updated to use GF_Aweber_API.
	 *
	 * @return array
	 */
	private function get_accounts() {
		$cache_key = 'aweber_accounts';
		$accounts  = GFCache::get( $cache_key );

		if ( $accounts ) {
			return $accounts;
		}

		if ( ! $this->initialize_api() ) {
			return array();
		}

		$accounts = $this->api->get_accounts();;

		if ( empty( $accounts ) ) {
			return array();
		}

		if ( is_wp_error( $accounts ) ) {
			$this->log_error( __METHOD__ . sprintf( '(): Unable to retrieve AWeber accounts; code: %d; message: %s', $accounts->get_error_code(), $accounts->get_error_message() ) );

			return array();
		}

		$this->log_debug( __METHOD__ . '(): Retrieved AWeber accounts => ' . print_r( $accounts, true ) );

		GFCache::set( $cache_key, $accounts, true, HOUR_IN_SECONDS );

		return $accounts;

	}

	/**
	 * Return an array of AWeber fields for the specified list.
	 *
	 * @since Unknown
	 * @since 4.0     Updated to use GF_Aweber_API.
	 *
	 * @param string $list_id    The AWeber list ID.
	 * @param string $account_id The AWeber account ID.
	 *
	 * @return array
	 */
	public function get_custom_fields( $list_id, $account_id ) {

		// Initialize default custom field choices.
		$custom_fields = array(
			array(
				'label'      => esc_html__( 'Email Address', 'gravityformsaweber' ),
				'name'       => 'email',
				'required'   => true,
				'field_type' => array( 'email', 'hidden' ),
			),
			array(
				'label' => esc_html__( 'Full Name', 'gravityformsaweber' ),
				'name'  => 'fullname',
			),
		);

		$cache_key            = sprintf( 'aweber_account_%d_list_%d_custom_fields', $account_id, $list_id );
		$aweber_custom_fields = GFCache::get( $cache_key );

		if ( empty( $aweber_custom_fields ) ) {
			if ( ! $this->initialize_api() ) {
				return $custom_fields;
			}

			$aweber_custom_fields = $this->api->get_custom_fields( $account_id, $list_id );
			if ( is_wp_error( $aweber_custom_fields ) ) {
				$this->log_error( __METHOD__ . sprintf( '(): Unable to retrieve custom fields; code: %d; message: %s', $aweber_custom_fields->get_error_code(), $aweber_custom_fields->get_error_message() ) );

				return $custom_fields;
			}

			GFCache::set( $cache_key, $aweber_custom_fields, true, HOUR_IN_SECONDS );
		}

		if ( empty( $aweber_custom_fields['entries'] ) ) {
			return $custom_fields;
		}

		// Loop through custom fields.
		foreach ( $aweber_custom_fields['entries'] as $cf ) {

			// If custom field name or ID is empty, skip.
			if ( empty( $cf['name'] ) || empty( $cf['id'] ) ) {
				continue;
			}

			// Add custom field to array.
			$custom_fields[] = array(
				'label' => esc_html( $cf['name'] ),
				'name'  => esc_attr( 'cf_' . $cf['id'] ),
			);

		}

		return $custom_fields;

	}

	/**
	 * Return the AWeber tokens.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string $api_credentials AWeber API credentials.
	 *
	 * @uses   AWeberAPI::getAccessToken()
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFAddOn::log_error()
	 * @uses   GFAWeber::include_api()
	 *
	 * @return array
	 */
	public function get_aweber_tokens( $api_credentials = '' ) {

		// Include AWeber API library.
		$this->include_api();

		// Separate API credentials.
		list( $application_key, $application_secret, $request_token, $request_token_secret, $oauth_verifier ) = rgexplode( '|', $api_credentials, 5 );

		// Log that we are getting authentication tokens.
		$this->log_debug( __METHOD__ . "(): Getting tokens for key {$application_key}" );

		$aweber                     = new AWeberAPI( $application_key, $application_secret );
		$aweber->user->tokenSecret  = $request_token_secret;
		$aweber->user->requestToken = $request_token;
		$aweber->user->verifier     = $oauth_verifier;

		$access_token        = '';
		$access_token_secret = '';

		try {
			$this->log_debug( __METHOD__ . '(): Getting tokens.' );
			list( $access_token, $access_token_secret ) = $aweber->getAccessToken();
		} catch ( AWeberException $e ) {
			$this->log_error( __METHOD__ . "(): Unable to retrieve tokens: {$e}" );
		}

		return array( 'access_token' => $access_token, 'access_token_secret' => $access_token_secret );

	}

	/**
	 * Validates the API connection by getting the default account.
	 *
	 * @since Unknown
	 * @since 4.0     Updated to use GF_Aweber_API.
	 *
	 * @return bool
	 */
	public function is_valid_key() {
		static $result;

		if ( is_bool( $result ) ) {
			return $result;
		}

		if ( ! $this->initialize_api() ) {
			$result = false;

			return false;
		}

		$this->log_debug( __METHOD__ . '(): Validating API connection.' );

		if ( ! $this->get_default_account() ) {
			$this->log_error( __METHOD__ . '(): Unable to validate connection.' );
			$result = false;

			return false;
		}

		$this->log_debug( __METHOD__ . '(): API connection is valid.' );
		$result = true;

		return true;

	}

	/**
	 * Return the AWeberAPI object.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   AWeberAPI
	 * @uses   GFAWeber::get_access_token()
	 * @uses   GFAWeber::get_access_token_secret()
	 * @uses   GFAWeber::get_api_tokens()
	 * @uses   GFAWeber::include_api()
	 *
	 * @return AWeberAPI|bool
	 */
	public function get_aweber_object() {

		// Include AWeber API library.
		$this->include_api();

		// Get API tokens.
		$tokens = $this->get_api_tokens();

		// If tokens are empty, return.
		if ( empty( $tokens['application_key'] ) && empty( $tokens['application_secret'] ) && empty( $tokens['request_token'] ) && empty( $tokens['oauth_verifier'] ) ) {
			return false;
		}

		// Initialize new AWeber API object.
		$aweber = new AWeberAPI( $tokens['application_key'], $tokens['application_secret'] );

		// Assign AWeber credentials to object.
		$aweber->user->requestToken = $tokens['request_token'];
		$aweber->user->verifier     = $tokens['oauth_verifier'];
		$aweber->user->accessToken  = $this->get_access_token();
		$aweber->user->tokenSecret  = $this->get_access_token_secret();

		return $aweber;

	}

	/**
	 * Return the API tokens.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAddOn::get_plugin_setting()
	 *
	 * @return array
	 */
	public function get_api_tokens() {

		// Get API credentials.
		$api_credentials = $this->get_plugin_setting( 'authorizationCode' );

		// Separate token details.
		list( $application_key, $application_secret, $request_token, $request_token_secret, $oauth_verifier ) = rgexplode( '|', $api_credentials, 5 );

		return array(
			'application_key'      => $application_key,
			'application_secret'   => $application_secret,
			'request_token'        => $request_token,
			'request_token_secret' => $request_token_secret,
			'oauth_verifier'       => $oauth_verifier,
		);

	}

	/**
	 * Return the value of the access_token setting.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAddOn::get_plugin_setting()
	 *
	 * @return string
	 */

	public function get_access_token() {

		return $this->get_plugin_setting( 'access_token' );

	}

	/**
	 * Return the value of the access_token_secret setting.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAddOn::get_plugin_setting()
	 *
	 * @return string
	 */
	public function get_access_token_secret() {

		return $this->get_plugin_setting( 'access_token_secret' );

	}

	/**
	 * Include the AWeber API.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAddOn::get_base_path()
	 */
	public function include_api() {

		if ( ! class_exists( 'AWeberServiceProvider' ) ) {
			require_once $this->get_base_path() . '/includes/autoload.php';
		}

	}



	// # UPGRADES ------------------------------------------------------------------------------------------------------

	/**
	 * Checks if a previous version was installed and if the feeds need migrating to the framework structure.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string $previous_version The version number of the previously installed version.
	 *
	 * @uses   GFAddOn::update_plugin_settings()
	 * @uses   GFAWeber::get_old_feeds()
	 * @uses   GFAWeber::update_paypal_delay_settings()
	 * @uses   GFFeedAddOn::insert_feed()
	 */
	public function upgrade( $previous_version ) {

		// If previous version is empty, check legacy option.
		$previous_version = empty( $previous_version ) ? get_option( 'gf_aweber_version' ) : $previous_version;

		// Determine if previous version is before the Add-On Framework update.
		$previous_is_pre_addon_framework = ! empty( $previous_version ) && version_compare( $previous_version, '2.0.dev1', '<' );

		// If previous version is not before the Add-On Framework update, exit.
		if ( ! $previous_is_pre_addon_framework ) {
			return;
		}

		// Get old feeds.
		$old_feeds = $this->get_old_feeds();

		// If no old feeds were found, exit.
		if ( ! $old_feeds ) {
			return;
		}

		// Loop through old feeds.
		foreach ( $old_feeds as $i => $old_feed ) {

			// Prepare feed name.
			$feed_name = 'Feed ' . ( $i + 1 );

			// Get feed form ID and active state.
			$form_id   = $old_feed['form_id'];
			$is_active = $old_feed['is_active'];

			// Initialize feed meta array.
			$new_meta = array(
				'feedName'    => $feed_name,
				'account'     => rgar( $old_feed['meta'], 'client_id' ),
				'contactList' => rgar( $old_feed['meta'], 'contact_list_id' ),
			);

			// Migrate field mapping.
			foreach ( $old_feed['meta']['field_map'] as $var_tag => $field_id ) {
				$new_meta[ 'listFields_' . $var_tag ] = $field_id;
			}

			// Migrate Opt-In condition.
			if ( rgars( $old_feed, 'meta/optin_enabled' ) ) {

				$new_meta['feed_condition_conditional_logic']        = 1;
				$new_meta['feed_condition_conditional_logic_object'] = array(
					'conditionalLogic' => array(
						'actionType' => 'show',
						'logicType'  => 'all',
						'rules'      => array(
							array(
								'fieldId'  => $old_feed['meta']['optin_field_id'],
								'operator' => $old_feed['meta']['optin_operator'],
								'value'    => $old_feed['meta']['optin_value'],
							),
						),
					),
				);

			} else {

				$new_meta['feed_condition_conditional_logic'] = 0;

			}

			// Insert new feed.
			$this->insert_feed( $form_id, $is_active, $new_meta );

		}

		// Get old plugin settings.
		$old_settings = get_option( 'gf_aweber_settings' );

		// Prepare new plugin settings.
		$new_settings = array(
			'authorizationCode'   => $old_settings['api_credentials'],
			'access_token'        => $old_settings['access_token'],
			'access_token_secret' => $old_settings['access_token_secret'],
		);

		// Save plugin settings.
		parent::update_plugin_settings( $new_settings );

		// Set PayPal delay setting.
		$this->update_paypal_delay_settings( 'delay_aweber_subscription' );

	}

	/**
	 * Migrate the delayed payment setting for the PayPal Add-On integration.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @param string $old_delay_setting_name
	 *
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFAWeber::get_old_paypal_feeds()
	 * @uses   GFFeedAddOn::get_feeds_by_slug()
	 * @uses   GFFeedAddOn::update_feed_meta()
	 * @uses   wpdb::update()
	 */
	public function update_paypal_delay_settings( $old_delay_setting_name ) {

		global $wpdb;

		// Log that we are updating PayPal delay settings.
		$this->log_debug( __METHOD__ . '(): Checking to see if there are any delay settings that need to be migrated for PayPal Standard.' );

		// Prepare new PayPal delay setting name.
		$new_delay_setting_name = 'delay_' . $this->_slug;

		// Get PayPal feeds from old table.
		$paypal_feeds_old = $this->get_old_paypal_feeds();

		// Loop through feeds and look for delay setting and create duplicate with new delay setting for the non-framework version of PayPal Standard.
		if ( ! empty( $paypal_feeds_old ) ) {

			// Log that old feeds were found.
			$this->log_debug( __METHOD__ . '(): Old feeds found for ' . $this->_slug . ' - copying over delay settings.' );

			// Loop through feeds.
			foreach ( $paypal_feeds_old as $old_feed ) {

				// Get old feed meta.
				$meta = $old_feed['meta'];

				// If PayPal delay setting was not found, skip.
				if ( rgempty( $old_delay_setting_name, $meta ) ) {
					continue;
				}

				// Copy delay meta.
				$meta[ $new_delay_setting_name ] = $meta[ $old_delay_setting_name ];

				// Serialize meta.
				$meta = maybe_serialize( $meta );

				// Update PayPal meta.
				$wpdb->update( "{$wpdb->prefix}rg_paypal", array( 'meta' => $meta ), array( 'id' => $old_feed['id'] ), array( '%s' ), array( '%d' ) );

			}

		}

		// Get PayPal feeds from new framework table.
		$paypal_feeds = $this->get_feeds_by_slug( 'gravityformspaypal' );

		// Loop through feeds and look for delay setting and create duplicate with new delay setting for the framework version of PayPal Standard.
		if ( ! empty( $paypal_feeds ) ) {

			// Log that new PayPal feeds were found.
			$this->log_debug( __METHOD__ . '(): New feeds found for ' . $this->_slug . ' - copying over delay settings.' );

			// Loop through feeds.
			foreach ( $paypal_feeds as $feed ) {

				// Get feed meta.
				$meta = $feed['meta'];

				// If PayPal delay setting was not found, skip.
				if ( rgempty( $old_delay_setting_name, $meta ) ) {
					continue;
				}

				// Copy delay meta.
				$meta[ $new_delay_setting_name ] = $meta[ $old_delay_setting_name ];

				// Update feed.
				$this->update_feed_meta( $feed['id'], $meta );

			}

		}

	}

	/**
	 * Retrieve any old PayPal feeds.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFAddOn::table_exists()
	 * @uses   GFFormsModel::get_form_table_name()
	 * @uses   wpdb::get_results()
	 *
	 * @return bool|array
	 */
	public function get_old_paypal_feeds() {

		global $wpdb;

		// Prepare table name.
		$table_name = $wpdb->prefix . 'rg_paypal';

		// If table does not exist, return.
		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		// Prepare SQL statement.
		$form_table_name = GFFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
				FROM {$table_name} s
				INNER JOIN {$form_table_name} f ON s.form_id = f.id";

		// Log SQL statement.
		$this->log_debug( __METHOD__ . "(): getting old paypal feeds: {$sql}" );

		// Get PayPal feeds.
		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Log SQL error.
		$this->log_debug( __METHOD__ . "(): error?: {$wpdb->last_error}" );

		// Get number of feeds.
		$count = count( $results );

		// Log number of feeds.
		$this->log_debug( __METHOD__ . "(): count: {$count}" );

		// Unserialize feed meta.
		for ( $i = 0; $i < $count; $i++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;

	}

	/**
	 * Retrieve any old feeds which need migrating to the framework.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses   GFAddOn::table_exists()
	 * @uses   GFFormsModel::get_form_table_name()
	 * @uses   wpdb::get_results()
	 *
	 * @return bool|array
	 */
	public function get_old_feeds() {

		global $wpdb;

		// Prepare table name.
		$table_name = $wpdb->prefix . 'rg_aweber';

		// If table does not exist, return.
		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		// Prepare SQL statement.
		$form_table_name = GFFormsModel::get_form_table_name();
		$sql             = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
				FROM $table_name s
				INNER JOIN $form_table_name f ON s.form_id = f.id";

		// Get old feeds.
		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Uneserialize feed meta.
		for ( $i = 0; $i < count( $results ); $i++ ) {
			$results[ $i ]['meta'] = maybe_unserialize( $results[ $i ]['meta'] );
		}

		return $results;

	}

}
