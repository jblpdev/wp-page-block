/*

*/
(function($) {

$(document).ready(function() {

	$('.wp-admin.post-type-page').each(function(i, element) {

		if ($(element).find('#poststuff').length == 0)
			return

		$(document.body).toggleClass('wp-page-block-post-editor-disabled', $('.block').length > 0)

		var blockPickerAreaId = null
		var blockPickerPostId = null
		var blockPickerPageId = null

		/**
		 * @since 0.3.0
		 */
		var showBlockPicker = window.wpbShowBlockPicker = function() {
			blockPickerAreaId = $(this).attr('data-area-id') || 0
			blockPickerPostId = $(this).closest('[data-post-id]').attr('data-post-id') || 0
			blockPickerPageId = $(this).closest('[data-page-id]').attr('data-page-id') || 0
			$('.block-picker-modal').addClass('block-picker-modal-visible')
		}

		/**
		 * @since 0.3.0
		 */
		var hideBlockPicker = window.wpbHideBlockPicker = function() {
			$('.block-picker-modal').removeClass('block-picker-modal-visible')
		}

		/**
		 * @since 0.3.0
		 */
		var showBlockEditor = window.wpbShowBlockEditor = function(url, source) {
			$('.block-edit-modal').addClass('block-edit-modal-visible')
			$('.block-edit-modal iframe').attr('src', url)
		}

		/**
		 * @since 0.3.0
		 */
		var hideBlockEditor = window.wpbHideBlockEditor = function() {
			$('.block-edit-modal').removeClass('block-edit-modal-visible')
			$('.block-edit-modal iframe').attr('src', '')
		}

		/**
		 * @since 0.3.0
		 */
		var refreshBlockPreview = window.wpbRefreshBlock = function(postId) {

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
		 * @since 0.3.0
		 */
		var appendBlock = function(blocks, buid) {

			var pageId = $('#post_ID').val()
			if (pageId == null)  {
				return;
			}

			$.post(ajaxurl, {
				'action': 'add_page_block',
				'buid': buid,
				'page_id': pageId,
				'into_id': blockPickerPostId,
				'area_id': blockPickerAreaId,
			}, function(result) {
				blocks.append(setupBlock(result))
			})

			$(document.body).addClass('wp-page-block-post-editor-disabled')
		}

		/**
		 * @since 0.3.0
		 */
		var setupBlock = function(block) {

			block = $(block)

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

			block.find('.blocks').sortable()
			block.find('.blocks').disableSelection()

			return block
		}

		$('.blocks').sortable()
		$('.blocks').disableSelection()
		$('.blocks').each(function(i, element) {
			setupBlock(element)
		})

		$('#wpb_block_list').on('click', '.block-picker-modal-show', showBlockPicker)
		$('#wpb_block_list').on('click', '.block-picker-modal-hide', hideBlockPicker)
		$('#wpb_block_list').on('click', '.block-edit-modal-hide', hideBlockEditor)

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

			appendBlock(blocks, buid)

			hideBlockPicker()
		})
	})
})

})(jQuery);