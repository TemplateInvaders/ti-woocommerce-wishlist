// Misc
(function ($) {
	$(document).ready(function () {

		$('#tinvwl_manage_actions, #tinvwl_product_actions').addClass('form-control').parent().wrapInner('<div class="tinvwl-input-group tinvwl-no-full">').find('button').wrap('<span class="tinvwl-input-group-btn">');

		$('.tinv-lists-nav').each(function () {
			if (!$.trim($(this).html()).length) {
				$(this).remove();
			}
		});

		$('body').on('click', '.social-buttons .social[title!=email]', function (e) {
			var newWind = window.open($(this).attr('href'), $(this).attr('title'), "width=420,height=320,resizable=yes,scrollbars=yes,status=yes");
			if (newWind) {
				newWind.focus();
				e.preventDefault();
			}
		});

		$('body').on('click', '.tinv-wishlist .tinv-overlay, .tinv-wishlist .tinv-close-modal, .tinv-wishlist .tinvwl_button_close', function (e) {
			e.preventDefault();
			$(this).parents('.tinv-modal:first').removeClass('tinv-modal-open');
		});

		$('body').on('click', '.tinv-wishlist .tinvwl-btn-onclick', function (e) {
			var url = $(this).data('url');
			if (url) {
				e.preventDefault();
				window.location = $(this).data('url');
			}
		});

		var navigationButton = $('.tinv-wishlist .navigation-button');
		if (navigationButton.length) {
			navigationButton.each(function () {
				var navigationButtons = $(this).find('> li');
				if (navigationButtons.length < 5) {
					navigationButtons.parent().addClass('tinvwl-btns-count-' + navigationButtons.length);
				}
			});
		}

		$('.tinv-login .showlogin').unbind("click").on('click', function (e) {
			e.preventDefault();
			$(this).closest('.tinv-login').find('.login').toggle();
		});

		$('.tinv-wishlist table.tinvwl-table-manage-list tfoot td').each(function () {
			$(this).toggle(!!$(this).children().not('.look_in').length || !!$(this).children('.look_in').children().length);
		});

	});

	$(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function () {
		var has_products = !('0' == $('.wishlist_products_counter_number').html() || '' == $('.wishlist_products_counter_number').html());
		$('.wishlist_products_counter').toggleClass('wishlist-counter-with-products', has_products);
	});

	update_cart_hash();


})(jQuery);

function update_cart_hash() {
	jQuery(document.body).on('wc_fragments_loaded.wishlist wc_fragments_refreshed.wishlist', function () {
		if (typeof wc_cart_fragments_params === 'undefined') {
			return false;
		}

		cart_hash_key = wc_cart_fragments_params.cart_hash_key;
		localStorage.setItem(cart_hash_key, localStorage.getItem(cart_hash_key) + (new Date()).getTime());
		sessionStorage.setItem(cart_hash_key, sessionStorage.getItem(cart_hash_key) + (new Date()).getTime());
		jQuery(document.body).off('wc_fragments_loaded.wishlist wc_fragments_refreshed.wishlist');
	});
}
