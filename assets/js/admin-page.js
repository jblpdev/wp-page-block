(function($) {

$(document).ready(function() {

	/**
	 * @function wpbHideBlockPicker
	 * @since 1.0.0
	 */
	var hideBlockPicker = window.wpbHideBlockPicker = function() {
		$('.block-metabox-modal').removeClass('block-metabox-modal-visible')
	}

	/**
	 * @function wpbShowBlockEditor
	 * @since 1.0.0
	 */
	var showBlockEditor = window.wpbShowBlockEditor = function(url, source) {
		$('.block-edit-modal').addClass('block-edit-modal-visible')
		$('.block-edit-modal iframe').attr('src', url)
	}

	/**
	 * @function wpbHideBlockEditor
	 * @since 1.0.0
	 */
	var hideBlockEditor = window.wpbHideBlockEditor = function() {
		$('.block-edit-modal').removeClass('block-edit-modal-visible')
		$('.block-edit-modal iframe').attr('src', '')
	}

	/**
	 * @function wpbRefreshBlock
	 * @since 1.0.0
	 */
	var refreshBlock = window.wpbRefreshBlock = function(postId) {

		var content = $('[data-post-id="' + postId + '"] .block-content').addClass('block-content-updating')

		$.ajax({
			url: $('[data-post-id="' + postId + '"]').attr('data-page-url'),
			success: function(html) {
				html = $(html).find('[data-post-id="' + postId + '"] .block-content')
				content.replaceWith(html)
				content.removeClass('block-content-updating')
			}
		})
	}

	/**
	 * @function wpbReplaceBlock
	 * @since 1.0.0
	 */
	var replaceBlock = window.wpbReplaceBlock = function(postId, buid, callback) {

		var block = $('[data-post-id="' + postId + '"]')

		var pageId = block.attr('data-page-id')

		$.post(ajaxurl, {
			'action': 'remove_page_block',
			'post_id': postId,
			'page_id': pageId
		}, function() {

			appendBlock(block.closest('.blocks'), buid, null, null, callback)

			block.remove()
		})

		block.find('.block-content').addClass('block-content-updating')
	}

	/**
	 * @function wpbAppendBlock
	 * @since 1.0.0
	 */
	var appendBlock = window.wpbAppendBlock = function(blocks, buid, intoId, areaId, callback) {

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

			var block = setupBlock(result)

			if (callback) {
				callback(block)
			}

			blocks.append(block)
		})

		$(document.body).addClass('wp-page-block-post-editor-disabled')
	}

	/**
	 * @function setupBlock
	 * @since 1.0.0
	 */
	var setupBlock = function(block) {

		block = $(block)

		if (block.is('.disable')) {
			return
		}

		var cancel = function(e) {
			e.preventDefault()
			e.stopPropagation()
		}

		block.find('.block-edit a').on('mousedown', cancel)
		block.find('.block-remove a').on('mousedown', cancel)

		block.find('.block-edit a').on('click', function(e) {

			cancel(e)

			var href = $(this).attr('href')
			var post = $(this).closest('[data-post-id]').attr('data-post-id')

			showBlockEditor(href, post)
		})

		block.find('.block-remove a').on('click', function(e) {

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
		})

		block.on('mousedown', function() {
			var parent = block.closest('.blocks')
			var marginT = parseFloat(parent.css('margin-top'))
			var marginB = parseFloat(parent.css('margin-bottom'))
			parent.css('height', parent.get(0).scrollHeight - marginT - marginB)
		})

		block.on('mouseup', function() {
			block.closest('.blocks').css('height', '')
		})

		createSortable()

		return block
	}

	var sortableInitialized = false

	/**
	 * @function createSortable
	 * @since 1.0.0
	 */
	var createSortable = function() {

		if (sortableInitialized == false) {
			sortableInitialized = true

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
			return
		}

		$('.blocks').sortable('refresh')
	}

	$('.wp-admin').each(function(i, element) {

		if ($(element).find('#poststuff').length === 0 ||
			$(element).find('#wpb_block_metabox').length === 0) {
			return
		}

		$(document.body).toggleClass('wp-page-block-post-editor-disabled', $('.block').length > 0)

		var blockPickerAreaId = null
		var blockPickerPostId = null
		var blockPickerPageId = null

		/**
		 * @since 1.0.0
		 */
		var showBlockPicker = function() {
			blockPickerAreaId = $(this).attr('data-area-id') || 0
			blockPickerPostId = $(this).closest('[data-post-id]').attr('data-post-id') || 0
			blockPickerPageId = $(this).closest('[data-page-id]').attr('data-page-id') || 0
			$('#wpb-pick.block-metabox-modal').addClass('block-metabox-modal-visible')
		}

		createSortable()

		$('.blocks').each(function(i, element) {
			setupBlock(element)
		})

		$('#wpb_block_metabox').on('click', '.block-metabox-modal-show', showBlockPicker)
		$('#wpb_block_metabox').on('click', '.block-metabox-modal-hide', hideBlockPicker)
		$('#wpb_block_metabox').on('click', '.block-edit-modal-hide', hideBlockEditor)

		$('.block-template-info-action .button-insert').on('click', function() {

			var buid = $(this).closest('.block-template-info').attr('data-buid')
			if (buid == null) {
				hideBlockPicker()
				return
			}

			var blocks = $('.blocks[data-area-id="' + blockPickerAreaId + '"]').eq(0)
			if (blocks.length == 0) {
				blocks = $('.blocks').eq(0)
			}

			appendBlock(blocks, buid, blockPickerPostId, blockPickerAreaId)

			hideBlockPicker()
		})
	})
})

})(jQuery);