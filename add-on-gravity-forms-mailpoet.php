<?php
/**
 * Plugin Name:       Add-on Gravity Forms - Mailpoet
 * Description:       Add a MailPoet 3 signup field to your Gravity Forms.
 * Version:           1.1.14.1
 * Author:            Tikweb
 * Author URI:        http://www.tikweb.dk/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       add-on-gravity-forms-mailpoet
 * Domain Path:       /languages
 */


/*
Add-on Gravity Forms - MailPoet 3 is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Add-on Gravity Forms - MailPoet 3 is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Add-on Gravity Forms - MailPoet 3. If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
*/

define( 'GF_NEW_MAILPOET_ADDON_VERSION', '1.0.0' );

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	exit;
}

/*
 * Once plugin loaded, load text domain
*/

function agfm_load_text_domain() {
	load_plugin_textdomain( 'add-on-gravity-forms-mailpoet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'agfm_load_text_domain' );

/**
 * Include plugin.php to detect plugin.
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/**
 * Check MailPoet active
 * Prerequisite
 */
if ( ! is_plugin_active( 'mailpoet/mailpoet.php' ) ) {
	add_action( 'admin_notices', function () {
		?>
        <div class="error">
            <p>
				<?php
				$name    = 'Add-on Gravity Forms - Mailpoet';
				$mp_link = '<a href="https://wordpress.org/plugins/mailpoet/" target="_blank">MailPoet</a>';
				printf(
					__( '%s plugin requires %s plugin, Please activate %s first to using %s.',
						'add-on-gravity-forms-mailpoet' ),
					$name,
					$mp_link,
					$mp_link,
					$name
				);
				?>
            </p>
        </div>
		<?php
	} );

	return;    // If not then return
}

/**
 * After gravity form loaded.
 */
add_action( 'gform_loaded', array( 'GF_New_MailPoet_Startup', 'load' ), 5 );

class GF_New_MailPoet_Startup {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . '/class-gfnewmailpoetaddon.php';

		GFAddOn::register( 'GFNEWMailPoetAddOn' );

		// include mailpoet field
		require_once plugin_dir_path( __FILE__ ) . '/mailpoet-fields.php';
	}

}

function gf_new_mailpoet_feed_addon() {
	return GFNEWMailPoetAddOn::get_instance();
}


add_action( 'admin_notices', 'gf_new_mailpoet_plugin_admin_notices' );
function gf_new_mailpoet_plugin_admin_notices() {
	if ( $notices = get_option( 'gf_new_mailpoet_plugin_deferred_admin_notices' ) ) {
		foreach ( $notices as $notice ) {
			echo "<div class='notice notice-warning is-dismissable'><p>$notice</p></div>";
		}
		delete_option( 'gf_new_mailpoet_plugin_deferred_admin_notices' );
	}
}

register_deactivation_hook( __FILE__, 'gf_new_mailpoet_plugin_deactivation' );
function gf_new_mailpoet_plugin_deactivation() {
	delete_option( 'gf_new_mailpoet_plugin_deferred_admin_notices' );
}

/**
 * array_column alternatives for PHP version less than PHP 5.5
 * this file / function has no effect on php version greater than php 5.5
 */
include plugin_dir_path( __FILE__ ) . '/array_column.php';

/**
 * Mailpoet class load
 */

use MailPoet\Models\Subscriber;
use MailPoet\Models\Segment;
use MailPoet\Subscribers\ConfirmationEmailMailer;
use MailPoet\Subscribers\NewSubscriberNotificationMailer;

// use MailPoet\Models\CustomField;

/*
 * Add a MailPoet custom field in each gravity form field
 *
 */

// add_action( 'gform_field_standard_settings', 'my_standard_settings', 10, 2 );
function my_standard_settings( $position, $form_id ) {

	//create settings on position 25 (right after Field Label)
	if ( $position == 25 ) {
		?>
        <li class="mpcf_settings field_setting">
            <label for="field_admin_label">
				<?php esc_html_e( 'MailPoet custom field ID', 'gravityforms' ); ?>
				<?php gform_tooltip( 'form_field_encrypt_value' ) ?>
            </label>
            <input type="text" id="mcf_field_name" class="fieldwidth-3" onkeyup="SetFieldProperty('mpcfName', this.value);" value="" size="35"/>
        </li>
		<?php
	}
}

// add_filter( 'gform_tooltips', 'add_encryption_tooltips' );
function add_encryption_tooltips( $tooltips ) {

	//$fields  = CustomField::findMany();
	$results = array();
	/*
	foreach ( $fields as $field ) {
		$results[ 'cf_' . $field->id ] = $field->name;
	}
	*/
	if ( ! empty( $results ) ) {
		$tooltips['form_field_encrypt_value'] = '';
		foreach ( $results as $key => $value ) {
			$tooltips['form_field_encrypt_value'] .= __("MailPoet custom field name: ", "add-on-gravity-forms-mailpoet") . "<i>" . $value . "</i>" . "<br>" . __("MailPoet custom field ID : ", "add-on-gravity-forms-mailpoet") . "<strong>" . $key . "</strong>" . "<br><br>";
		}
	} else {
		$tooltips['form_field_encrypt_value'] = __("No MailPoet custom field available", "add-on-gravity-forms-mailpoet");
	}

	return $tooltips;
}
/*
add_action( 'gform_editor_js', function () {
	echo '<script type="text/javascript">' . PHP_EOL;
	foreach ( GF_Fields::get_all() as $gf_field ) {
		echo 'fieldSettings.' . $gf_field->type . ' += ", .mpcf_settings";' . PHP_EOL;
	}

	//binding to the load field settings event to initialize
	echo "jQuery(document).on('gform_load_field_settings', function(event, field, form){
            if (typeof field['mpcfName'] !== 'undefined') {
                jQuery('#mcf_field_name').attr('value', field['mpcfName']);
            } else {
                jQuery('#mcf_field_name').attr('value', '');
            }
	    });";

	echo '</script>' . PHP_EOL;
} );
*/ 

/**
 * Add mailpoet list to choice.
 */
// add_action( 'gform_predefined_choices', 'mailpoet_predefiend_list' );
/*
function mailpoet_predefiend_list( $choices ) {
	$ret = array();

	$segments = Segment::where_not_equal( 'type', Segment::TYPE_WP_USERS )->findArray();

	foreach ( $segments as $s_key => $s_val ) {

		$ret['Mailpoet List'][] = $s_val['name'] . '|' . $s_val['id'];

	}

	foreach ( $choices as $key => $value ) {
		$ret[ $key ] = $value;
	}

	return $ret;
}
*/

/**
 * Set default input
 */
/*
add_action( 'gform_editor_js_set_default_values', 'mailpoet_list_set_default' );
function mailpoet_list_set_default() {
	$segments = Segment::where_not_equal( 'type', Segment::TYPE_WP_USERS )->findArray();

	$choice = '[';
	foreach ( $segments as $key => $value ) {
		$choice .= 'new Choice("' . $value["name"] . '","' . $value["id"] . '"), ';
	}

	$choice .= '];';

	if ( empty( $segments ) ) {
		$choice = "[new Choice('List one'), new Choice('List two'), new Choice('Please set a list')];";
	}

	?>

    case "mailpoet":
    field.label = "Subscribe";
    field.choices = <?= $choice; ?>
    break;
	<?php
}
*/

/**
 * Process form submission, make subscriber, etc.
 */
add_action( 'gform_after_submission', 'process_mailpoet_list', 10, 2 );
function process_mailpoet_list( $entry, $form ) {

	if ( ! is_array( $entry ) || ! is_array( $form ) || empty( $entry ) || empty( $form ) ) {
		return;
	}

	if ( ! isset( $form['fields'] ) ) {
		return;
	}

	// extract email
	$email_key = array_search( 'email', array_column( $form['fields'], 'type' ) );
	if ( false === $email_key ) {
		$email_key = array_search( 'email', array_column( array_map( 'get_object_vars', $form['fields'] ), 'type' ) );
	}


	if ( ! is_integer( $email_key ) ) {
		return;
	}

	$email_id = $form['fields'][ $email_key ]->id;
	$email    = rgar( $entry, $email_id );


	if ( empty( $email ) ) {
		return;
	}

	$subscriber = Subscriber::findOne( $email );

	/* Deleted this code in version 1.1.6 to fix existing list id replace issue*/
	/*	if ( false !== $subscriber ){
			$segments = $subscriber->segments()->findArray();

			if ( !empty($segments) ){
				return;
			}
		}*/

	/* Deleted code end. Version 1.1.6 */

	$subscriber_data = array(
		'email' => $email
	);

	// extract name
	$name_key = array_search( 'name', array_column( $form['fields'], 'type' ) );
	if ( false === $name_key ) {
		$name_key = array_search( 'name', array_column( array_map( 'get_object_vars', $form['fields'] ), 'type' ) );
	}


	if ( is_integer( $name_key ) ) {

		$fname_id = array_search( 'First', array_column( $form['fields'][ $name_key ]->inputs, 'label' ) );
		$fname_id = $form['fields'][ $name_key ]->inputs[ $fname_id ]['id'];

		$lname_id = array_search( 'Last', array_column( $form['fields'][ $name_key ]->inputs, 'label' ) );
		$lname_id = $form['fields'][ $name_key ]->inputs[ $lname_id ]['id'];

		$first_name = rgar( $entry, $fname_id );
		$last_name  = rgar( $entry, $lname_id );

		$subscriber_data['first_name'] = $first_name;
		$subscriber_data['last_name']  = $last_name;

	}


	// extract mailpoet list ids
	$mp_key = array_search( 'mailpoet', array_column( $form['fields'], 'type' ) );
	if ( false === $mp_key ) {
		$mp_key = array_search( 'mailpoet', array_column( array_map( 'get_object_vars', $form['fields'] ), 'type' ) );
	}

	if ( ! is_integer( $mp_key ) ) {
		return;
	}

	$mp_id = (array) $form['fields'][ $mp_key ];
	$mp_id = array_column( $mp_id['inputs'], 'id' );


	$mailpoetlists = [];

	foreach ( $mp_id as $key => $value ) {
		$lst = rgar( $entry, $value );

		if ( ! empty( $lst ) ) {

			if ( is_integer( $lst ) || is_numeric( $lst ) ) {

				$mailpoetlists[] = $lst;

			} else {

				$list = Segment::where( 'name', $lst )->findArray();

				if ( ! empty( $list ) ) {
					$list      = array_shift( $list );
					$mailpoetlists[] = isset( $list['id'] ) ? $list['id'] : null;
				}

			}
		}

	}

	/*
	 *
	 *
	 *
	 *
	 * The below codes run ONLY when someone added subscription field in the gravity field, NOT from mailpoet feed
	 *
	 *
	 *
	 *
	 */

	// subscribe to
	if ( ! empty( $mailpoetlists ) ) {

		/* Added this to fix old list replace issue. Version 1.1.6 */

		//If user is already subscribed then get the existed list id.
		if ( $subscriber ) {
			$subscriber->withSubscriptions();
			$old_lists = $subscriber->subscriptions;

			foreach ( $old_lists as $key => $value ) {
				$list_ids[] = $value['segment_id'];
			}
			$mailpoetlists = array_unique(array_merge( $mailpoetlists, $list_ids ));
		}

		/* Fix added end. Version 1.1.6 */

		//If registered user is in woocommerce or wp user list, remove that list ids first
		$wp_segment = Segment::whereEqual('type', Segment::TYPE_WP_USERS)->findArray();
		$wc_segment = Segment::whereEqual('type', Segment::TYPE_WC_USERS)->findArray();

		if (($key = array_search($wp_segment[0]['id'], $mailpoetlists)) ) {
			unset($mailpoetlists[$key]);
		}

		if (($key = array_search($wc_segment[0]['id'], $mailpoetlists)) ) {
			unset($mailpoetlists[$key]);
		}

		//Saving the updated list
		$subscriber_data['segments'] = $mailpoetlists;

		//Getting custom field information
		$count_field_id = count( $form['fields'] );
		$field_cf       = array();
		for ( $i = 0; $i < $count_field_id; $i ++ ) {
			$field_cf[] = $form['fields'][ $i ]->mpcfName;
		}

		foreach ( $field_cf as $key => $value ) {
			if ( ! empty( $value ) ) {
				$cf_field_name[] = $value;
			}
		}

		$cf_field_val = array();
		foreach ( $form['fields'] as $field ) {
			if ( ! empty( $field->mpcfName ) ) {
				if ( 'checkbox' == (string) $field->type )  {
					$value = rgar( $entry, (string) $field->id . '.1' );
					$cf_field_val[] = empty($value) ? 0: 1;
				} else {
					$value = rgar( $entry, (string) $field->id );
					$cf_field_val[] = $value;
				}
			}
		}

		//Appending the above custom field result to subscriber data
		for ( $i = 0; $i < count( $cf_field_val ); $i ++ ) {
			$subscriber_data[ $cf_field_name[ $i ] ] = $cf_field_val[ $i ];
		}

		try {
			$subscriber_data = \MailPoet\API\API::MP( 'v1' )->addSubscriber( $subscriber_data, $mailpoetlists );

		} catch ( Exception $exception ) {


			//==  Below code is modifed in version 1.1.13 by k4mrul  ==\\

			//If subscriber already exist
			if ( 12 == $exception->getCode() ) {
				try {
					//If subscriber already exist, simply add subscriber to the new list. Subscriber status will be subscribed as s/he already confirmed email. s/he may get a new mail from mailpoet for confirmation but s/he already considered as subscribed
					$existingUser = \MailPoet\API\API::MP( 'v1' )->getSubscriber($subscriber_data['email']);

					\MailPoet\API\API::MP( 'v1' )->subscribeToLists($existingUser['subscriptions'][0]['subscriber_id'], $mailpoetlists , $options['send_confirmation_email'] = true);

					//Previous code. Previously we make the subscriber status unconfirmed and let him/her to confirm the email for new list. But that's not how mailpoet's default behavior works. So we respected default behavior
					//$subscriber_data['status']          = 'unconfirmed';
					//Subscriber::createOrUpdate( $subscriber_data );
					//$subscriber = \MailPoet\API\API::MP( 'v1' )->subscribeToLists( $subscriber_data['email'], $mailpoetlists, $options['send_confirmation_email'] = true );

				} catch ( Exception $exception ) {

				}

			} else {

			}
		}


//Deprecated:
		/*		$subscriber_data['segments'] = $mailpoetlists;


				$subscriber          = Subscriber::createOrUpdate( $subscriber_data );
				$sagments            = $subscriber->segments()->findMany();
				$notification_sender = new NewSubscriberNotificationMailer();

				if ( ! empty( $subscriber ) ) {

					$subscriber_status = Subscriber::whereEqual( 'email',
						$email )->whereEqual( 'status', 'subscribed' )->findArray();

					//If the subscriber is new, send email confirmation to subscribe for the list. Otherwise, if subscriber already confirm the email for another list and subscribe to the new list, don't send confirmation email again.
					if ( empty( $subscriber_status[0]['confirmed_ip'] ) ) {
						$confirmation_sender = new ConfirmationEmailMailer();
						$confirmation_sender->sendConfirmationEmail( $subscriber );
					} //Notify the admin that existed subscriber subscribed to the new list
					else {
						$notification_sender->send( $subscriber, $sagments );
					}
				}*/
	}
}
