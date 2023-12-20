<?php
/**
 * Plugin Name:  MikeG3D Imgur Sync
 * Plugin URI:   https://mikeg3d.com/imgursync
 * Description:  Sync your wordpress media images to imgur
 * Version:      1
 * Author:       MikeG3D
 * Author URI:   https://mikeg3d.com
 * License:      PRIVATE
 * License URI:  PRIVATE
**/


// add admin menu & settings page
add_action( 'admin_menu', 'mg3d_imgursync_menu' );

function mg3d_imgursync_menu(){
	add_management_page( //adds submenu under Tools
		'MikeG3D Imgur Sync  Settings',
		'Imgur Sync Settings',
		'install_plugins',
		'mg3d_imgursync',
		'mg3d_imgursync_admin', // settings page callback
		10 // menu position
	);
}

// settings page with imgur api auth setup
// php html mixed crap
function mg3d_imgursync_admin(){
?>
<div class="wrap">
	<h1><?php echo get_admin_page_title() ?></h1>
	
	<?php
	// receive imgur creds from URL Hash
	// This is dumb - hashes are not sent to server javascript needed to parse url
	if( isset( $_GET['auth'] ) ){
		//send creds via url param
	?>
		<h1>Auth Callback Received</h1>
		<h3>Please wait...</h3>
		<script language="javascript">
			window.open('<?php echo admin_url ( 'tools.php?page=mg3d_imgursync&token=1' ); ?>&' + location.hash.substring(1),"_self")
		</script>
	<?php
	}
	
	// catch imgur creds
	if( isset( $_GET['token'] ) ){
		if( isset( $_GET['access_token'] ) ){
			update_option('mg3d_imgursync_access_token', $_GET['access_token']);
		}
		if( isset( $_GET['refresh_token'] ) ){
		update_option('mg3d_imgursync_refresh_token', $_GET['refresh_token']);
		}
		if( isset( $_GET['account_username'] ) ){
			update_option('mg3d_imgursync_account_username', $_GET['account_username']);
		}
		if( isset( $_GET['account_id'] ) ){
			update_option('mg3d_imgursync_account_id', $_GET['account_id']);
		}
	}

	// if we have an access token test connection and show status info
	if( get_option( 'mg3d_imgursync_access_token' ) ){
		$imgur = imgursync_AccountBase(); // calls imgur Account Base api
		if( $imgur['status'] == 200 && !isset( $imgur['data']['error'] ) ){ // if success show it off
		?>
		
			<p><strong style="padding:10px;border:1px solid #000;background-color:#33FF57;">Successfully linked to <?php echo $imgur['data']['url']; ?></strong></p>
		
		<?php
		}
		if( $imgur['data']['error'] || $imgur['status'] !== 200){ // fail show why / log?
		?>
		
			<p><strong style="padding:10px;border:1px solid #000;background-color:#ff0000;">ERROR: <?php echo isset($imgur['data']['error']) ? $imgur['data']['error'] : $imgur['status']; ?></strong></p>
		
		<?php
		}
	}
	// testing things
	//var_dump( $imgur );
	?>

	<form method="post" action="options.php">
		<?php
			settings_fields( 'mg3d_imgursync_settings' ); // settings group name
			do_settings_sections( 'mg3d_imgursync' ); // just a page slug
			submit_button(); // "Save Changes" button
			// imgur account authorize button
			if( get_option( 'mg3d_imgur_clientid' ) && get_option( 'mg3d_imgur_clientsecret' ) ){
				// create auth URL *include redirect will throw error if entered incorrectly on imgur
				$authurl = 'https://api.imgur.com/oauth2/authorize?response_type=token&client_id=' . get_option('mg3d_imgur_clientid') . '&redirect_uri=' . urlencode( admin_url ( 'tools.php?page=mg3d_imgursync&auth=1' ) );
			?>
				<input type="button" onclick="window.open(' <?php echo $authurl; ?>','_self');" value="Authorize" class="button button-primary"/>
			<?php
			}

		?>
		
	<p><strong>*This plugin requires imgur API access to function.<br />
	Obtain and save a Client ID and Client Secret using the instructions below.<br />
	THEN click the Authorize button and ALLOW access to your Imgur account.<br />
	Instructions:</strong> <a href="https://api.imgur.com/oauth2/addclient" target="_blank">Register with Imgur (Register an Application)</a><br />
	Application name: Wordpress to Imgur<br />
	Authorization type: OAuth 2 authorization with a callback URL<br />
	Authorization callback URL: <?php echo admin_url('tools.php?page=mg3d_imgursync&auth=1'); ?><br />
	Application website:
	Email: &lt; Your Email &gt;<br />
	Description: Pushes wordpress media images to imgur account</p>
	Submit, the next page will have your Client ID and Client secret. Save in the corresponding fields above and authorize your account.
	</form>
</div>
<?php
}

// add settings fields
add_action( 'admin_init',  'imgursync_settings_fields' );

function imgursync_settings_fields(){
	$page_slug = 'mg3d_imgursync';
	$option_group = 'mg3d_imgursync_settings';
	
	// create settings section
	add_settings_section(
		'mg3d_imgursync_id', // section ID
		'', // title (optional)
		'', // callback function to display the section (optional)
		$page_slug
	);
	
	// register settings
	register_setting( $option_group, 'mg3d_imgur_clientid' );
	register_setting( $option_group, 'mg3d_imgur_clientsecret' );
	
	// display fields
	add_settings_field(
		'mg3d_imgur_clientid', // id
		'Imgur ClientId', // title 
		'mg3d_settings_textinput', // html input callback
		$page_slug, // page slug
		'mg3d_imgursync_id', // section ID           
		array( // args for callback
			'name' => 'mg3d_imgur_clientid'
		)
	);
	add_settings_field(
		'mg3d_imgur_clientsecret', // id
		'Imgur Client Secret', // title 
		'mg3d_settings_textinput', // html input callback
		$page_slug, // page slug
		'mg3d_imgursync_id', // section ID           
		array( // args for callback
			'name' => 'mg3d_imgur_clientsecret'
		)
	);
}

function mg3d_settings_textinput( $args ){
	printf(
		'<input type="text" id="%s" name="%s" value="%s" />',
		$args[ 'name' ],
		$args[ 'name' ],
		get_option( $args[ 'name' ] )
	);
}

// main wordpress hooks for imgur sync
if( get_option( 'mg3d_imgursync_access_token' ) ) { // make sure we have access
	add_action( 'add_attachment' , 'imgursync_add_attachment' ); // args - $post_id
	add_filter('attachment_fields_to_save', 'imgursync_attachment_fields_to_save', null , 2); // args - $post, $attachment
	add_action( 'attachment_updated', 'imgursync_attachment_updated', null, 3); // args - $post_id, $post_after, $post_before
}

// upload new images to imgur too
function imgursync_add_attachment( $post_id ){
	$post = get_post( $post_id );

	// make sure attachment is an image
	if ( str_starts_with( $post->post_mime_type, 'image' ) ) {
		// send to imgur
		$imgur = imgursync_ImageUpload( wp_get_attachment_url( $post_id ), $post->post_title , '' );
		// check for success
		if( $imgur['status'] == 200 && !isset( $imgur['data']['error'] ) ){
			update_post_meta( $post_id, '_imgursync_image_url', $imgur['data']['link'] ); // save imgur link
			update_post_meta( $post_id, '_imgursync_image_id', $imgur['data']['id'] ); // save imgur id
		}
		if( $imgur['data']['error'] || $imgur['status'] !== 200 ) {
			// error do something
		}
	}
	
	// for future update to add images resized by WP
	// function returns array including different sizes and urls
	// $resizedimgs = wp_get_attachment_metadata( $post_id ); //get all other resized images 

}

// send to imgur checked
function imgursync_attachment_fields_to_save( $post, $attachment ){ 
	//check for push and make sure we didn't already sync
	if ( isset( $attachment['imgurpush'] ) && !get_post_meta( $post['ID'], '_imgursync_image_url', true ) ){
		// push image to imgur
		$imgur = imgursync_ImageUpload( wp_get_attachment_url( $post['ID'] ), $post['post_title'] , $post['post_content'] );
		// check for success
		if( $imgur['status'] == 200 && !isset( $imgur['data']['error'] ) ){
			update_post_meta( $post['ID'], '_imgursync_image_url', $imgur['data']['link'] );
			update_post_meta( $post['ID'], '_imgursync_image_id', $imgur['data']['id'] );
		}
		if( $imgur['data']['error'] || $imgur['status'] !== 200 ) {
			// error do something
		}
	}
	return $post; // must return post
}

// update imgur title and desc on WP updates
function imgursync_attachment_updated( $post_id, $post_after, $post_before ){
	$imgurid = get_post_meta( $post_id, '_imgursync_image_id', true );
	if( (	$post_after->post_title !== $post_before->post_title ||
			$post_after->post_content !== $post_before->post_content ) && $imgurid ){
				
		$imgur = imgursync_UpdateImageInfo( $imgurid, $post_after->post_title, $post_after->post_content );
		if( $imgur['data']['error'] || $imgur['status'] !== 200 ) {
			// error do something
		}
	}
}

// show imgur data in attachment details 
add_filter( 'attachment_fields_to_edit', 'imgursync_attachment_fields_to_edit', null, 2 );
function imgursync_attachment_fields_to_edit( $form_fields, $post ){
	$imgurUrl = get_post_meta( $post->ID, '_imgursync_image_url', true );
	if( $imgurUrl ){
		// add imgur url
		$form_fields['imgur_url'] = array(
			'label'	=> 'Imgur URL',
			'input'	=> 'html',
			'html'	=> "<input type='text' class='text urlfield' readonly='readonly' name='attachments[$post->ID][imgururl]' value='" . $imgurUrl . "' /><br />",
			'value'	=> $imgurUrl,
		);
	}
	
	// if not on imgur and is an image

	if ( !$imgurUrl && str_starts_with( $post->post_mime_type, 'image' ) ){
		// does a checkbox work
		$form_fields['imgur_push'] = array(
			'label'	=> 'Send to Imgur',
			'input'	=> 'html',
			'html'	=> "<input type='checkbox' name='attachments[$post->ID][imgurpush]' /><br />",
			'value'	=> false,
		);
	}
		
	return $form_fields;
}

/**
 * 
 *  Imgur Api Functions
 *
**/

// imgur api - account base
// using imgur Account Base for testing
function imgursync_AccountBase(){
	$curl = curl_init();

	curl_setopt_array( $curl, array(
		CURLOPT_URL => 'https://api.imgur.com/3/account/me',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'GET',
		CURLOPT_HTTPHEADER => array(
			'Authorization: Bearer ' . get_option( 'mg3d_imgursync_access_token' )
		),
	));

	$response = curl_exec( $curl );

	curl_close( $curl );
	return json_decode( $response, true );
}

// imgur api - image upload
function imgursync_ImageUpload( $url, $title, $desc ){
	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://api.imgur.com/3/image',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => array(
			'image' => $url,
			'type' => 'url',
			'title' => $title,
			'description' => $desc
		),
		CURLOPT_HTTPHEADER => array(
			'Authorization: Bearer ' . get_option( 'mg3d_imgursync_access_token' )
	  ),
	));

	$response = curl_exec($curl);

	curl_close($curl);
	return json_decode( $response, true );
}

// imgur api - update image info
function imgursync_UpdateImageInfo( $id, $title, $desc ){
	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://api.imgur.com/3/image/' . $id,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => array(
			'title' => $title,
			'description' => $desc
		),
		CURLOPT_HTTPHEADER => array(
			'Authorization: Bearer ' . get_option( 'mg3d_imgursync_access_token' )
		),
	));

	$response = curl_exec($curl);

	curl_close($curl);
	return json_decode( $response, true );
}

?>