<?php

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once WP_CONTENT_DIR . '/plugins/wp-page-block/Block.php';
require_once WP_CONTENT_DIR . '/plugins/wp-page-block/Layout.php';
require_once WP_CONTENT_DIR . '/plugins/wp-page-block/lib/page-walker.php';

$_block_template_infos_cache = null;

/**
 * @function wpb_read_json
 * @since 1.0.0
 */
function wpb_read_json($file)
{
	$json = json_decode(file_get_contents($file), true);

	if ($json == null) {
		throw new Exception("$file contains invalid JSON.");
	}

	return $json;
}

/**
 * Returns whether the user has access to a specified block for admin editing.
 * @function wpb_user_has_access
 * @since 1.0.0
 */
function wpb_user_has_access($page_block) {

	$role = isset($page_block['role']) ? $page_block['role'] : null;

	if ($role == null) {
		return true;
	}

	$trim = function($str) {
		return trim($str);
	};

	$role = array_map($trim, explode(',', $role));

	return count(array_intersect($role, wp_get_current_user()->roles)) > 0;
}

/**
 * Returns an array that contains data about all available templates.
 * @function wpb_block_template_infos
 * @since 1.0.0
 */
function wpb_block_template_infos()
{
	global $_block_template_infos_cache;

	if ($_block_template_infos_cache == null) {

		$_block_template_infos_cache = array();

		foreach (wpb_block_template_paths() as $path) {

			foreach (glob($path . '/*' , GLOB_ONLYDIR) as $path) {

				$type = str_replace(WP_CONTENT_DIR, '', $path);

				$data = wpb_read_json($path . '/block.json');
				$data['category'] = isset($data['category']) ? $data['category'] : 'Uncategorized';
				$data['fields'] = isset($data['fields']) ? $data['fields'] : array();
				$data['styles'] = isset($data['styles']) ? $data['styles'] : array();
				$data['buid'] = $type;
				$data['path'] = $path;

				if (wpb_user_has_access($data) == false) {
					continue;
				}

				foreach (glob($path . '/fields/*.json') as $file) {
					$data['fields'][] = wpb_read_json($file);
				}

				$_block_template_infos_cache[] = $data;
			}
		}

		usort($_block_template_infos_cache, function($a, $b) {
			return strcmp($a['name'], $b['name']);
		});

		$_block_template_infos_cache = apply_filters('wpb/block_template_infos', $_block_template_infos_cache);
	}

	return $_block_template_infos_cache;
}

/**
 * Returns an array that contains all templates path.
 * @function wpb_block_template_paths
 * @since 1.0.0
 */
function wpb_block_template_paths()
{
	return apply_filters('wpb/block_template_paths', array(WP_CONTENT_DIR . '/plugins/wp-page-block/blocks', get_template_directory() . '/blocks'));
}

/**
 * Returns the block template data using a block unique identifier. This
 * identifier is made from the block path relative to the app directory.
 * @function wpb_block_template_by_buid
 * @since 1.0.0
 */
function wpb_block_template_by_buid($buid)
{
	static $block_template_infos = null;

	if ($block_template_infos == null) {
		$block_template_infos = wpb_block_template_infos();
	}

	foreach ($block_template_infos as $block_template_info) {
		if ($block_template_info['buid'] == $buid) return $block_template_info;
	}

	return null;
}

/**
 * Returns the block template that contains the specified field group.
 * @function wbp_block_template_by_field_group_key
 * @since 1.0.0
 */
function wbp_block_template_by_field_group_key($key)
{
	static $block_template_infos = null;

	if ($block_template_infos == null) {
		$block_template_infos = wpb_block_template_infos();
	}

	foreach ($block_template_infos as $block_template_info) {
		foreach ($block_template_info['fields'] as $field) {
			if ($field['key'] == $key) {
				return $block_template_info;
			}
		}
	}

	return null;
}

/**
 * @function wpb_block_metabox
 * @since 1.0.0
 */
function wpb_block_metabox()
{
	foreach (wpb_block_context() as $post_type) {

		add_meta_box('wpb_block_metabox', wpb_block_metabox_title($post_type), function() {

			$block_template_infos = wpb_block_template_infos();
			$block_template_paths = wpb_block_template_paths();

			$filter = function($page_block) {
				return wpb_block_template_by_buid($page_block['buid']);
			};

			$page_blocks = wpb_get_blocks(get_the_id());

			if ($page_blocks) {
				$page_blocks = array_filter($page_blocks, $filter);
			}

			$categories = array();

			foreach ($block_template_infos as $block_template_info) {
				$categories[$block_template_info['category']] = array();
			}

			foreach ($block_template_infos as $block_template_info) {
				$categories[$block_template_info['category']][] = $block_template_info;
			}

			ksort($categories);

			$pages = wp_list_pages(array(
				'title_li' => '',
				'echo' => false,
				'walker' => new WPB_Walker_Page()
			));

			$data = Timber::get_context();
			$data['pages'] = $pages;
			$data['categories'] = $categories;
			$data['page_blocks'] = $page_blocks;
			$data['block_template_infos'] = $block_template_infos;
			$data['block_template_paths'] = $block_template_paths;
			Timber::render('block-metabox.twig', $data);

		}, $post_type, 'normal', wpb_block_metabox_priority($post_type));

		add_filter('postbox_classes_' . $post_type . '_wpb_block_metabox', function($classes = array()) {
			$classes[] = 'wpb-postbox';
			$classes[] = 'seamless';
			return $classes;
		});
	}
}

/**
 * @function wpb_block_context
 * @since 1.0.0
 */
function wpb_block_context()
{
	return apply_filters('wpb/block_context', array('page'));
}

/**
 * @function wpb_block_metabox_title
 * @since 1.0.0
 */
function wpb_block_metabox_title($post_type)
{
	return apply_filters('wpb/block_metabox_title', 'Blocks', $post_type);
}

/**
 * @function wpb_block_metabox_priority
 * @since 1.0.0
 */
function wpb_block_metabox_priority($post_type)
{
	return apply_filters('wpb/block_metabox_priority', 'low', $post_type);
}

/**
 * @function wpb_get_blocks
 * @since 1.0.0
 */
function wpb_get_blocks($page_id)
{
	return get_post_meta($page_id, '_wpb_blocks', true);
}

/**
 * @function wpb_get_block
 * @since 1.0.0
 */
function wpb_get_block($buid, $post_id, $page_id)
{
	$block_template = wpb_block_template_by_buid($buid);

	if ($block_template == null) {
		return null;
	}

	$class_file = isset($block_template['class_file']) ? $block_template['class_file'] : null;
	$class_name = isset($block_template['class_name']) ? $block_template['class_name'] : null;
	require_once $block_template['path'] . '/' . $class_file;

	return new $class_name($post_id, $page_id, $block_template);
}

/**
 * @function wpb_block_edit_link
 * @since 0.3.0
 */
function wpb_render_block_edit_link()
{
	$block = Block::get_current();
	if ($block == null) {
		return;
	}

	if ($block->is_editable()) {
		$context = Timber::get_context();
		$context['post_id'] = $block->get_post_id();
		$context['page_id'] = $block->get_page_id();
		Timber::render('block-edit-link.twig', $context);
	}
}

/**
 * @function wpb_render_block_copy_link
 * @since 0.3.0
 */
function wpb_render_block_copy_link()
{
	$block = Block::get_current();
	if ($block == null) {
		return;
	}

	if ($block->is_copyable()) {
		$context = Timber::get_context();
		$context['post_id'] = $block->get_post_id();
		$context['page_id'] = $block->get_page_id();
		Timber::render('block-copy-link.twig', $context);
	}
}

/**
 * @function wpb_render_block_move_link
 * @since 0.3.0
 */
function wpb_render_block_move_link()
{
	$block = Block::get_current();
	if ($block == null) {
		return;
	}

	if ($block->is_movable()) {
		$context = Timber::get_context();
		$context['post_id'] = $block->get_post_id();
		$context['page_id'] = $block->get_page_id();
		Timber::render('block-move-link.twig', $context);
	}
}

/**
 * @function wpb_render_block_remove_link
 * @since 0.3.0
 */
function wpb_render_block_remove_link()
{
	$block = Block::get_current();
	if ($block == null) {
		return;
	}

	if ($block->is_deletable()) {
		$context = Timber::get_context();
		$context['post_id'] = $block->get_post_id();
		$context['page_id'] = $block->get_page_id();
		Timber::render('block-remove-link.twig', $context);
	}
}

/**
 * @function wpb_render_block_area
 * @since 0.3.0
 */
function wpb_render_block_area($area_id)
{
	$block = Block::get_current();
	if ($block == null) {
		return;
	}

	$page_id = $block->get_page_id();
	$post_id = $block->get_post_id();

	echo '<ul class="blocks" data-area-id="' . $area_id . '">';

	$page_blocks = wpb_get_blocks($page_id);
	$page_blocks = apply_filters('wpb/children_blocks', $page_blocks, $block);

	if ($page_blocks) {

		foreach ($page_blocks as $page_block) {

			if (!isset($page_block['buid']) ||
				!isset($page_block['page_id']) ||
				!isset($page_block['post_id']) ||
				!isset($page_block['area_id'])) {
				continue;
			}

			$disable = isset($page_block['disable']);

			if ($page_block['into_id'] == $post_id &&
				$page_block['area_id'] == $area_id) {
				wpb_render_block_preview(
			 		$page_block['buid'],
			 		$page_block['post_id'],
			 		$page_block['page_id'],
			 		array(
			 			'disable' => $disable,
			 			'into_id' => $post_id,
			 			'area_id' => $area_id
			 		)
				);
			}
		}
	}

	echo '</ul>';

	echo '<div class="button block-add-button" data-area-id="' . $area_id . '">Add block</div>';
}

/**
 * @function wpb_render_block_outline
 * @since 1.0.0
 */
function wpb_render_block_outline($buid)
{
	wpb_get_block($buid, 0, 0)->render_outline();
}

/**
 * @function wpb_render_block_preview
 * @since 1.0.0
 */
function wpb_render_block_preview($buid, $post_id, $page_id, $options = array())
{
	wpb_get_block($buid, $post_id, $page_id)->render_preview($options);
}

/**
 * @function wpb_render_block_template
 * @since 1.0.0
 */
function wpb_render_block_template($buid, $post_id, $page_id)
{
	wpb_get_block($buid, $post_id, $page_id)->render_template();
}

/**
 * @function wpb_render_block_children
 * @since 1.0.0
 */
function wpb_render_block_children($area_id)
{
	Block::get_current()->render_children($area_id);
}

/**
 * @function wpb_render_block_attr
 * @since 1.0.0
 */
function wpb_render_block_attr($post, $base) {

	$id = get_field('wpb_css_id', $post);
	$class = get_field('wpb_css_class', $post);
	$style = get_field('wpb_css_style', $post);

	if ($id == null) {
		$id = sprintf('%s-%s', $base, $post->ID);
	} else {
		$id = preg_replace('/[^a-zA-Z0-9]+/mis', '-', $id);
	}

	if ($class) {
		$class = preg_replace('/[^a-zA-Z0-9]+/mis', '-', $class);
	}

	if ($style) {
		$style = preg_replace('/[^a-zA-Z0-9]+/mis', '-', $style);
		$style = sprintf('%s-%s', $base, $style);
	}

	return strtr('id="{id}" class="{base} {class} {style}"', array(
		'{id}'    => $id,
		'{base}'  => $base,
		'{class}' => $class,
		'{style}' => $style
	));
}

//------------------------------------------------------------------------------
// Twig Filters
//------------------------------------------------------------------------------

TimberHelper::function_wrapper('wpb_render_block_edit_link');
TimberHelper::function_wrapper('wpb_render_block_copy_link');
TimberHelper::function_wrapper('wpb_render_block_move_link');
TimberHelper::function_wrapper('wpb_render_block_remove_link');
TimberHelper::function_wrapper('wpb_render_block_outline');
TimberHelper::function_wrapper('wpb_render_block_preview');
TimberHelper::function_wrapper('wpb_render_block_template');
TimberHelper::function_wrapper('wpb_render_block_children');
TimberHelper::function_wrapper('wpb_render_block_area');
TimberHelper::function_wrapper('wpb_render_block_attr');

