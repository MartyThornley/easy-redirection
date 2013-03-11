<?php
/*
Plugin Name: Easy Redirection
Plugin URI: http://blogsiteplugins.com
Description: Create redirections with custom post types
Version: 0.1
Author: Marty Thornley
Author URI: http://martythornley.com
*/

	//delete_option( 'saved_redirects' );
	
	if ( !is_admin() ) {
		$saved_redirects = get_option( 'saved_redirects' );
		//print '<pre>'; print_r( $saved_redirects ); print '</pre>';
	}
		
	add_action( 'init' , 'easy_redirect_init' );
	
	/*
	 * Init Actions
	 */
	function easy_redirect_init(){
		
		easy_redirect_post_types();
		
		if ( is_admin() ) {
			
			add_action( 'admin_menu' , 		'easy_redirect_menus' );
			add_action( 'add_meta_boxes' ,	'easy_redirect_meta_boxes' );
	
			add_action( 'save_post' ,					'easy_redirect_save_post' , 1 , 2 );
			add_action( 'delete_post' ,					'easy_redirect_delete_post' , 1 , 2 );
			add_action( 'transition_post_status' ,		'easy_redirect_transition_post' , 1 , 3 );
			
		} else {
			
			easy_redirects_process();
		
		}
	}
	
	/*
	 * Process redirects
	 *
	 * Called durin init
	 *
	 */
	function easy_redirects_process(){
		
		$current_url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$siteurl = trailingslashit( get_option( 'siteurl' ) );

		// don't redirect on homepage		
		if ( $current_url != $siteurl ) {
			
			$last = array_pop( explode( '/' , rtrim( $current_url , '/' ) ) );
			$saved_redirects = get_option( 'saved_redirects' );

			// if this url was listed in our redirects	
			if ( $last != '' && isset( $saved_redirects[$last]  ) ) {
				
			
				$url = str_replace( 'http://' , '' , $siteurl . $last );
				$url = str_replace( 'https://' , '' , $url );
				
				
				// if we are on the url that should be redirected
				// and if we are not on the home page ( make sure main site can never be redirected by accident )
				if ( $url == $current_url ) {
					$response = wp_remote_get( trim( $saved_redirects[$last]['dest'] ) , array( 'sslverify' => false ) );

					if ( is_wp_error( $response ) ) {
						// wp should end up with 404 page?
					} else {
						wp_redirect( esc_url_raw( $saved_redirects[$last]['dest'] ), '301' );
						exit;
					}
				}		
			}
		}
	}

	/*
	 * Add settings page
	 * 
	 * May not need except for information?
	 */	
	function easy_redirect_menus() {
		add_submenu_page('edit.php?post_type=easy_redirect', 'Settings' , 'Settings' ,'edit_themes','easy_redirect','easy_redirect_admin');
	}

	/*
	 * Register settings
	 * 
	 * May not need yet?
	 */		
	function easy_redirect_admin() {
			register_setting( 'easy_redirect_options_group',		'easy_redirect_options' );
	}

	/*
	 * Add and remove metaboxes for edit page
	 */		
	function easy_redirect_meta_boxes() {
			
			if ( isset( $_GET['post'] ) ) 
				$post_type = get_post_type( $_GET['post'] );
			
			if ( ( isset( $_GET['post_type'] ) && $_GET['post_type'] == 'easy_redirect' ) || $post_type == 'easy_redirect' ) {
	
				remove_meta_box( 'commentsdiv', 'easy_redirect', 'normal' );
				remove_meta_box( 'authordiv', 'easy_redirect', 'normal' );
				remove_meta_box( 'postexcerpt', 'easy_redirect', 'normal' );
				remove_meta_box( 'trackbacksdiv', 'easy_redirect', 'normal' );
				remove_meta_box( 'revisionsdiv', 'easy_redirect', 'normal' );
				remove_meta_box( 'formatdiv', 'easy_redirect', 'normal' );
				remove_meta_box( 'commentstatusdiv', 'easy_redirect', 'normal' );
	
				add_meta_box( 'easy_redirect_box' , 'Edit Redirection'  , 'easy_redirect_metabox' , 'easy_redirect' , 'normal' , 'high' );
	
			};
	}

	/*
	 * The Metabox for the edit page
	 */		
	function easy_redirect_metabox(){
	
		$easy_redirect_options = get_option( 'easy_redirect_options' );
	
		$post_ID = $_GET['post'];
			
		wp_nonce_field('easy_redirect_save_post','easy_redirect_save_post'); 		
			
		$redirect_settings = get_post_meta( $post_ID , 'redirect_settings' , true );
		
		$saved_redirects = get_option( 'saved_redirects' );

		?>
		<h4>Url to redirect</h4>
		<?php echo get_option( 'siteurl') ; ?>/<input type="text" name="easy_redirect_post[url]" value="<?php echo $redirect_settings['url']; ?>"/>
		<h4>Url to redirect to</h4>
		<input type="text" name="easy_redirect_post[dest]" value="<?php echo $redirect_settings['dest']; ?>"/input>
		<input type="hidden" name="easy_redirect_post[old_url]" value="<?php echo $redirect_settings['url']; ?>"/input>
		
		<?php $conflict = get_post_meta( $post_ID , 'redirect_conflict' , true ); ?>
						
		<?php if ( is_array( $conflict ) ) { ?>
		
			<?php $conflict_post = get_post( $conflict['post'] ); ?>

			<div style="background: #f9e4e0; padding: 10px 20px; margin: 10px 0;">
				<h4>Possible Conflict</h4>
				It looks like you have a similar redirection already saved:
				<div style="border-bottom: 1px solid #aaa; border-top: 1px solid #aaa;">
					<p>The redirection at <em>your_site/</em> <strong style="background:#fff;padding:0 8px;"><?php echo $conflict['url']; ?></strong> was already asigned to the Redirect named "<?php echo $conflict_post->post_title; ?>".</p>
					<p>You can <a class="button-secondary" href="<?php echo admin_url( 'post.php?post='.$conflict['post'].'&action=edit'); ?>">edit it here</a>
				</div>
				
			</div>
			
		<?php }; ?>
		
		<?php
	}

	/*
	 * Save posted info from metabox
	 *
	 * called during save_post
	 */		
	function easy_redirect_save_post( $postid , $post ){
			
		global $wpdb;
		
		// only for our post type
		if ( $post->post_type != 'easy_redirect' ) 
			return $postid;
			
		// only if nonce is good	
		if ( isset( $_POST['easy_redirect_save_post'] ) && !wp_verify_nonce( $_POST['easy_redirect_save_post'] , 'easy_redirect_save_post') || !current_user_can( 'edit_theme' ) ) 
			return $postid;

		$old_url 	= $_POST['easy_redirect_post']['old_url'];
		$url 		= strtolower( $_POST['easy_redirect_post']['url'] );
		$dest 		= strtolower( $_POST['easy_redirect_post']['dest'] );
		
		// NEED TO SANITIZE

									
		// save for use by site
		$saved_redirects = get_option( 'saved_redirects' );
		
		// delete old one...
		if ( $old_url != $url && isset( $saved_redirects[$old_url] ) ){
			unset( $saved_redirects[$old_url] );
		}
		
		// check ids too - make sure we have 1 per saved post
		foreach ( $saved_redirects as $saved_url => $saved_redirect ) {
			
			if ( isset( $saved_redirect['post'] ) && $saved_redirect['post'] == $postid ) {
				unset( $saved_redirects[$saved_url] );
			}
		}
		
		// empty out saved settings
		if ( $_POST['easy_redirect_post']['url'] == '' ) {
		
			$redirect_settings = array( 
				'url' 		=> '',
				'dest' 		=> '',
				'old_url' 	=> '',
			);
		
			delete_post_meta ( $postid, 'redirect_conflict' );
		
		// if there is not already one with the same url saved…
		} elseif ( !isset( $saved_redirects[$url] ) ) {
			
			// setup meta		
			$redirect_settings = array( 
				'url' 		=> sanitize_text_field( $url ),
				'dest' 		=> esc_url_raw( $dest ),
				'old_url' 	=> sanitize_text_field( $old_url ),
			);
			
			// setup options	
			$saved_redirects[$url] = array(
				'dest' => $dest,
				'post' => $postid,
			);

			delete_post_meta ( $postid, 'redirect_conflict' );

		// there was already a redirect using that url
		} else {
		
			// setup meta		
			$redirect_settings = array( 
				'url' 		=> '',
				'dest' 		=> '',
				'old_url' 	=> '',
			);
			
			$redirect_conflict = $saved_redirects[$url];

		}

		update_post_meta ( $postid, 'redirect_settings' , $redirect_settings );
		update_option( 'saved_redirects' , $saved_redirects );			
		update_post_meta ( $postid, 'redirect_conflict' , $redirect_conflict );
		
	}

	/*
	 * Keep track of redirects as posts change status
	 * Redirects should only be live when posts are "published"
	 *
	 */		
	function easy_redirect_transition_post( $new_status , $old , $post ){
		
		if ( $post->post_type != 'easy_redirect' ) 
			return;
				
		$saved_redirects = get_option( 'saved_redirects' );
		$redirect_settings = get_post_meta( $post->ID , 'redirect_settings' , true );
	
		if ( $new_status != 'publish' ) {
			if ( isset( $saved_redirects[$redirect_settings['url']] ) ){
				unset( $saved_redirects[$redirect_settings['url']] );
			}
		} elseif( isset( $redirect_settings['url'] ) && ( $redirect_settings['url'] != '' ) && isset( $redirect_settings['dest'] ) && ( $redirect_settings['dest'] != '' ) ) {
			
			// we should not over-write a published one with one being restored….		
			if ( !isset( $saved_redirects[$redirect_settings['url']] )) {
				$saved_redirects[$redirect_settings['url']] = array( 
					'dest' => $redirect_settings['dest'],
					'post' => $post->ID,
				);
			}
		}
		
		update_option( 'saved_redirects' , $saved_redirects );	
	}

	/*
	 * Remove redirect when deleting post
	 */		
	function easy_redirect_delete_post( $postid ){
			
		global $wpdb;
		
		$post = get_post($postid);
	
		if ( $post->post_type != 'easy_redirect' ) 
			return $postid;
	
		if ( isset( $_POST['easy_redirect_post'] ) ) {
			
			$saved_redirects = get_option( 'saved_redirects' );
			$redirect_settings = get_post_meta( $postid, 'redirect_settings' , true );
			
			if ( isset( $saved_redirects[$redirect_settings['url']] ) ){
				
				unset( $saved_redirects[$redirect_settings['url']] );
				update_option( 'saved_redirects' , $saved_redirects );
			}
	
		}
	}

	/*
	 * Register Post Type - "easy_redirect"
	 */		
	function easy_redirect_post_types() { 
	
			$easy_redirects_options = get_option('easy_redirect_options');
	
			$easy_redirects_labels = array ( 
				'menu_name'				=> __( 'Redirects', 'easy_redirect' ),
				'name' 					=> _x( 'Redirects', 'post type general name' ),
				'singular_name' 		=> _x( 'Redirect', 'post type singular name' ),
				'add_new' 				=> _x( 'Add New', 'Redirects'),
				'add_new_item'			=> __( 'Add New' ),
				'edit_item' 			=> __( 'Edit redirect' ),
				'new_item' 				=> __( 'New redirect' ),
				'view_item' 			=> __( 'View redirect' ),
				'search_items' 			=> __( 'Search redirects' ),
				'not_found' 			=> __( 'No redirects found' ),
				'not_found_in_trash' 	=> __( 'No redirects found in the trash' ),
			);
			
			$easy_redirects_args = array (
				'labels' 				=> $easy_redirects_labels,
				'has_archive'			=> false,
				'public' 				=> false,
				'show_ui' 				=> true,
				'publicly_queryable'	=> false,
				'exclude_from_search'	=> true,
				'hierarchical' 			=> false,
				'revisions'				=> false,
				'capability_type' 		=> 'post',
				'query_var'				=> false,			
				'supports'				=> array( 'title' )
			);
			
			/* Register our post_type */
			register_post_type( 'easy_redirect', $easy_redirects_args );
	
			register_taxonomy( 'easy_redirect_category', array( 'easy_redirect_category' ), array(
				'labels' => array(
					'name' 				=> 	__( 'Categories'  ),
	  				'singular_name' 	=> 	__( 'Category'  ),
	  				'add_new_item'		=> 	__( 'Add New Category'  ),
				),
				'show_ui' 				=> true,
				'query_var' 			=> true,
				'hierarchical' 			=> true,
				'rewrite' 				=> array( 'slug' =>__( 'easy_redirect_category' ) ),
			));	
	}
?>