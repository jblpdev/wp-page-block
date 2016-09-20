<?php

require_once WP_CONTENT_DIR . '/plugins/wp-page-block/Block.php';

/**
 * @class Layout
 * @since 0.3.0
 */
class Layout extends Block
{
	/**
	 * @method is_editable
	 * @since 0.3.0
	 */
	public function is_editable()
	{
		return false;
	}

	/**
	 * Returns whether this block can be copied.
	 * @method is_copyable
	 * @since 1.0.0
	 */
	public function is_copyable()
	{
		// Features to copy layouts are not completed yet.
		return false;
	}

	/**
	 * Returns whether this block can be moved.
	 * @method is_movable
	 * @since 1.0.0
	 */
	public function is_movable()
	{
		// Features to move layouts are not completed yet.
		return false;
	}

	/**
	 * Renders a specific area of this block.
	 * @method render_children
	 * @since 0.3.0
	 */
	public function render_children($area_id)
	{
		$page_id = $this->get_page_id();
		$post_id = $this->get_post_id();

		$page_blocks = wpb_get_blocks($page_id);
		$page_blocks = apply_filters('wpb/children_blocks', $page_blocks, $this);

		if ($page_blocks) {

			foreach ($page_blocks as $page_block) {

				if (!isset($page_block['buid']) ||
					!isset($page_block['page_id']) ||
					!isset($page_block['post_id'])) {
					continue;
				}

				if ($page_block['into_id'] == $post_id &&
					$page_block['area_id'] == $area_id) {

					wpb_render_block_template(
						$page_block['buid'],
						$page_block['post_id'],
						$page_block['page_id']
					);
				}
			}
		}
	}
}