(function($) {

$.fn.wpb_modal = function(options) {

	var element = $(this)

	if (typeof options === 'string') {

		switch (options) {
			case 'show':
				element.data('wpb_modal').show()
				break
			case 'hide':
				element.data('wpb_modal').hide()
				break
		}

		return this
	}

	options = options || {}

	var instance = {

		show: function() {
			element.toggleClass('block-metabox-modal-visible', true)
			if (options.onShow) {
				options.onShow()
			}
		},

		hide: function() {
			element.toggleClass('block-metabox-modal-visible', false)
			if (options.onHide) {
				options.onHide()
			}
		}
	}

	element.data('wpb_modal', instance)
	element.find('.block-metabox-modal-hide').on('click', function() {
		instance.hide()
	})
}

$(document).ready(function() {

	//--------------------------------------------------------------------------
	// Public Functions
	//--------------------------------------------------------------------------

	/**
	 * @function wpb_refreshBlock
	 * @since 1.0.0
	 */
	window.wpb_refreshBlock = function(postId) {

		var content = $('[data-post-id="' + postId + '"] .block-content').addClass('block-content-updating')

		$.ajax({
			url: $('[data-post-id="' + postId + '"]').attr('data-page-url'),
			success: function(html) {
				html = $(html).find('[data-post-id="' + postId + '"] .block-content')
				content.replaceWith(html)
				content.removeClass('block-content-updating')
			}
		})

		$('#wpb-edit-modal').wpb_modal('hide')
	}

	/**
	 * @function wpb_appendBlock
	 * @since 1.0.0
	 */
	window.wpb_appendBlock = function(blocks, buid, intoId, areaId, callback) {

		var pageId = $('#post_ID').val()
		if (pageId == null)  {
			return;
		}

		$.post(ajaxurl, {
			'action': 'add_page_block',
			'buid': buid,
			'page_id': pageId,
			'into_id': intoId,
			'area_id': areaId,
		}, function(result) {

			var block = createBlock(result)

			if (callback) {
				callback(block)
			}

			blocks.append(block)
		})

		$(document.body).addClass('wp-page-block-post-editor-disabled')
	}

	/**
	 * @function wpb_replaceBlock
	 * @since 1.0.0
	 */
	window.wpb_replaceBlock = function(postId, buid, callback) {

		var block = $('[data-post-id="' + postId + '"]')

		var pageId = block.attr('data-page-id')

		$.post(ajaxurl, {
			'action': 'remove_page_block',
			'post_id': postId,
			'page_id': pageId
		}, function() {

			wpb_appendBlock(block.closest('.blocks'), buid, null, null, callback)

			block.remove()
		})

		block.find('.block-content').addClass('block-content-updating')
	}

	//--------------------------------------------------------------------------
	// Private Functions
	//--------------------------------------------------------------------------

	/**
	 * @function createBlock
	 * @since 1.0.0
	 */
	var createBlock = function(block) {

		block = $(block)

		if (block.is('.disable')) {
			return
		}

		var cancel = function(e) {
			e.preventDefault()
			e.stopPropagation()
		}

		block.find('.block-edit a').on('mousedown', cancel)
		block.find('.block-move a').on('mousedown', cancel)
		block.find('.block-copy a').on('mousedown', cancel)
		block.find('.block-remove a').on('mousedown', cancel)

		var onEditButtonClick = function(e) {

			cancel(e)

			var href = $(this).attr('href')

			$('#wpb-edit-modal').wpb_modal('show')
			$('#wpb-edit-modal iframe').attr('src', href)
		}

		var onMoveButtonClick = function(e) {
			$('#wpb-move-modal').wpb_modal('show')
		}

		var onCopyButtonClick = function(e) {
			$('#wpb-copy-modal').wpb_modal('show')
		}

		var onRemoveButtonClick = function(e) {

			cancel(e)

			var answer = confirm('This block will be removed, continue ?')
			if (answer) {

				var postId = $(this).attr('data-post-id')
				var pageId = $(this).attr('data-page-id')

				$.post(ajaxurl, {
					'action': 'remove_page_block',
					'post_id': postId,
					'page_id': pageId
				})

				$(this).closest('.block[data-post-id="' + postId + '"]').remove()

				$(document.body).toggleClass('wp-page-block-post-editor-disabled', $('.block').length)
			}
		}

		block.find('.block-edit a').on('click', onEditButtonClick)
		block.find('.block-copy a').on('click', onMoveButtonClick)
		block.find('.block-move a').on('click', onCopyButtonClick)
		block.find('.block-remove a').on('click', onRemoveButtonClick)

		block.on('mousedown', function() {
			var parent = block.closest('.blocks')
			var marginT = parseFloat(parent.css('margin-top'))
			var marginB = parseFloat(parent.css('margin-bottom'))
			parent.css('height', parent.get(0).scrollHeight - marginT - marginB)
		})

		block.on('mouseup', function() {
			block.closest('.blocks').css('height', '')
		})

		$('.blocks').sortable('refresh')

		return block
	}

	$('#wpb-edit-modal').wpb_modal({
		onHide: function() {
			$('#wpb-edit-modal iframe').attr('src', '')
		}
	})

	$('#wpb-pick-modal').wpb_modal()
	$('#wpb-move-modal').wpb_modal()
	$('#wpb-copy-modal').wpb_modal()

	var blockAreaId = null
	var blockPostId = null
	var blockPageId = null

	/**
	 * Initializes each blocks.
	 * @since 1.0.0
	 */
	$('.wp-admin #poststuff #wpb_block_metabox').each(function(i, element) {

		$(document.body).toggleClass('wp-page-block-post-editor-disabled', $('.block').length > 0)

		var options = {
			connectWith: '.blocks',
			cancel: '.disable, select, input',
			stop: function(event, ui) {
			 	var item = $(ui.item)
			 	var intoIdInput = item.find('> [name="_wpb_blocks_into_id[]"]')
			 	var areaIdInput = item.find('> [name="_wpb_blocks_area_id[]"]')
			 	var intoId = item.parent().closest('[data-post-id]').attr('data-post-id') || 0
			 	var areaId = item.parent().closest('[data-area-id]').attr('data-area-id') || 0
			 	intoIdInput.val(intoId)
				areaIdInput.val(areaId)
			}
		}

		$('.blocks').sortable(options)
		$('.blocks').disableSelection()
		$('.blocks').each(function(i, element) {
			createBlock(element)
		})
	})

	/**
	 * Shows the block picker.
	 * @since 1.0.0
	 */
	$('.wp-admin').on('click', '.block-add-button', function() {
		blockAreaId = $(this).attr('data-area-id') || 0
		blockPostId = $(this).closest('[data-post-id]').attr('data-post-id') || 0
		blockPageId = $(this).closest('[data-page-id]').attr('data-page-id') || 0
		$('#wpb-pick-modal').wpb_modal('show')
	})

	/**
	 * Inserts a block in the current hierarchy.
	 * @since 1.0.0
	 */
	$('.wp-admin #wpb-pick-modal .block-insert-button').on('click', function() {

		var buid = $(this).closest('.block-template-info').attr('data-buid')
		if (buid) {

			var blocks = $('.wp-admin .blocks[data-area-id="' + blockAreaId + '"]').eq(0)
			if (blocks.length == 0) {
				blocks = $('.blocks').eq(0)
			}

			wpb_appendBlock(blocks, buid, blockPostId, blockAreaId)
		}

		$('#wpb-pick-modal').wpb_modal('hide')
	})
})

})(jQuery);