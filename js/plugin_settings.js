/* global jQuery, ajaxurl, gform_aweber_pluginsettings_strings */
/* eslint-disable camelcase, no-var */

window.GFAweberSettings = null;
window.setting_aweber = gform_aweber_pluginsettings_strings;

( function( $ ) {
	var GFAweberSettings = function() {
		var self = this;
		var isLegacy = 'true' === gform_aweber_pluginsettings_strings.is_legacy;
		var prefixes = {
			input: isLegacy ? '_gaddon_setting_' : '_gform_setting_',
			field: isLegacy ? 'gaddon-setting-row-' : 'gform-setting-',
		};

		var saveButton = $( '#gform-settings-save' );

		self.ui = {
			buttons: {
				connect: $( '#gform_aweber_connect_button' ),
				disconnect: $( '#gform_aweber_disconnect_button' ),
				save: saveButton,
			},
			saveContainer: isLegacy ? saveButton.parent().parent() : saveButton.parent(),
		};

		this.init = function() {
			this.bindDisconnect();
		};

		this.bindDisconnect = function() {
			// Disconnect from Aweber.
			self.ui.buttons.disconnect.on( 'click', function( e ) {
				// Prevent default event.
				e.preventDefault();

				// Get confirmation from user.
				/* eslint-disable no-alert */
				if ( ! window.confirm( gform_aweber_pluginsettings_strings.disconnect ) ) {
					return;
				}

				// Set disabled state on button.
				self.ui.buttons.disconnect.attr( 'disabled', 'disabled' );

				// Send request to disconnect.
				$.post( {
					url: ajaxurl,
					dataType: 'json',
					data: {
						action: 'gfaweber_deauthorize',
						nonce: gform_aweber_pluginsettings_strings.ajax_nonce,
					},
					success: function( response ) {
						if ( response.success ) {
							window.location.reload();
						} else {
							window.alert( response.error );
						}

						self.ui.buttons.disconnect.removeAttr( 'disabled' );
					},
					fail: function( response ) {
						window.alert( response.error );
					},
				} );
			} );
		};

		this.init();
	};

	$( document ).ready( GFAweberSettings );
} )( jQuery );
