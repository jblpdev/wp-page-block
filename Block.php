<?php

require_once ABSPATH . 'wp-admin/includes/file.php';

class Block
{
	//--------------------------------------------------------------------------
	// Static
	//--------------------------------------------------------------------------

	/**
	 * @field current;
	 * @since 0.3.0
	 * @private
	 */
	private static $current = null;

	/**
	 * Return the block being rendered.
	 * @since 0.3.0
	 */
	public static function get_current()
	{
		return Block::$current;
	}

	//--------------------------------------------------------------------------
	// Fields
	//--------------------------------------------------------------------------

	/**
	 * @field post_id
	 * @private
	 * @since 0.3.0
	 */
	private $post_id = null;

	/**
	 * @field page_id
	 * @private
	 * @since 0.2.0
	 */
	private $page_id = null;

	/**
	 * @field infos
	 * @private
	 * @since 1.0.0
	 */
	public $infos = array();

	//--------------------------------------------------------------------------
	// Methods
	//--------------------------------------------------------------------------

	/**
	 * @constructor
	 * @since 1.0.0
	 */
	public function __construct($post_id, $page_id, $infos)
	{
		$this->post_id = $post_id;
		$this->page_id = $page_id;

		$this->infos = $infos;
		$this->infos['template_file'] = isset($infos['template_file']) ? $infos['template_file'] : 'block.twig';
		$this->infos['outline_file'] = isset($infos['outline_file']) ? $infos['outline_file'] : 'outline.twig';
		$this->infos['preview_file'] = isset($infos['preview_file']) ? $infos['preview_file'] : 'preview.twig';
		$this->infos['class_file'] = isset($infos['class_file']) ? $infos['class_file'] : null;
		$this->infos['class_name'] = isset($infos['class_name']) ? $infos['class_name'] : null;
	}

	/**
	 * Returns the block id.
	 * @method get_id
	 * @since 0.3.0
	 */
	public function get_id()
	{
		return $this->id;
	}

	/**
	 * Returns the post where the block data is stored.
	 * @method get_post_id
	 * @since 0.3.0
	 */
	public function get_post_id()
	{
		return $this->post_id;
	}

	/**
	 * Returns the page where the block is added.
	 * @method get_id
	 * @since 0.3.0
	 */
	public function get_page_id()
	{
		return $this->page_id;
	}

	/**
	 * Returns the block name.
	 * @method get_name
	 * @since 0.3.0
	 */
	public function get_name()
	{
		return $this->infos['name'];
	}

	/**
	 * Returns the block description.
	 * @method get_description
	 * @since 0.3.0
	 */
	public function get_description()
	{
		return $this->infos['description'];
	}

	/**
	 * Returns whether this block can be edited.
	 * @method is_editable
	 * @since 1.0.0
	 */
	public function is_editable()
	{
		return true;
	}

	/**
	 * Returns whether this block can be deleted.
	 * @method is_deletable
	 * @since 1.0.0
	 */
	public function is_deletable()
	{
		return true;
	}

	/**
	 * Returns whether this block can be copied.
	 * @method is_copyable
	 * @since 1.0.0
	 */
	public function is_copyable()
	{
		return true;
	}

	/**
	 * Returns whether this block can be moved.
	 * @method is_movable
	 * @since 1.0.0
	 */
	public function is_movable()
	{
		return true;
	}

	/**
	 * Renders the outline template.
	 * @method render_outline
	 * @since 1.0.0
	 */
	public function render_outline()
	{
		$this->render($this->infos['outline_file'], Timber::get_context());
	}

	/**
	 * Renders the block preview template.
	 * @method render_preview
	 * @since 1.0.0
	 */
	public function render_preview($options = array())
	{
		$current = Block::$current;

		Block::$current = $this;

		$context = Timber::get_context();
		$context['page_url'] = get_edit_post_link($this->page_id);
		$context['disable'] = isset($options['disable']) ? $options['disable'] : false;
		$context['into_id'] = isset($options['into_id']) ? $options['into_id'] : 0;
		$context['area_id'] = isset($options['area_id']) ? $options['area_id'] : 0;
		$context['header'] = apply_filters('wpb/block_preview_header', '', $this);
		$context['footer'] = apply_filters('wpb/block_preview_footer', '', $this);

		$this->render($this->infos['preview_file'], $context);

		Block::$current = $current;
	}

	/**
	 * Renders the block template.
	 * @method render_template
	 * @since 1.0.0
	 */
	public function render_template()
	{
		$current = Block::$current;
		Block::$current = $this;
		$this->render($this->infos['template_file'], Timber::get_context());
		Block::$current = $current;
	}

	/**
	 * Renders a specific template.
	 * @method render
	 * @since 1.0.0
	 */
	public function render($template, $context)
	{
		$this->on_render($template, $context);

		$locations = Timber::$locations;

		Timber::$locations = array_merge($this->get_render_location(), Timber::$locations);

		$context['block_buid'] = $this->infos['buid'];
		$context['block_name'] = $this->infos['name'];
		$context['block_description'] = $this->infos['description'];
		$context['page_id'] = $this->page_id;
		$context['post_id'] = $this->post_id;
		$context['page'] = new TimberPost($this->page_id);
		$context['post'] = new TimberPost($this->post_id);

		ob_start();
		Timber::render($template, $context);
		$render = ob_get_contents();
		$render = apply_filters('wpb/render', $render, $this);
		ob_end_clean();

		echo $render;

		Timber::$locations = $locations;
	}

	/**
	 * Returns the template locations.
	 * @method get_render_location
	 * @since 1.0.0
	 */
	public function get_render_location()
	{
		return array($this->infos['path']);
	}

	//--------------------------------------------------------------------------
	// Events
	//--------------------------------------------------------------------------

	/**
	 * Called when the block is about to be rendered.
	 * @method on_render
	 * @since 1.0.0
	 */
	protected function on_render($template, array &$data)
	{

	}

	/**
	 * Returns the output of a function.
	 * @method get_output
	 * @since 0.3.0
	 */
	protected function get_output($func)
	{
		ob_start();
		$func();
		$data = ob_get_contents();
		ob_end_clean();
		return $data;
	}
}


