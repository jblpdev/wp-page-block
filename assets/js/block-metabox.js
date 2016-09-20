(function($) {

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
			 block.find('> [name="_wpb_blocks_into_id[]"]').val(intoId)
			 block.find('> [name="_wpb_blocks_area_id[]"]').val(areaId)

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

		block.on('mousedown', function() {
			var parent = block.closest('.blocks')
			var marginT = parseFloat(parent.css('margin-top'))
			var marginB = parseFloat(parent.css('margin-bottom'))
			parent.css('height', parent.get(0).scrollHeight - marginT - marginB)
		})

		block.on('mouseup', function() {
			block.closest('.blocks').css('height', '')
		})

		var onEditButtonClick = function(e) {

			cancel(e)

			var href = $(this).attr('href')

			$('#wpb-edit-modal').wpb_modal('show')
			$('#wpb-edit-modal iframe').attr('src', href)
		}

		var onMoveButtonClick = function(e) {

			cancel(e)

			var link = $(e.target).closest('a')
			var pageId = link.attr('data-page-id')
			var postId = link.attr('data-post-id')

			$('#wpb-move-modal').wpb_modal('show')
			$('#wpb-move-modal').attr('data-source-page-id', pageId)
			$('#wpb-move-modal').attr('data-source-post-id', postId)
		}

		var onCopyButtonClick = function(e) {

			cancel(e)

			var link = $(e.target).closest('a')
			var pageId = link.attr('data-page-id')
			var postId = link.attr('data-post-id')

			$('#wpb-copy-modal').wpb_modal('show')
			$('#wpb-copy-modal').attr('data-source-page-id', pageId)
			$('#wpb-copy-modal').attr('data-source-post-id', postId)
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

				$(document.body).toggleClass('wp-page-block-post-editor-disabled', $('.block').length > 0)
			}
		}

		block.find('> .block-bar > .block-actions > .block-edit a').on('mousedown', cancel)
		block.find('> .block-bar > .block-actions > .block-move a').on('mousedown', cancel)
		block.find('> .block-bar > .block-actions > .block-copy a').on('mousedown', cancel)
		block.find('> .block-bar > .block-actions > .block-remove a').on('mousedown', cancel)

		block.find('> .block-bar > .block-actions > .block-edit a').on('click', onEditButtonClick)
		block.find('> .block-bar > .block-actions > .block-copy a').on('click', onCopyButtonClick)
		block.find('> .block-bar > .block-actions > .block-move a').on('click', onMoveButtonClick)
		block.find('> .block-bar > .block-actions > .block-remove a').on('click', onRemoveButtonClick)

		$('.blocks').sortable('refresh')

		return block
	}

	$('#wpb-pick-modal').wpb_modal({
		onHide: function() {

		}
	})

	$('#wpb-edit-modal').wpb_modal({
		onHide: function() {
			$('#wpb-edit-modal iframe').attr('src', '')
		}
	})

	$('#wpb-move-modal').wpb_modal({
		onHide: function() {
			$('#wpb-move-modal .block-metabox-pages li a.selected').removeClass('selected')
			$('#wpb-move-modal').removeClass('wpb-processing')
		}
	})

	$('#wpb-copy-modal').wpb_modal({
		onHide: function() {
			$('#wpb-copy-modal .block-metabox-pages li a.selected').removeClass('selected')
			$('#wpb-copy-modal').removeClass('wpb-processing')
		}
	})

	var addingInAreaId = null
	var addingInPostId = null
	var addingInPageId = null

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
		$('.block').each(function(i, element) {
			createBlock(element)
		})
	})

	$('.wp-admin').on('click', '.button.block-add-button', function() {
		addingInAreaId = $(this).attr('data-area-id') || 0
		addingInPostId = $(this).closest('[data-post-id]').attr('data-post-id') || 0
		addingInPageId = $(this).closest('[data-page-id]').attr('data-page-id') || 0
		$('#wpb-pick-modal').wpb_modal('show')
	})

	$('.wp-admin #wpb-pick-modal').on('click', '.button.block-insert-button', function() {

		var buid = $(this).closest('.block-template-info').attr('data-buid')
		if (buid) {

			wpb_appendBlock(
				$('.block[data-page-id="' + addingInPageId + '"][data-post-id="' + addingInPostId + '"] .blocks[data-area-id="' + addingInAreaId + '"]'),
				buid,
				addingInPostId,
				addingInAreaId
			)
		}

		$('#wpb-pick-modal').wpb_modal('hide')
	})

	$.each([
		'#wpb-move-modal',
		'#wpb-copy-modal'
	], function(i, id) {

		var selected = null

		$(id + ' .block-metabox-pages').on('click', 'a', function(e) {

			e.preventDefault()

			if (selected) {
				selected.removeClass('selected')
				selected = null
			}

			selected = $(e.target).closest('a')
			selected.addClass('selected')
		})
	})

	$('#wpb-move-modal').on('click', '.button.button-primary', function(e) {

		var sourcePageId = $('#wpb-move-modal').attr('data-source-page-id')
		var sourcePostId = $('#wpb-move-modal').attr('data-source-post-id')
		var targetPageId = $('#wpb-move-modal .block-metabox-pages li a.selected').closest('li').attr('data-page-id');

		if (targetPageId == null) {
			return
		}

		$('#wpb-move-modal').addClass('wpb-processing')

		$.post(ajaxurl, {
			'action': 'move_page_block',
			'source_post_id': sourcePostId,
			'source_page_id': sourcePageId,
			'target_page_id': targetPageId
		}, function() {
			$('#wpb-move-modal').wpb_modal('hide')
		})

		$('.block[data-post-id="' + sourcePostId + '"]').remove()
	})

	$('#wpb-copy-modal').on('click', '.button.button-primary', function(e) {

		var sourcePageId = $('#wpb-copy-modal').attr('data-source-page-id')
		var sourcePostId = $('#wpb-copy-modal').attr('data-source-post-id')
		var targetPageId = $('#wpb-copy-modal .block-metabox-pages li a.selected').closest('li').attr('data-page-id');

		if (targetPageId == null) {
			return
		}

		$('#wpb-copy-modal').addClass('wpb-processing')

		$.post(ajaxurl, {
			'action': 'copy_page_block',
			'source_post_id': sourcePostId,
			'source_page_id': sourcePageId,
			'target_page_id': targetPageId
		}, function() {
			$('#wpb-copy-modal').wpb_modal('hide')
		})
	})
})

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

})(jQuery);