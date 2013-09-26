<?php
// ======================== //
//	New Translation Action  //
// ======================== //

/*
 * Handle request for a new translation of a post
 * Will create a new post with the data copied over,
 * and direct you to the edit page of the new post
 */
add_action('admin_init', 'nLingual_new_translation');
function nLingual_new_translation(){
	global $wpdb;
	if(isset($_GET['nL_new_translation'])){
		$post_id = $_GET['nL_new_translation'];

		if(!isset($_GET['_nL_nonce']) || !wp_verify_nonce($_GET['_nL_nonce'], NL_SELF))
			wp_die(__('You do not have permission to do that.', NL_TXTDMN));

		if(!isset($_GET['language']) || !nL_lang_exists($_GET['language']))
			wp_die(__('Invalid language.', NL_TXTDMN));

		$lang = $_GET['language'];

		// Load the original posts post/meta/tax data
		$orig = $wpdb->get_row($wpdb->prepare("SELECT post_title, post_type, post_content, post_excerpt, post_parent, menu_order FROM $wpdb->posts WHERE ID = %d", $post_id), ARRAY_A);
		$orig_meta = get_post_meta($post_id);
		$orig_taxs = get_object_taxonomies($orig['post_type']);
		$tax_query = array();

		// Loop through the taxonomies for this post, and get the terms (execpt for language)
		foreach($orig_taxs as $tax){
			if($tax == 'language') continue;
			$terms = wp_get_post_terms($post_id, $tax, array('fields' => 'ids'));
			$tax_query[$tax] = $terms;
		}

		// Set the language term to the requested language
		$tax_query['language'] = $lang;

		// Build the arguments for wp_insert_args
		$args = $orig;
		$args['tax_input'] = $tax_query;

		// Set the status to draft and update the title to flag it as needing translation
		$args['post_status'] = 'draft';
		$args['post_title'] = sprintf('Translate to %s: %s', nL_get_lang('name', $lang), $args['post_title']);

		// Set the post parent to be the translated parent if available
		$args['post_parent'] = nL_get_translation($orig['post_parent'], $lang);

		// Inser the new post
		$new = wp_insert_post($args);

		// Loop through the metadata and apply it to the new post (except the _translated_[lang] field, not that that should exist anyway)
		foreach($orig_meta as $key => $value){
			foreach($value as $val){
				add_post_meta($new, $key, maybe_unserialize($val));
			}
		}

		// Set the translation status
		nL_associate_posts($post_id, array($lang => $new));

		// Redirect them to the edit screen for the new post
		header('Location: '.admin_url("/post.php?post=$new&action=edit"));
		exit;
	}
}