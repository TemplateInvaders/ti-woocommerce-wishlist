// Wishlist table
(function ($) {
	//Prevent to submit wishlist table action
	$.fn.tinvwl_break_submit = function (so) {
		var sd = {
			selector: 'input, select, textarea',
			ifempty: true,
			invert: false,
			validate: function () {
				return $(this).val();
			},
			rule: function () {
				var form_elements = $(this).parents('form').eq(0).find(s.selector),
					trigger = s.invert;
				if (0 === form_elements.length) {
					return s.ifempty;
				}
				form_elements.each(function () {
					if ((trigger && !s.invert) || (!trigger && s.invert)) {
						return;
					}
					trigger = Boolean(s.validate.call($(this)));
				});
				return trigger;
			}
		};
		var s = $.extend(true, {}, sd, so);
		return $(this).each(function () {
			$(this).on('click', function (event) {
				if (!s.rule.call($(this))) {
					alert(window.tinvwl_add_to_wishlist['tinvwl_break_submit']);
					event.preventDefault();
				}
			});
		});
	};

	$(document).ready(function () {

		$('.tinvwl-break-input').tinvwl_break_submit({
			selector: '.tinvwl-break-input-filed'
		});
		$('.tinvwl-break-checkbox').tinvwl_break_submit({
			selector: 'table td input[type=checkbox]',
			validate: function () {
				return $(this).is(':checked');
			}
		});

		// Wishlist table bulk action checkbox
		$('.global-cb').on('click', function () {
			$(this).closest('table').eq(0).find('.product-cb input[type=checkbox], .wishlist-cb input[type=checkbox]').prop('checked', $(this).is(':checked'))
		});
	});
})(jQuery);
