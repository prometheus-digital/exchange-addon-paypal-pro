<?php
/**
 * Exchange will build your add-on's settings page for you and link to it from our add-on
 * screen. You are free to link from it elsewhere as well if you'd like... or to not use our API
 * at all. This file has all the functions related to registering the page, printing the form, and saving
 * the options. This includes the wizard settings. Additionally, we use the Exchange storage API to
 * save / retreive options. Add-ons are not required to do this.
*/

/**
 * This is the function registered in the options array when it_exchange_register_addon was called for PayPal Pro
 *
 * It tells Exchange where to find the settings page
 *
 * @return void
*/
function it_exchange_paypal_pro_addon_settings_callback() {
    $IT_Exchange_PayPal_Pro_Add_On = new IT_Exchange_PayPal_Pro_Add_On();
    $IT_Exchange_PayPal_Pro_Add_On->print_settings_page();
}

/**
 * Outputs wizard settings for PayPal Pro
 *
 * Exchange allows add-ons to add a small amount of settings to the wizard.
 * You can add these settings to the wizard by hooking into the following action:
 * - it_exchange_print_[addon-slug]_wizard_settings
 * Exchange exspects you to print your fields here.
 *
 * @since 1.0.0
 * @todo make this better, probably
 * @param object $form Current IT Form object
 * @return void
*/
function it_exchange_print_paypal_pro_wizard_settings( $form ) {
    $IT_Exchange_PayPal_Pro_Add_On = new IT_Exchange_PayPal_Pro_Add_On();
    $settings = it_exchange_get_option( 'addon_paypal_pro', true );
    $form_values = ITUtility::merge_defaults( ITForm::get_post_data(), $settings );
    $hide_if_js =  it_exchange_is_addon_enabled( 'paypal_pro' ) ? '' : 'hide-if-js';
    ?>
    <div class="field paypal_pro-wizard <?php echo $hide_if_js; ?>">
    <?php if ( empty( $hide_if_js ) ) { ?>
        <input class="enable-paypal_pro" type="hidden" name="it-exchange-transaction-methods[]" value="paypal_pro" />
    <?php } ?>
    <?php $IT_Exchange_PayPal_Pro_Add_On->get_form_table( $form, $form_values ); ?>
    </div>
    <?php
}
add_action( 'it_exchange_print_paypal_pro_wizard_settings', 'it_exchange_print_paypal_pro_wizard_settings' );

/**
 * Saves PayPal Pro settings when the Wizard is saved
 *
 * @since 1.0.0
 *
 * @return void
*/
function it_exchange_save_paypal_pro_wizard_settings( $errors ) {
    if ( ! empty( $errors ) )
        return $errors;

    $IT_Exchange_PayPal_Pro_Add_On = new IT_Exchange_PayPal_Pro_Add_On();
    return $IT_Exchange_PayPal_Pro_Add_On->save_wizard_settings();
}
add_action( 'it_exchange_save_paypal_pro_wizard_settings', 'it_exchange_save_paypal_pro_wizard_settings' );

/**
 * Default settings for PayPal Pro
 *
 * @since 1.0.0
 *
 * @param array $values
 * @return array
*/
function it_exchange_paypal_pro_addon_default_settings( $values ) {
    $defaults = array(
        'paypal_pro_api_live_username'     => '',
        'paypal_pro_api_live_password'     => '',
        'paypal_pro_api_live_signature'    => '',
    		'paypal_pro_sale_method'           => 'auth_capture',
        'paypal_pro_sandbox_mode'          => false,
        'paypal_pro_purchase_button_label' => __( 'Purchase', 'LION' ),
        'paypal_pro_api_sandbox_username'  => '',
        'paypal_pro_api_sandbox_password'  => '',
        'paypal_pro_api_sandbox_signature' => '',
    );

	// Detect settings
	$paypal_settings = it_exchange_get_option( 'addon_paypal_standard_secure' ); // standard-secure

	if ( !empty( $paypal_settings ) ) {
		if ( isset( $paypal_settings[ 'paypal-standard-secure-live-api-username' ] ) ) {
			$defaults[ 'paypal_pro_api_live_username' ] = $paypal_settings[ 'paypal-standard-secure-live-api-username' ];
			$defaults[ 'paypal_pro_api_live_password' ] = $paypal_settings[ 'paypal-standard-secure-live-api-password' ];
			$defaults[ 'paypal_pro_api_live_signature' ] = $paypal_settings[ 'paypal-standard-secure-live-api-signature' ];
		}

		if ( isset( $paypal_settings[ 'paypal-standard-secure-sandbox-mode' ] ) && !empty( $paypal_settings[ 'paypal-standard-secure-sandbox-mode' ] ) ) {
			if ( isset( $paypal_settings[ 'paypal-standard-secure-sandbox-api-username' ] ) ) {
				$defaults[ 'paypal_pro_api_sandbox_username' ] = $paypal_settings[ 'paypal-standard-secure-sandbox-api-username' ];
				$defaults[ 'paypal_pro_api_sandbox_password' ] = $paypal_settings[ 'paypal-standard-secure-sandbox-api-password' ];
				$defaults[ 'paypal_pro_api_sandbox_signature' ] = $paypal_settings[ 'paypal-standard-secure-sandbox-api-signature' ];
				$defaults[ 'paypal_pro_sandbox_mode' ] = true;
			}
		}
	}

    $values = ITUtility::merge_defaults( $values, $defaults );

    return $values;
}
add_filter( 'it_storage_get_defaults_exchange_addon_paypal_pro', 'it_exchange_paypal_pro_addon_default_settings' );

/**
 * Class for PayPal Pro
 * @since 1.0.0
*/
class IT_Exchange_PayPal_Pro_Add_On {

    /**
     * @var boolean $_is_admin true or false
     * @since 1.0.0
    */
    var $_is_admin;

    /**
     * @var string $_current_page Current $_GET['page'] value
     * @since 1.0.0
    */
    var $_current_page;

    /**
     * @var string $_current_add_on Current $_GET['add-on-settings'] value
     * @since 1.0.0
    */
    var $_current_add_on;

    /**
     * @var string $status_message will be displayed if not empty
     * @since 1.0.0
    */
    var $status_message;

    /**
     * @var string $error_message will be displayed if not empty
     * @since 1.0.0
    */
    var $error_message;

    /**
     * Set up the class
     *
     * @since 1.0.0
    */
    function __construct() {
        $this->_is_admin       = is_admin();
        $this->_current_page   = empty( $_GET['page'] ) ? false : $_GET['page'];
        $this->_current_add_on = empty( $_GET['add-on-settings'] ) ? false : $_GET['add-on-settings'];

        if ( ! empty( $_POST ) && $this->_is_admin && 'it-exchange-addons' == $this->_current_page && 'paypal_pro' == $this->_current_add_on ) {
            add_action( 'it_exchange_save_add_on_settings_paypal_pro', array( $this, 'save_settings' ) );
            do_action( 'it_exchange_save_add_on_settings_paypal_pro' );
        }
    }

    /**
     * Prints settings page
     *
     * @since 1.0.0
    */
    function print_settings_page() {
        $settings = it_exchange_get_option( 'addon_paypal_pro', true );
        $form_values  = empty( $this->error_message ) ? $settings : ITForm::get_post_data();
        $form_options = array(
            'id'      => apply_filters( 'it_exchange_add_on_paypal_pro', 'it-exchange-add-on-paypal_pro-settings' ),
            'enctype' => apply_filters( 'it_exchange_add_on_paypal_pro_settings_form_enctype', false ),
            'action'  => 'admin.php?page=it-exchange-addons&add-on-settings=paypal_pro',
        );
        $form         = new ITForm( $form_values, array( 'prefix' => 'it-exchange-add-on-paypal_pro' ) );

        if ( ! empty ( $this->status_message ) )
            ITUtility::show_status_message( $this->status_message );
        if ( ! empty( $this->error_message ) )
            ITUtility::show_error_message( $this->error_message );

        ?>
        <div class="wrap">
            <?php screen_icon( 'it-exchange' ); ?>
            <h2><?php _e( 'PayPal Pro Settings', 'LION' ); ?></h2>

            <?php do_action( 'it_exchange_paypal_pro_settings_page_top' ); ?>
            <?php do_action( 'it_exchange_addon_settings_page_top' ); ?>
            <?php $form->start_form( $form_options, 'it-exchange-paypal_pro-settings' ); ?>
                <?php do_action( 'it_exchange_paypal_pro_settings_form_top' ); ?>
                <?php $this->get_form_table( $form, $form_values ); ?>
                <?php do_action( 'it_exchange_paypal_pro_settings_form_bottom' ); ?>
                <p class="submit">
                    <?php $form->add_submit( 'submit', array( 'value' => __( 'Save Changes', 'LION' ), 'class' => 'button button-primary button-large' ) ); ?>
                </p>
            <?php $form->end_form(); ?>
            <?php do_action( 'it_exchange_paypal_pro_settings_page_bottom' ); ?>
            <?php do_action( 'it_exchange_addon_settings_page_bottom' ); ?>
        </div>
        <?php
    }

    /**
     * Builds Settings Form Table
     *
     * @since 1.0.0
     */
    function get_form_table( $form, $settings = array() ) {

        $general_settings = it_exchange_get_option( 'settings_general' );

        if ( !empty( $settings ) ) {
            foreach ( $settings as $key => $var ) {
                $form->set_option( $key, $var );
			}
		}

        if ( ! empty( $_GET['page'] ) && 'it-exchange-setup' == $_GET['page'] ) : ?>
            <h3><?php _e( 'PayPal Pro', 'LION' ); ?></h3>
        <?php endif; ?>
        <div class="it-exchange-addon-settings it-exchange-paypal_pro-addon-settings">
            <p>
                <?php _e( 'To get PayPal Pro set up for use with Exchange, you\'ll need to add the following information from your PayPal Pro account.', 'LION' ); ?>
            </p>
            <p>
                <?php _e( 'Don\'t have a PayPal Pro account yet?', 'LION' ); ?> <a href="https://www.paypal.com/webapps/mpp/paypal-payments-pro" target="_blank"><?php _e( 'Go set one up here', 'LION' ); ?></a>.
            </p>
            <h4><?php _e( 'Fill out your PayPal Pro API Credentials', 'LION' ); ?></h4>
            <p>
                <label for="paypal_pro_api_live_username"><?php _e( 'API Username', 'LION' ); ?> <span class="tip" title="<?php _e( 'Your PayPal Pro API Username can be found in PayPal under Profile -› API Access (or Request API Credentials).', 'LION' ); ?>">i</span></label>
                <?php $form->add_text_box( 'paypal_pro_api_live_username' ); ?>
            </p>
            <p>
                <label for="paypal_pro_api_live_password"><?php _e( 'API Password', 'LION' ); ?> <span class="tip" title="<?php _e( 'The PayPal Pro API Password can be found in PayPal under Profile -› API Access (or Request API Credentials).', 'LION' ); ?>">i</span></label>
                <?php $form->add_password( 'paypal_pro_api_live_password' ); ?>
            </p>
            <p>
                <label for="paypal_pro_api_live_signature"><?php _e( 'API Signature', 'LION' ); ?> <span class="tip" title="<?php _e( 'The PayPal Pro API Signature can be found in PayPal under Profile -› API Access (or Request API Credentials).', 'LION' ); ?>">i</span></label>
                <?php $form->add_password( 'paypal_pro_api_live_signature' ); ?>
            </p>
            <p>
                <label for="paypal_pro_sale_method"><?php _e( 'Transaction Sale Method', 'LION' ); ?> <span class="tip" title="<?php _e( 'Authorization & Capture, or Auth/Capture, allows you to authorize the availability of funds for a transaction but delay the capture of funds until a later time. This is often useful for merchants who have a delayed order fulfillment process.', 'LION' ); ?>">i</span></label>
				<?php
					$sale_methods = array(
						'auth_capture' => __( 'Authorize and Capture - Charge the Credit Card for the total amount', 'LION' ),
						'auth' => __( 'Authorize - Only authorize the Credit Card for the total amount', 'LION' )
					);

					$form->add_drop_down( 'paypal_pro_sale_method', $sale_methods );
				?>
            </p>
            <h4><?php _e( 'Optional: Edit Purchase Button Label', 'LION' ); ?></h4>
            <p>
                <label for="paypal_pro_purchase_button_label"><?php _e( 'Purchase Button Label', 'LION' ); ?> <span class="tip" title="<?php esc_attr_e( 'This is the text inside the button your customers will press to purchase with PayPal Pro', 'LION' ); ?>">i</span></label>
                <?php $form->add_text_box( 'paypal_pro_purchase_button_label' ); ?>
            </p>

            <h4 class="hide-if-wizard"><?php _e( 'Optional: Enable PayPal Pro Sandbox Mode', 'LION' ); ?></h4>
            <p class="hide-if-wizard">
                <?php $form->add_check_box( 'paypal_pro_sandbox_mode', array( 'class' => 'show-test-mode-options' ) ); ?>
                <label for="paypal_pro_sandbox_mode"><?php _e( 'Enable PayPal Pro Sandbox Mode?', 'LION' ); ?> <span class="tip" title="<?php esc_attr_e( 'Use this mode for testing your store. This mode will need to be disabled when the store is ready to process customer payments.', 'LION' ); ?>">i</span></label>
            </p>
			<?php $hidden_class = ( $settings['paypal_pro_sandbox_mode'] ) ? '' : 'hide-if-live-mode'; ?>
			<p class="test-mode-options hide-if-wizard <?php echo $hidden_class; ?>">
				<label for="paypal_pro_api_sandbox_username"><?php _e( 'PayPal Sandbox API Username', 'LION' ); ?> <span class="tip" title="<?php _e( 'Your PayPal Pro API Username can be found in PayPal Sandbox under Profile -› API Access (or Request API Credentials). ', 'LION' ); ?>http://ithemes.com/tutorials/creating-a-paypal-sandbox-test-account">i</span></label>
				<?php $form->add_text_box( 'paypal_pro_api_sandbox_username' ); ?>
			</p>
			<p class="test-mode-options hide-if-wizard <?php echo $hidden_class; ?>">
				<label for="paypal_pro_api_sandbox_password"><?php _e( 'PayPal Sandbox API Password', 'LION' ); ?> <span class="tip" title="<?php _e( 'Your PayPal Pro API Password can be found in PayPal Sandbox under Profile -› API Access (or Request API Credentials).', 'LION' ); ?>http://ithemes.com/tutorials/creating-a-paypal-sandbox-test-account">i</span></label>
				<?php $form->add_text_box( 'paypal_pro_api_sandbox_password' ); ?>
			</p>
			<p class="test-mode-options hide-if-wizard <?php echo $hidden_class; ?>">
				<label for="paypal_pro_api_sandbox_signature"><?php _e( 'PayPal Sandbox API Signature', 'LION' ); ?> <span class="tip" title="<?php _e( 'Your PayPal Pro API Signeatur can be found in PayPal Sandbox under Profile -› API Access (or Request API Credentials).', 'LION' ); ?>http://ithemes.com/tutorials/creating-a-paypal-sandbox-test-account">i</span></label>
				<?php $form->add_text_box( 'paypal_pro_api_sandbox_signature' ); ?>
			</p>
        </div>
        <?php
    }

    /**
     * Save settings
     *
     * @since 1.0.0
     * @return void
    */
    function save_settings() {
        $defaults = it_exchange_get_option( 'addon_paypal_pro' );
        $new_values = wp_parse_args( ITForm::get_post_data(), $defaults );

        // Check nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'it-exchange-paypal_pro-settings' ) ) {
            $this->error_message = __( 'Error. Please try again', 'LION' );
            return;
        }

        $errors = apply_filters( 'it_exchange_add_on_paypal_pro_validate_settings', $this->get_form_errors( $new_values ), $new_values );
        if ( ! $errors && it_exchange_save_option( 'addon_paypal_pro', $new_values ) ) {
            ITUtility::show_status_message( __( 'Settings saved.', 'LION' ) );
        } else if ( $errors ) {
            $errors = implode( '<br />', $errors );
            $this->error_message = $errors;
        } else {
            $this->status_message = __( 'Settings not saved.', 'LION' );
        }

    }

    /**
     * This is a means of catching errors from the activation method above and displaying it to the customer
     *
     * @since 1.2.2
     */
    function exchange_paypal_pro_admin_notices() {
    	if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) {

    		switch( $_GET['sl_activation'] ) {

    			case 'false':
    				$message = urldecode( $_GET['message'] );
    				?>
    				<div class="error">
    					<p><?php echo $message; ?></p>
    				</div>
    				<?php
    				break;

    			case 'true':
    			default:
    				// Developers can put a custom success message here for when activation is successful if they way.
    				break;

    		}
    	}
    }

    /**
     * Save wizard settings
     *
     * @since 1.0.0
     * @return void|array Void or Error message array
    */
    function save_wizard_settings() {
        if ( empty( $_REQUEST['it_exchange_settings-wizard-submitted'] ) )
            return;

		$paypal_pro_settings = array();

		$fields = array(
			'paypal_pro_api_live_username',
			'paypal_pro_api_live_password',
			'paypal_pro_api_live_signature',
			'paypal_pro_sale_method',
			'paypal_pro_sandbox_mode',
			'paypal_pro_api_sandbox_username',
			'paypal_pro_api_sandbox_password',
			'paypal_pro_api_sandbox_signature',
			'paypal_pro_purchase_button_label',
		);
		$default_wizard_paypal_pro_settings = apply_filters( 'default_wizard_paypal_pro_settings', $fields );

        foreach( $default_wizard_paypal_pro_settings as $var ) {
            if ( isset( $_REQUEST['it_exchange_settings-' . $var] ) ) {
                $paypal_pro_settings[$var] = $_REQUEST['it_exchange_settings-' . $var];
            }
        }

        $settings = wp_parse_args( $paypal_pro_settings, it_exchange_get_option( 'addon_paypal_pro' ) );

        if ( $error_msg = $this->get_form_errors( $settings ) ) {

            return $error_msg;

        } else {
            it_exchange_save_option( 'addon_paypal_pro', $settings );
            $this->status_message = __( 'Settings Saved.', 'LION' );
        }

        return;
    }

    /**
     * Validates for values
     *
     * Returns string of errors if anything is invalid
     *
     * @since 1.0.0
     * @return array
    */
    public function get_form_errors( $values ) {

        $errors = array();

		if ( empty( $values['paypal_pro_api_live_username'] ) )
            $errors[] = __( 'Please include your PayPal Pro API Username', 'LION' );

        if ( empty( $values['paypal_pro_api_live_password'] ) )
            $errors[] = __( 'Please include your PayPal Pro API Password', 'LION' );

        if ( empty( $values['paypal_pro_api_live_signature'] ) )
            $errors[] = __( 'Please include your PayPal Pro API Signature', 'LION' );

		if ( !empty( $values['paypal_pro_sandbox_mode' ] ) ) {
			if ( empty( $values['paypal_pro_api_sandbox_username'] ) )
				$errors[] = __( 'Please include your PayPal Pro Sandbox API Username', 'LION' );

			if ( empty( $values['paypal_pro_api_sandbox_password'] ) )
				$errors[] = __( 'Please include your PayPal Pro Sandbox API Password', 'LION' );

			if ( empty( $values['paypal_pro_api_sandbox_signature'] ) )
				$errors[] = __( 'Please include your PayPal Pro Sandbox API Signature', 'LION' );
		}

        return $errors;

    }

}
