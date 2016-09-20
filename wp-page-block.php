<?php
/*
Plugin Name: WP Page Blocks
Plugin URI: http://jblp.ca
Description: Creates pages using multiple blocks.
Version: 0.3.0
Author: Jean-Philippe Dery (jp@jblp.ca)
Author URI: http://jblp.ca
License: MIT
Copyright: JBLP Inc.
*/

require_once ABSPATH . 'wp-admin/includes/file.php';

define('WPB_VERSION', '1.0.0');
define('WPB_FILE', __FILE__);
define('WPB_DIR', plugin_dir_path(WPB_FILE));
define('WPB_URL', plugins_url('/', WPB_FILE));

require_once WP_CONTENT_DIR . '/plugins/wp-page-block/Block.php';
require_once WP_CONTENT_DIR . '/plugins/wp-page-block/Layout.php';
require_once WP_CONTENT_DIR . '/plugins/wp-page-block/lib/functions.php';
require_once WP_CONTENT_DIR . '/plugins/wp-page-block/lib/migrations.php';

//------------------------------------------------------------------------------
// Post Types
//------------------------------------------------------------------------------

$labels = array(
	'name'               => _x('Page Blocks', 'post type general name', 'your-plugin-textdomain' ),
	'singular_name'      => _x('Page Block', 'post type singular name', 'your-plugin-textdomain' ),
	'menu_name'          => _x('Page Blocks', 'admin menu', 'your-plugin-textdomain' ),
	'name_admin_bar'     => _x('Page Block', 'add new on admin bar', 'your-plugin-textdomain' ),
	'add_new'            => _x('Add New', 'book', 'your-plugin-textdomain' ),
	'add_new_item'       => __('Add New Page Block', 'your-plugin-textdomain' ),
	'new_item'           => __('New Page Block', 'your-plugin-textdomain' ),
	'edit_item'          => __('Edit Page Block', 'your-plugin-textdomain' ),
	'view_item'          => __('View Page Block', 'your-plugin-textdomain' ),
	'all_items'          => __('All Page Blocks', 'your-plugin-textdomain' ),
	'search_items'       => __('Search Page Blocks', 'your-plugin-textdomain' ),
	'parent_item_colon'  => __('Parent Page Blocks:', 'your-plugin-textdomain' ),
	'not_found'          => __('No page blocks found.', 'your-plugin-textdomain' ),
	'not_found_in_trash' => __('No page block found in Trash.', 'your-plugin-textdomain' )
);

register_post_type('wpb-block', array(
	'labels'             => $labels,
	'description'        => '',
	'public'             => false,
	'publicly_queryable' => false,
	'show_ui'            => true,
	'show_in_menu'       => false,
	'query_var'          => false,
	'rewrite'            => false,
	'capability_type'    => 'post',
	'has_archive'        => false,
	'hierarchical'       => false,
	'menu_position'      => null,
	'supports'           => array('revisions')
));

//------------------------------------------------------------------------------
// Actions
//------------------------------------------------------------------------------

/**
 * @activation-hook
 * @since 1.0.0
 */
register_activation_hook(__FILE__, function() {

	global $acf;

	if (is_plugin_active('timber-library/timber.php') === false ||
		version_compare(Timber::$version, '1.0.0', '<')) {
		echo 'Timber-Library version 1.0.0 or higher is required. <br> See https://wordpress.org/plugins/timber-library/';
		exit;
	}

	if (is_plugin_active('advanced-custom-fields-pro/acf.php') === false ||
		version_compare($acf->settings['version'], '5.4.0', '<')) {
		echo 'Advanced Custom Fields version 5.4.0 or higher is required. <br> See https://wordpress.org/plugins/advanced-custom-fields/';
		exit;
	}
});

/**
 * @action init
 * @since 1.0.0
 */
add_action('init', function() {

	Timber::$locations = array(WPB_DIR . 'templates/');

	$ver = get_option('wpb_version', '1.0.0');
	if ($ver === '1.0.0') {
		$ver = migrate_0_1_0_to_1_0_0();
	}

});

/**
 * @action admin_init
 * @since 1.0.0
 */
add_action('admin_init', function() {

	/**
	 * Adds a metabox on the block edit page used to store the block id and page
	 * it was added to. This metabox is hidden.
	 * @since 1.0.0
	 */
	add_meta_box('wpb_disabled_editor', 'Page', function() {

		echo 'The post editor has been disabled because this page contains blocks.';

	}, 'page', 'normal', 'high');

	wpb_block_metabox();

});

/**
 * Adds a special class to disable the post box if there is blocks on that page
 * @action admin_body_class
 * @since 1.1.0
 */
add_filter('admin_body_class', function($classes) {

	global $post;

	if ($post) {

		$page_blocks = get_post_meta($post->ID, '_wpb_blocks', true);

		if ($page_blocks) {

			foreach ($page_blocks as $page_block) {

				if (!isset($page_block['buid']) ||
					!isset($page_block['page_id']) ||
					!isset($page_block['post_id']) ||
					!isset($page_block['area_id'])) {
					continue;
				}

				return $classes . ' ' . 'wp-page-block-post-editor-disabled';
			}
		}
	}

	return $classes;

});

/**
 * Adds the required CSS and JavaScript to the admin page.
 * @action admin_enqueue_scripts
 * @since 1.0.0
 */
add_action('admin_enqueue_scripts', function() {

	foreach (wpb_block_context() as $post_type) {
		if (get_post_type() == $post_type) {
			wp_enqueue_script('wpb_admin_render_block_list_form_js', WPB_URL . 'assets/js/block-metabox.js', false, WPB_VERSION);
			wp_enqueue_style('wpb_admin_render_block_list_form_css', WPB_URL . 'assets/css/block-metabox.css', false, WPB_VERSION);
			wp_enqueue_style('wpb_admin_render_block_list_grid_css', WPB_URL . 'assets/css/admin-grid.css', false, WPB_VERSION);
		}
	}

	if (get_post_type() == 'wpb-block') {
		wp_enqueue_script('wpb_admin_render_block_list_form_js', WPB_URL . 'assets/js/block-edit.js', false, WPB_VERSION);
		wp_enqueue_style('wpb_admin_block_css', WPB_URL . 'assets/css/block-edit.css', false, WPB_VERSION);
	}

	if (is_readable(get_template_directory() . '/editor-style-shared.css')) {
		wp_enqueue_style('wpb_admin_block_css', get_template_directory_uri() . '/editor-style-shared.css', false, WPB_VERSION);
	}

});

/**
 * Moves the submit div to the bottom of the block post type page.
 * @action add_meta_boxes_block
 * @since 1.0.0
 */
add_action('add_meta_boxes_wpb-block', function() {
	remove_meta_box('submitdiv', 'wpb-block', 'side');
	add_meta_box('submitdiv', __('Save'), 'post_submit_meta_box', 'wpb-block', 'normal', 'default');
}, 0, 1);

/**
 * Renames the "Publish" button to a "Save" button on the block post type page.
 * @filter gettext
 * @since 1.0.0
 */
add_filter('gettext', function($translation, $text) {

	if (get_post_type() == 'wpb-block') {
		switch ($text) {
			case 'Publish': return 'Save';
		}
	}

	return $translation;

}, 10, 2);

//------------------------------------------------------------------------------
// Post
//------------------------------------------------------------------------------

/**
 * Saves the block order.
 * @action save_post
 * @since 1.0.0
 */
add_action('save_post', function($post_id, $post) {

	if (wp_is_post_revision($post_id)) {
		return;
	}

	if (get_post_type() == 'wpb-block') {

		$revs = wp_get_post_revisions($post_id);

		// the first revision seems to be the actual post
		$rev = array_shift($revs);
		$rev = array_shift($revs);

		if ($rev) {

			$page_id = get_post($post->post_parent)->ID;

			$page_blocks = get_post_meta($page_id, '_wpb_blocks', true);

			foreach ($page_blocks as &$page_block) {
				if ($page_block['post_id'] == $post_id) {
					$page_block['post_revision_id'] = !isset($page_block['post_revision_id']) || $page_block['post_revision_id'] == null ? $rev->ID : $page_block['post_revision_id'];
				}
			}

			update_post_meta($page_id, '_wpb_blocks', $page_blocks);
		}

		return $post_id;
	}

	foreach (wpb_block_context() as $post_type) {

		if (get_post_type() == $post_type && isset($_POST['_wpb_blocks']) && is_array($_POST['_wpb_blocks'])) {

			$page_blocks = $_POST['_wpb_blocks'];
			$page_blocks_into_id = $_POST['_wpb_blocks_into_id'];
			$page_blocks_area_id = $_POST['_wpb_blocks_area_id'];

			$page_blocks_old = get_post_meta(get_the_id(), '_wpb_blocks', true);
			$page_blocks_new = array();

			foreach ($page_blocks as $index => $post_id) {
				foreach ($page_blocks_old as $page_block_old) {
					if ($page_block_old['post_id'] == $post_id) {
						$page_block_old['post_revision_id'] = null;
						$page_block_old['into_id'] = $page_blocks_into_id[$index];
						$page_block_old['area_id'] = $page_blocks_area_id[$index];
						$page_blocks_new[] = $page_block_old;
					}
				}
			}

			update_post_meta(get_the_id(), '_wpb_blocks', $page_blocks_new);

			do_action('wpb/save_block', get_the_id(), $page_blocks_new);
		}
	}

	return $post_id; // necessary ?

}, 10, 2);

/**
 * Updates
 * @action wp_restore_post_revision
 * @since 1.0.0
 */
add_action('wp_restore_post_revision', function($post_id, $revision_id) {

}, 10, 2);

/**
 * Adds a special keyword in the block post type page url that closes the page
 * when the page is saved and redirected.
 * @filter redirect_post_location
 * @since 1.0.0
 */
add_filter('redirect_post_location', function($location, $post_id) {

	switch (get_post_type()) {
		case 'wpb-block':
			$location = $location . '#block_saved';
			break;
	}

	return $location;

}, 10, 2);

/**
 * Hides the page content and displays block instead.
 * @filter the_content
 * @since 1.0.0
 */
add_filter('the_content', function($content) {

	global $post;

	if (is_admin()) {
		return $content;
	}

	foreach (wpb_block_context() as $post_type) {

		if (get_post_type() == $post_type) {

			$page_blocks = wpb_get_blocks($post->ID);

			if ($page_blocks) {

				ob_start();

				foreach ($page_blocks as $page_block) {

					if (!isset($page_block['buid']) ||
						!isset($page_block['page_id']) ||
						!isset($page_block['post_id'])) {
						continue;
					}

					if (is_preview() === false && isset($page_block['post_revision_id'])) {
						$rev = wp_get_post_revision($page_block['post_revision_id']);
						if ($rev) {
							$page_block['post_id'] = $rev->ID;
						}
					}

					if ($page_block['into_id'] == 0) wpb_render_block_template(
						$page_block['buid'],
						$page_block['post_id'],
						$page_block['page_id']
					);
				}

				$content = ob_get_contents();

				ob_end_clean();
			}
		}
	}

	return $content;

}, 20);

//------------------------------------------------------------------------------
// AJAX
//------------------------------------------------------------------------------

/**
 * Adds a block to a page.
 * @action wp_ajax_add_page_block
 * @since 1.0.0
 */
add_action('wp_ajax_add_page_block', function() {

	global $post;

	$buid = $_POST['buid'];
	$page_id = $_POST['page_id'];
	$into_id = $_POST['into_id'];
	$area_id = $_POST['area_id'];

	if (wpb_block_template_by_buid($buid) == null) {
		return;
	}

	$post_id = wp_insert_post(array(
		'post_parent'  => $page_id,
		'post_type'    => 'wpb-block',
		'post_title'   => sprintf('Page %s : Block %s', $page_id, $buid),
		'post_content' => '',
		'post_status'  => 'publish',
	));

	$page_blocks = wpb_get_blocks($page_id);
	if ($page_blocks == null) {
		$page_blocks = array();
	}

	$page_block = array(
		'buid' => $buid,
		'area_id' => $area_id,
		'post_id' => (int) $post_id,
		'page_id' => (int) $page_id,
		'into_id' => (int) $into_id,
	);

	$page_blocks[] = $page_block;

	update_post_meta($page_id, '_wpb_blocks', $page_blocks);

	$post = get_post($page_id);

	setup_postdata($post);

	wpb_render_block_preview($buid, $post_id, $page_id);

	exit;
});

/**
 * Moves a block to another page.
 * @action wp_ajax_move_page_block
 * @since 1.0.0
 */
add_action('wp_ajax_move_page_block', function() {

	$source_page_id = $_POST['source_page_id'];
	$source_post_id = $_POST['source_post_id'];
	$target_page_id = $_POST['target_page_id'];

	$source_page_blocks = wpb_get_blocks($source_page_id);
	$target_page_blocks = wpb_get_blocks($target_page_id);

	if ($source_page_blocks == null) $source_page_blocks = array();
	if ($target_page_blocks == null) $target_page_blocks = array();

	foreach ($source_page_blocks as $i => $source_page_block) {
		if ($source_page_block['post_id'] == $source_post_id) {
			$source_page_block['into_id'] = 0;
			$source_page_block['area_id'] = 0;
			$target_page_blocks[  ] = $source_page_block;
			$source_page_blocks[$i] = null;
			continue;
		}
	}

	$source_page_blocks = array_filter($source_page_blocks, function($item) {
		return !!$item;
	});

	update_post_meta($source_page_id, '_wpb_blocks', $source_page_blocks);
	update_post_meta($target_page_id, '_wpb_blocks', $target_page_blocks);

	exit;
});

/**
 * Copies a block to another page.
 * @action wp_ajax_copy_page_block
 * @since 1.0.0
 */
add_action('wp_ajax_copy_page_block', function() {
	$source_page_id = $_POST['source_page_id'];
	$source_post_id = $_POST['source_post_id'];
	$target_page_id = $_POST['source_page_id'];
	exit;
});

/**
 * Removes a block from a page.
 * @action wp_ajax_remove_page_block
 * @since 1.0.0
 */
add_action('wp_ajax_remove_page_block', function() {

	$post_id = $_POST['post_id'];
	$page_id = $_POST['page_id'];

	$page_blocks = wpb_get_blocks($page_id);

	if ($page_blocks == null) {
		return;
	}

	$page_blocks = array_filter($page_blocks, function($page_block) use ($post_id) {

		if ($page_block['post_id'] == $post_id ||
			$page_block['into_id'] == $post_id) {
			return false;
		}

		return true;

	});

	update_post_meta($page_id, '_wpb_blocks', $page_blocks);

	wp_delete_post($post_id);
});

//------------------------------------------------------------------------------
// ACF
//------------------------------------------------------------------------------

/**
 * @filter acf/settings/load_json
 * @since 1.0.0
 */
add_filter('acf/settings/load_json', function($paths) {

	static $block_template_infos = null;

	if ($block_template_infos == null) {
		$block_template_infos = wpb_block_template_infos();
	}

	foreach ($block_template_infos as $block_template_info) {
		$paths[] = $block_template_info['path'] . '/fields';
	}

	return $paths;
});

/**
 * @filter acf/get_field_groups
 * @since 1.0.0
 */
add_filter('acf/get_field_groups', function($field_groups) {

	if (isset($_GET['post_status']) && $_GET['post_status'] === 'sync') {
		return $field_groups;
	}

	if (get_post_type() != 'wpb-block') {

		$page_blocks = wpb_block_template_infos();

		foreach ($page_blocks as $page_block) {

			$block_template = wpb_block_template_by_buid($page_block['buid']);
			if ($block_template == null) {
				continue;
			}

			$path = $block_template['path'];

			foreach ($block_template['fields'] as $json) {

				$json['ID'] = null;
				$json['style'] = 'seamless';
				$json['position'] = 'normal';
				$json['location'] = array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'block'
						),
					)
				);

				$field_groups[] = $json;
			}
		}

		return $field_groups;
	}

	$post_id = $_GET['post'];
	$page_id = $_GET['page_id'];

	$page_blocks = array_filter(wpb_get_blocks($page_id), function($page_block) use($post_id) {
		return $page_block['post_id'] == $post_id;
	});

	if ($page_blocks) foreach ($page_blocks as $page_block) {

		$block_template = wpb_block_template_by_buid($page_block['buid']);
		if ($block_template == null) {
			continue;
		}

		$path = $block_template['path'];

		foreach ($block_template['fields'] as $json) {

			$json['ID'] = null;
			$json['style'] = 'seamless';
			$json['position'] = 'normal';
			$json['location'] = array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'wpb-block'
					),
				)
			);

			$field_groups[] = $json;
		}
	}

	return $field_groups;
});

/**
 * @filter acf/get_fields
 * @since 1.0.0
 */
add_filter('acf/get_fields', function($fields, $parent) {

	if ($parent) {

		$block_template = wbp_block_template_by_field_group_key($parent['key']);

		if ($block_template) {

			if (count($block_template['styles'])) {

				$fields[] = array(
					'key' => 'field_wpb_css_style_' . md5($block_template['buid']),
					'label' => 'Style',
					'name' => 'wpb_css_style',
					'type' => 'radio',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'choices' => $block_template['styles'],
					'other_choice' => 0,
					'save_other_choice' => 0,
					'default_value' => '',
					'allow_null' => true,
					'layout' => 'vertical',
					// ACF Specificx
					'ID' => 0,
					'id' => null,
					'prefix' => 'acf',
					'class' => null,
					'value' => null,
					'_name' => 'wpb_css_style',
					'_input' => null,
					'_valid' => 1
				);
			}

			if (current_user_can('administrator')) {

				$fields[] = array(
					'key' => 'field_wpb_css_id_' . md5($block_template['buid']),
					'label' => 'CSS ID',
					'name' => 'wpb_css_id',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => ''
					),
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'maxlength' => '',
					'readonly' => 0,
					'disabled' => 0,
					// ACF Specific
					'ID' => 0,
					'id' => null,
					'prefix' => 'acf',
					'value' => null,
					'class' => null,
					'_name' => 'wpb_css_id',
					'_input' => null,
					'_valid' => 1
				);

				$fields[] = array(
					'key' => 'field_wpb_css_class_' . md5($block_template['buid']),
					'label' => 'CSS Class',
					'name' => 'wpb_css_class',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => ''
					),
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'maxlength' => '',
					'readonly' => 0,
					'disabled' => 0,
					// ACF Specific
					'ID' => 0,
					'id' => null,
					'prefix' => 'acf',
					'value' => null,
					'class' => null,
					'_name' => 'css_class',
					'_input' => null,
					'_valid' => 1
				);
			}
		}
	}

	return $fields;

}, 10, 2);

/**
 * @action acf/save_post
 * @since 1.0.0
 */
add_action('acf/save_post', function($post_id) {

	$data = isset($_POST['acf']) ? $_POST['acf'] : null;

	if ($data == null) {
		return;
	}

	if (is_array($data)) foreach ($data as $key => $val) {

		if (strpos($key, 'field_wpb_css_style_') > -1) {
			update_post_meta($post_id, 'wpb_css_style', $val);
			update_post_meta($post_id, '_wpb_css_style', $key);
			continue;
		}

		if (strpos($key, 'field_wpb_css_class_') > -1) {
			update_post_meta($post_id, 'wpb_css_class', $val);
			update_post_meta($post_id, '_wpb_css_class', $key);
			continue;
		}

		if (strpos($key, 'field_wpb_css_id_') > -1) {
			update_post_meta($post_id, 'wpb_css_id', $val);
			update_post_meta($post_id, '_wpb_css_id', $key);
			continue;
		}
	}

});
