// Add to wishlist
(function ($) {

	//Add to wishlist main function.
	$.fn.tinvwl_to_wishlist = function (so) {
		var sd = {
			api_url: window.location.href,
			text_create: window.tinvwl_add_to_wishlist['text_create'],
			text_already_in: window.tinvwl_add_to_wishlist['text_already_in'],
			class: {
				dialogbox: '.tinvwl_add_to_select_wishlist',
				select: '.tinvwl_wishlist',
				newtitle: '.tinvwl_new_input',
				dialogbutton: '.tinvwl_button_add'
			},
			redirectTimer: null,
			onPrepareList: function () {
			},
			onGetDialogBox: function () {
			},
			onPrepareDialogBox: function () {
				if (!$('body > .tinv-wishlist').length) {
					$('body').append($('<div>').addClass('tinv-wishlist'));
				}
				$(this).appendTo('body > .tinv-wishlist');
			},
			onCreateWishList: function (wishlist) {
				$(this).append($('<option>').html(wishlist.title).val(wishlist.ID).toggleClass('tinv_in_wishlist', wishlist.in));
			},
			onSelectWishList: function () {
			},
			onDialogShow: function (modal) {
				$(modal).addClass('tinv-modal-open');
				$(modal).removeClass('ftinvwl-pulse');
			},
			onDialogHide: function (modal) {
				$(modal).removeClass('tinv-modal-open');
				$(modal).removeClass('ftinvwl-pulse');
			},
			onInited: function () {
			},
			onClick: function () {
				if ($(this).is('.disabled-add-wishlist')) {
					return false;
				}
				if ($(this).is('.ftinvwl-animated')) {
					$(this).addClass('ftinvwl-pulse');
				}
				if (this.tinvwl_dialog) {
					this.tinvwl_dialog.show_list.call(this);
				} else {
					s.onActionProduct.call(this);
				}
				update_cart_hash();
			},
			onPrepareDataAction: function () {
			},
			filterProductAlreadyIn: function (WList) {
				var WList = WList || [],
					data = {};
				$('form.cart[method=post], .woocommerce-variation-add-to-cart').find('input, select').each(function () {
					var name_elm = $(this).attr('name'),
						type_elm = $(this).attr('type'),
						value_elm = $(this).val();
					if ('checkbox' === type_elm || 'radio' === type_elm) {
						if ($(this).is(':checked')) {
							data['form' + name_elm] = value_elm;
						}
					} else {
						data['form' + name_elm] = value_elm;
					}
				});
				data = data['formvariation_id'];
				return WList.filter(function (wishlist) {
					if ('object' === typeof wishlist.in && 'string' === typeof data) {
						var number = parseInt(data);
						return 0 <= wishlist.in.indexOf(number);
					}
					return wishlist.in;
				});
			},
			onMultiProductAlreadyIn: function (WList) {
				var WList = WList || [];
				WList = s.onPrepareList.call(WList) || WList;
				WList = s.filterProductAlreadyIn.call(this, WList) || WList;
				$(this).parent().parent().find('.already-in').remove();
				var text = '';
				switch (WList.length) {
					case 0:
						break;
					default:
						var text = $('<ul>');
						$.each(WList, function (k, wishlist) {
							text.append($('<li>').html($('<a>').html(wishlist.title).attr({
								href: wishlist.url
							})).val(wishlist.ID));
						});
						break;
				}
				if (text.length) {
					$(this).closest('.tinv-modal-inner').find('img').after($('<div>').addClass('already-in').html(s.text_already_in + ' ').append(text));
				}
			},
			onAction: {
				redirect: function (url) {
					if (s.redirectTimer) {
						clearTimeout(s.redirectTimer);
					}
					s.redirectTimer = window.setTimeout(function () {
						window.location.href = url;
					}, 4000);
				},
				force_redirect: function (url) {
					window.location.href = url;
				},
				wishlists: function (wishlist) {
					$(this).attr('data-tinv-wl-list', wishlist);
				},
				msg: function (html) {
					if (!html) {
						return false;
					}
					var $msg = $(html).eq(0);
					if (!$('body > .tinv-wishlist').length) {
						$('body').append($('<div>').addClass('tinv-wishlist'));
					}
					$('body > .tinv-wishlist').append($msg);
					$msg.on('click', '.tinv-close-modal, .tinvwl_button_close, .tinv-overlay', function (e) {
						e.preventDefault();
						$msg.remove();
						if (s.redirectTimer) {
							clearTimeout(s.redirectTimer);
						}
					});
				},
				status: function (status) {
					if (status) {
						$(this).addClass('tinvwl-product-in-list');
					}
				},
				removed: function (status) {
					if (status) {
						$(this).removeClass('tinvwl-product-in-list').removeClass('tinvwl-product-make-remove').attr('data-tinv-wl-action', 'addto');
					}
				},
				make_remove: function (status) {
					$(this).toggleClass('tinvwl-product-make-remove', status).attr('data-tinv-wl-action', status ? 'remove' : 'addto');
				},
				fragments: function (data) {
					if (typeof wc_cart_fragments_params === 'undefined') {
						$.each(data, function (key, value) {
							$(key).replaceWith(value);
						});
						return false;
					}
					var $supports_html5_storage;
					try {
						$supports_html5_storage = ('sessionStorage' in window && window.sessionStorage !== null);
						window.sessionStorage.setItem('wc', 'test');
						window.sessionStorage.removeItem('wc');
						window.localStorage.setItem('wc', 'test');
						window.localStorage.removeItem('wc');
					} catch (err) {
						$supports_html5_storage = false;
					}
					if ($supports_html5_storage) {
						try {
							var wc_fragments = $.parseJSON(sessionStorage.getItem(wc_cart_fragments_params.fragment_name)),
								cart_hash_key = wc_cart_fragments_params.ajax_url.toString() + '-wc_cart_hash',
								cart_hash = sessionStorage.getItem(cart_hash_key),
								cookie_hash = Cookies.get('woocommerce_cart_hash'),
								cart_created = sessionStorage.getItem('wc_cart_created');
							if (cart_hash === null || cart_hash === undefined || cart_hash === '') {
								cart_hash = '';
							}
							if (cookie_hash === null || cookie_hash === undefined || cookie_hash === '') {
								cookie_hash = '';
							}
							if (cart_hash && (cart_created === null || cart_created === undefined || cart_created === '')) {
								throw 'No cart_created';
							}
							$.each(data, function (key, value) {
								wc_fragments[key] = value;
							});
							localStorage.setItem(cart_hash_key, localStorage.getItem(cart_hash_key) + (new Date()).getTime());
							sessionStorage.setItem(cart_hash_key, sessionStorage.getItem(cart_hash_key) + (new Date()).getTime());
							sessionStorage.setItem(wc_cart_fragments_params.fragment_name, JSON.stringify(wc_fragments));
							if (wc_fragments && wc_fragments['div.widget_shopping_cart_content'] && cart_hash === cookie_hash) {
								$.each(wc_fragments, function (key, value) {
									$(key).replaceWith(value);
								});
								$(document.body).trigger('wc_fragments_loaded');
							} else {
								throw 'No fragment';
							}
						} catch (err) {
							$(document.body).trigger('wc_fragment_refresh');
						}
					}
				}
			}
		};
		sd.onActionProduct = function (id, name) {
			var data = {
					form: {},
					tinv_wishlist_id: id || '',
					tinv_wishlist_name: name || '',
					product_type: $(this).attr('data-tinv-wl-producttype'),
					product_id: $(this).attr('data-tinv-wl-product') || 0,
					product_variation: $(this).attr('data-tinv-wl-productvariation') || 0,
					product_action: $(this).attr('data-tinv-wl-action') || 'addto'
				},
				a = this;
			$(a).closest('form.cart[method=post], .tinvwl-loop-button-wrapper').find('input, select, textarea').each(function () {
				var name_elm = $(this).attr('name'),
					type_elm = $(this).attr('type'),
					value_elm = $(this).val(),
					count = 10,
					ti_merge_value = function (o1, o2) {
						if ('object' === typeof o2) {
							if ('undefined' === typeof o1) {
								o1 = {};
							}
							for (var i in o2) {
								if ('' === i) {
									var j = -1;
									for (j in o1) {
										j = j;
									}
									j = parseInt(j) + 1;
									o1[j] = ti_merge_value(o1[i], o2[i]);
								} else {
									o1[i] = ti_merge_value(o1[i], o2[i]);
								}
							}
							return o1;
						} else {
							return o2;
						}
					};
				if ('button' === type_elm || 'undefined' == typeof name_elm || name_elm.substr(0, 10) == "attribute_") {
					return;
				}
				while (/^(.+)\[([^\[\]]*?)\]$/.test(name_elm) && 0 < count) {
					var n_name = name_elm.match(/^(.+)\[([^\[\]]*?)\]$/);
					if (3 === n_name.length) {
						var _value_elm = {};
						_value_elm[n_name[2]] = value_elm;
						value_elm = _value_elm;
					}
					name_elm = n_name[1];
					count--;
				}
				if ('checkbox' === type_elm || 'radio' === type_elm) {
					if ($(this).is(':checked')) {
						if (!value_elm.length && 'object' !== typeof value_elm) {
							value_elm = true;
						}
						data.form[name_elm] = ti_merge_value(data.form[name_elm], value_elm);
					}
				} else {
					data.form[name_elm] = ti_merge_value(data.form[name_elm], value_elm);
				}
			});
			data = s.onPrepareDataAction.call(a, data) || data;
			$.post(s.api_url, data, function (body) {
				s.onDialogHide.call(a.tinvwl_dialog, a);
				if ('object' === typeof body) {
					for (var k in body) {
						if ('function' === typeof s.onAction[k]) {
							s.onAction[k].call(a, body[k]);
						}
					}
				} else {
					if ('function' === typeof s.onAction['msg']) {
						s.onAction['msg'].call(a, body);
					}
				}
			});
		};
		var s = $.extend(true, {}, sd, so);
		return $(this).each(function () {
			if (!$(this).attr('data-tinv-wl-list')) {
				return false;
			}
			if (s.dialogbox) {
				if (s.dialogbox.length) {
					this.tinvwl_dialog = s.dialogbox;
				}
			}
			if (!this.tinvwl_dialog) {
				this.tinvwl_dialog = s.onGetDialogBox.call(this);
			}
			if (!this.tinvwl_dialog) {
				var _tinvwl_dialog = $(this).nextAll(s.class.dialogbox).eq(0);
				if (_tinvwl_dialog.length) {
					this.tinvwl_dialog = _tinvwl_dialog;
				}
			}
			if (this.tinvwl_dialog) {
				s.onPrepareDialogBox.call(this.tinvwl_dialog);
				if ('function' !== typeof this.tinvwl_dialog.update_list) {
					this.tinvwl_dialog.update_list = function (WL) {
						var $select = $(this).find(s.class.select).eq(0);
						$(this).find(s.class.newtitle).hide().val('');
						$select.html('');
						$.each(WL, function (k, v) {
							s.onCreateWishList.call($select, v);
						});
						if (s.text_create) {
							s.onCreateWishList.call($select, {
								ID: '',
								title: s.text_create,
								in: false
							});
						}
						s.onMultiProductAlreadyIn.call($select, WL);
						s.onSelectWishList.call($select, WL);
						$(this).find(s.class.newtitle).toggle('' === $select.val());
					}
				}
				if ('function' !== typeof this.tinvwl_dialog.show_list) {
					this.tinvwl_dialog.show_list = function () {
						var WList = $.parseJSON($(this).attr('data-tinv-wl-list')) || [];
						if (WList.length) {
							WList = s.onPrepareList.call(WList) || WList;
							this.tinvwl_dialog.update_list(WList);
							s.onDialogShow.call(this.tinvwl_dialog, this);
						} else {
							s.onActionProduct.call(this);
						}
					}
				}
				var a = this;
				$(this.tinvwl_dialog).find(s.class.dialogbutton).off('click').on('click', function () {
					var b = $(a.tinvwl_dialog).find(s.class.select),
						c = $(a.tinvwl_dialog).find(s.class.newtitle),
						d;
					if (b.val() || c.val()) {
						s.onActionProduct.call(a, b.val(), c.val());
					} else {
						d = c.is(':visible') ? c : b;
						d.addClass('empty-name-wishlist');
						window.setTimeout(function () {
							d.removeClass('empty-name-wishlist');
						}, 1000);
					}
				});
			}
			$(this).off('click').on('click', s.onClick);
			s.onInited.call(this, s);
		});
	};

	$(document).ready(function () {

		// Add to wishlist button click
		$('body').on('click', '.tinvwl_add_to_wishlist_button', function (e) {
			if ($(this).is('.disabled-add-wishlist')) {
				e.preventDefault();
				window.alert(tinvwl_add_to_wishlist.i18n_make_a_selection_text);
				return;
			}
			if ($(this).is('.inited-add-wishlist')) {
				return;
			}
			$(this).tinvwl_to_wishlist({
				onInited: function (s) {
					$(this).addClass('inited-add-wishlist');
					s.onClick.call(this);
				}
			});
		});

		// Disable add to wishlist button if variations not selected
		$('.variations_form').each(function () {
			var c = $(this),
				e = c.find('.tinvwl_add_to_wishlist_button');
			if (e.length) {
				c.on('hide_variation', function (a) {
					a.preventDefault();
					e.addClass('disabled-add-wishlist');
				}).on('show_variation', function (a, b, d) {
					var f = JSON.parse(e.attr('data-tinv-wl-list')),
						j = false,
						g = '1' == window.tinvwl_add_to_wishlist['simple_flow'];
					for (var i in f) {
						if (f[i].hasOwnProperty('in') && Array.isArray(f[i]['in']) && -1 < (f[i]['in'] || []).indexOf(b.variation_id)) {
							j = true;
						}
					}
					e.toggleClass('tinvwl-product-in-list', j).toggleClass('tinvwl-product-make-remove', (j && g)).attr('data-tinv-wl-action', ((j && g) ? 'remove' : 'addto'));
					a.preventDefault();
					e.removeClass('disabled-add-wishlist');
				});
			}
		});

	});
})(jQuery);

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
			$(this).closest('table').eq(0).find('.product-cb input[type=checkbox], .wishlist-cb input[type=checkbox]').prop('checked', $(this).is(':checked'));
		});
	});
})(jQuery);

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
		$('.wishlist_products_counter').toggleClass('wishlist-counter-with-products', '0' != $('.wishlist_products_counter_number').html());
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
