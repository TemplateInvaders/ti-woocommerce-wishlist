"use strict";

function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
// Add to wishlist
(function ($) {
  // Optional client storage never grants access or prevents a live server update.
  $.tinvwlStorage = {
    get: function get(storage, key) {
      try {
        return 'string' === typeof key && key ? window[storage].getItem(key) : null;
      } catch (error) {
        return null;
      }
    },
    set: function set(storage, key, value) {
      try {
        if ('string' !== typeof key || !key) {
          return false;
        }
        window[storage].setItem(key, value);
        return true;
      } catch (error) {
        return false;
      }
    },
    remove: function remove(storage, key) {
      try {
        if ('string' !== typeof key || !key) {
          return false;
        }
        window[storage].removeItem(key);
        return true;
      } catch (error) {
        return false;
      }
    }
  };
  function parseWishlistJSON(value) {
    if ('string' !== typeof value) {
      return value;
    }
    try {
      return JSON.parse(value);
    } catch (error) {
      return null;
    }
  }
  function owns(object, key) {
    return Object.prototype.hasOwnProperty.call(object, key);
  }
  function wishlistData(value) {
    var data = parseWishlistJSON(value);
    if (!data || 'object' !== _typeof(data) || Array.isArray(data) || !owns(data, 'products') && !owns(data, 'counter')) {
      return null;
    }
    if (owns(data, 'products') && (!data.products || 'object' !== _typeof(data.products))) {
      return null;
    }
    return data;
  }

  // Statistics are requested in bounded batches for the buttons currently on this page.
  function wishlistStatsProductIds(knownStats) {
    var ids = [],
      seen = Object.create(null);
    $('a.tinvwl_add_to_wishlist_button').each(function () {
      var value = $(this).attr('data-tinv-wl-product'),
        id = Number(value);
      if (!/^\d+$/.test(value) || !Number.isSafeInteger(id) || id < 1 || seen[id] || knownStats && owns(knownStats, id)) {
        return;
      }
      seen[id] = true;
      if (ids.length < 200) {
        ids.push(id);
      }
    });
    return ids;
  }
  function wishlistActionScope(element) {
    var button = $(element),
      form = button.closest('form'),
      modalState = button.closest('.tinv-modal').data('tinvwlModalState');
    if (!form.length && modalState && modalState.opener) {
      form = $(modalState.opener).closest('form');
    }
    return {
      form: form,
      container: form.closest('div.tinv-wishlist.woocommerce.tinv-wishlist-clear'),
      sharekey: button.data('tinvwl_sharekey')
    };
  }
  function updateWishlistContainer(scope, response) {
    if (!response || true !== response.status) {
      return;
    }
    if (scope.form.length) {
      if ('string' !== typeof response.content || !response.content.trim()) {
        return;
      }
      scope.container.replaceWith(response.content);
    } else if (scope.sharekey && response.status) {
      // The mutation queue retains this target until every write has settled.
      $.fn.tinvwl_get_wishlist_data('refresh', scope.sharekey);
    }
  }
  function finishWishlistAction(element, container) {
    $(element).removeClass('inited-wishlist-action ftinvwl-pulse');
    (container || wishlistActionScope(element).container).unblock();
  }
  function showRequestError(xhr) {
    var message = xhr && xhr.responseJSON && xhr.responseJSON.msg;
    if ('string' !== typeof message || !message || /[<>]/.test(message)) {
      message = tinvwl_add_to_wishlist.i18n_ajax_error;
    }
    if ('string' === typeof message && message) {
      window.alert(message);
    }
  }

  // Resolve each mutation before sending the next: snapshots follow server commit order.
  var mutationQueue = [],
    mutationRunning = false,
    mutationDrainTimer;
  // Confirmed failures are retried within the same list, bulk action and selection intent.
  var cartRetryStates = Object.create(null),
    cartRetryIntents = Object.create(null),
    cartRetrySequence = 0;
  function bulkCartAction(action) {
    return 'add_to_cart_all' === action || 'add_to_cart_selected' === action;
  }
  function cartRetryForms(key) {
    return $('div.tinv-wishlist.woocommerce.tinv-wishlist-clear form[data-tinvwl_sharekey]').filter(function () {
      return $(this).attr('data-tinvwl_sharekey') === key;
    });
  }
  function cartRetryContext(data) {
    if (data instanceof window.FormData || !bulkCartAction(data['tinvwl-action']) || !data['tinvwl-sharekey']) {
      return null;
    }
    var key = String(data['tinvwl-sharekey']),
      quantities = Object.create(null),
      revisions = Object.create(null),
      drafts = Object.create(null);
    cartRetryForms(key).find('input').each(function () {
      var match = /^wishlist_qty\[([1-9][0-9]*)\]$/.exec(this.name);
      if (match && 'hidden' !== this.type) {
        quantities[match[1]] = this.value;
        revisions[match[1]] = $(this).data('tinvwlDraftRevision') || 0;
        drafts[match[1]] = !!$(this).data('tinvwlDraftEdited') || this.value !== this.defaultValue;
      }
    });
    return {
      key: JSON.stringify([key, data['tinvwl-action']]),
      sharekey: key,
      intent: cartRetryIntents[key] || 0,
      sequence: ++cartRetrySequence,
      quantities: quantities,
      revisions: revisions,
      drafts: drafts
    };
  }
  function hasCartRetry(form, action) {
    var state = cartRetryStates[JSON.stringify([form.attr('data-tinvwl_sharekey'), action])];
    return bulkCartAction(action) && state && state.ids && state.ids.length > 0;
  }
  function prepareCartRetry(data, context) {
    if (!context) {
      return 'send';
    }
    if (context.intent !== (cartRetryIntents[context.sharekey] || 0)) {
      return 'cancelled';
    }
    var state = cartRetryStates[context.key];
    if (!state) {
      return 'send';
    }
    if (state.uncertainThrough && context.sequence <= state.uncertainThrough) {
      return 'uncertain';
    }
    if (state.completedThrough) {
      if (context.sequence <= state.completedThrough) {
        return 'cancelled';
      }
      delete cartRetryStates[context.key];
      return 'send'; // A later click after full success is a new explicit cart action.
    }
    if (!state.ids || !state.ids.length) {
      delete cartRetryStates[context.key];
      return 'send';
    }
    data['tinvwl-products'] = state.ids.slice();
    data['tinvwl-qty'] = {};
    state.ids.forEach(function (id) {
      var input = state.inputs[id],
        edited = input ? input.value !== context.quantities[id] || input.revision !== context.revisions[id] : context.drafts[id];
      // Add all did not submit a pre-existing draft. Preserve its saved requested amount until a new edit.
      if (owns(context.quantities, id) && (edited || !owns(state.quantities, id))) {
        data['tinvwl-qty'][id] = context.quantities[id];
      } else if (owns(state.quantities, id)) {
        data['tinvwl-qty'][id] = state.quantities[id];
      }
    });
    if ('add_to_cart_all' === data['tinvwl-action']) {
      data['tinvwl-retry'] = true;
    }
    return 'send';
  }
  function rememberCartRetry(data, response, context) {
    if (!context || context.intent !== (cartRetryIntents[context.sharekey] || 0) || !response || !response.cart_results) {
      return;
    }
    var results = response.cart_results,
      ids = [],
      quantities = Object.create(null),
      inputs = Object.create(null),
      valid = false;
    Object.keys(results).forEach(function (id) {
      var result = results[id];
      if (!/^[1-9][0-9]*$/.test(id) || !result || 'added' !== result.status && 'failed' !== result.status) {
        return;
      }
      valid = true;
      if ('failed' !== result.status) {
        return;
      }
      ids.push(id);
      if (owns(context.quantities, id)) {
        inputs[id] = {
          value: context.quantities[id],
          revision: context.revisions[id]
        };
      }
      if (isFinite(Number(result.requested_quantity)) && Number(result.requested_quantity) >= 0) {
        quantities[id] = result.requested_quantity;
      } else if (data['tinvwl-qty'] && owns(data['tinvwl-qty'], id)) {
        quantities[id] = data['tinvwl-qty'][id];
      }
    });
    if (!valid) {
      return;
    }
    cartRetryStates[context.key] = {
      sharekey: context.sharekey,
      ids: ids,
      quantities: quantities,
      inputs: inputs,
      completedThrough: ids.length ? 0 : cartRetrySequence
    };
  }
  function rememberUncertainCart(context) {
    if (!context || context.intent !== (cartRetryIntents[context.sharekey] || 0)) {
      return;
    }
    var state = cartRetryStates[context.key] || {
      sharekey: context.sharekey
    };
    state.uncertainThrough = cartRetrySequence;
    cartRetryStates[context.key] = state;
  }
  $(document).on('change', 'form[data-tinvwl_sharekey] input[name="wishlist_pr[]"], form[data-tinvwl_sharekey] .global-cb', function () {
    var key = $(this).closest('form').attr('data-tinvwl_sharekey');
    cartRetryIntents[key] = (cartRetryIntents[key] || 0) + 1;
    Object.keys(cartRetryStates).forEach(function (stateKey) {
      if (cartRetryStates[stateKey].sharekey === key) {
        delete cartRetryStates[stateKey];
      }
    });
  }).on('reset', 'form[data-tinvwl_sharekey]', function () {
    var key = $(this).attr('data-tinvwl_sharekey');
    cartRetryIntents[key] = (cartRetryIntents[key] || 0) + 1;
    Object.keys(cartRetryStates).forEach(function (stateKey) {
      if (cartRetryStates[stateKey].sharekey === key) {
        delete cartRetryStates[stateKey];
      }
    });
  });
  var pendingWishlistViews = Object.create(null);
  function rememberWishlistViews(target) {
    if (!(target && target.jquery)) {
      if ('undefined' !== typeof target) {
        pendingWishlistViews[String(target)] = true;
        return;
      }
      target = $('div.tinv-wishlist.woocommerce.tinv-wishlist-clear form[data-tinvwl_sharekey]');
    }
    target.each(function () {
      var key = $(this).attr('data-tinvwl_sharekey');
      if ('undefined' !== typeof key) {
        pendingWishlistViews[key] = true;
      }
    });
  }
  function wishlistAjax(options) {
    var data = options.data,
      mutation = data instanceof window.FormData || data && data['tinvwl-action'] && 'get_data' !== data['tinvwl-action'];
    if (!mutation) {
      return sendWishlistRequest(options);
    }
    var result = $.Deferred(),
      cartContext = cartRetryContext(data);
    rememberWishlistViews();
    wishlistRevision++;
    latestRead++;
    mutationQueue.push(function () {
      var retry = prepareCartRetry(data, cartContext);
      if ('send' !== retry) {
        if ('uncertain' === retry) {
          result.reject();
        } else {
          result.resolve({
            status: false,
            error: 'cart_intent_changed'
          });
        }
        mutationRunning = false;
        drainMutations();
        return;
      }
      sendWishlistRequest(options).done(function (response) {
        rememberCartRetry(data, response, cartContext);
        result.resolveWith(this, arguments);
      }).fail(function () {
        rememberUncertainCart(cartContext);
        result.rejectWith(this, arguments);
      }).always(function () {
        mutationRunning = false;
        drainMutations();
      });
    });
    drainMutations();
    return result.promise();
  }
  // Only a nonce rejection produced before execution is safe to replay once.
  function sendWishlistRequest(options) {
    if (!options.data || options.data instanceof window.FormData || !owns(options.data, 'tinvwl-security')) {
      return $.ajax(options);
    }
    var result = $.Deferred();
    function refresh(retried) {
      freshRequestNonce().done(function (nonces) {
        if (!nonces || 'string' !== typeof nonces.table_nonce || !nonces.table_nonce) {
          result.reject();
          return;
        }
        tinvwl_add_to_wishlist.nonce = nonces.table_nonce;
        send(retried);
      }).fail(function () {
        result.rejectWith(this, arguments);
      });
    }
    function send(retried) {
      options.data['tinvwl-security'] = tinvwl_add_to_wishlist.nonce;
      $.ajax(options).done(function (response) {
        if (!retried && response && false === response.status && 'invalid_nonce' === response.error) {
          refresh(true);
          return;
        }
        result.resolveWith(this, arguments);
      }).fail(function (xhr) {
        if (!retried && xhr && 403 === xhr.status && xhr.responseJSON && 'invalid_nonce' === xhr.responseJSON.error) {
          refresh(true);
          return;
        }
        result.rejectWith(this, arguments);
      });
    }
    if (tinvwl_add_to_wishlist.nonce) {
      send(false);
    } else {
      refresh(false);
    }
    return result.promise();
  }
  function drainMutations() {
    if (mutationRunning) {
      return;
    }
    if (mutationQueue.length) {
      mutationRunning = true;
      mutationQueue.shift()();
      return;
    }
    window.clearTimeout(mutationDrainTimer);
    mutationDrainTimer = window.setTimeout(function () {
      // Includes changes from other tabs and uncertain transport outcomes; never replay a write.
      if (!mutationRunning && !mutationQueue.length) {
        $.fn.tinvwl_get_wishlist_data('synchronize');
      }
    }, 0);
  }
  var nonceRequest;
  function freshRequestNonce() {
    var request = nonceRequest;
    if (!nonceRequest) {
      request = $.tinvwlGuestNonceRequest({
        url: tinvwl_add_to_wishlist.nonce_url || tinvwl_add_to_wishlist.wc_ajax_url.replace(/([?&])wc-ajax=[^&#]*/, '$1wc-ajax=tinvwl_request_nonce'),
        method: 'POST',
        dataType: 'json',
        cache: false
      });
      nonceRequest = request;
      request.done(function (nonces) {
        if (nonces && 'string' === typeof nonces.table_nonce && nonces.table_nonce) {
          tinvwl_add_to_wishlist.nonce = nonces.table_nonce;
        }
      });
      request.always(function () {
        if (nonceRequest === request) {
          nonceRequest = null;
        }
      });
    }
    return request;
  }
  function wishlistProductForm(button, productId) {
    var formSelector = 'form.cart[method=post], form.vtajaxform[method=post]',
      formEl = $(button).closest(formSelector);

    // Prefer the owning form; outside buttons require an unambiguous product identity.
    if (!formEl.length) {
      var matchingForms = $(formSelector).filter(function () {
          var formProductId = $(this).attr('data-product_id');
          if ('undefined' !== typeof formProductId) {
            return formProductId === String(productId);
          }
          return $(this).find('[name="add-to-cart"], [name="product_id"]').filter(function () {
            return $(this).val() === String(productId);
          }).length > 0;
        }),
        product = $(button).closest('.product'),
        localForms = matchingForms.filter(function () {
          return product.length && $.contains(product[0], this);
        });
      if (localForms.length) {
        matchingForms = localForms;
      }
      if (1 === matchingForms.length) {
        formEl = matchingForms;
      }
    }
    return formEl;
  }
  function updateProductActionLabel(element) {
    var button = $(element),
      remove = 'remove' === button.attr('data-tinv-wl-action'),
      label = $.trim(button.find(remove ? '.tinvwl_remove_from_wishlist-text' : '.tinvwl_add_to_wishlist-text').first().text()) || button.attr(remove ? 'data-tinv-wl-remove-label' : 'data-tinv-wl-add-label');
    if (label) {
      button.attr('aria-label', label);
    }
  }
  function variationFormButtons(form) {
    var productId = $(form).data('product_id');
    return $('.tinvwl_add_to_wishlist_button:not(.tinvwl-loop)[data-tinv-wl-product="' + productId + '"]').filter(function () {
      return wishlistProductForm(this, productId)[0] === form;
    });
  }

  //Add to wishlist main function.
  $.fn.tinvwl_to_wishlist = function (so) {
    var sd = {
      api_url: window.location.href.split('?')[0],
      text_create: window.tinvwl_add_to_wishlist['text_create'],
      text_already_in: window.tinvwl_add_to_wishlist['text_already_in'],
      class: {
        dialogbox: '.tinvwl_add_to_select_wishlist',
        select: '.tinvwl_wishlist',
        newtitle: '.tinvwl_new_input',
        dialogbutton: '.tinvwl_button_add'
      },
      redirectTimer: null,
      onPrepareList: function onPrepareList() {},
      onGetDialogBox: function onGetDialogBox() {},
      onPrepareDialogBox: function onPrepareDialogBox() {
        if (!$('body > .tinv-wishlist').length) {
          $('body').append($('<div>').addClass('tinv-wishlist'));
        }
        $(this).appendTo('body > .tinv-wishlist');
      },
      onCreateWishList: function onCreateWishList(wishlist) {
        $(this).append($('<option>').html(wishlist.title).val(wishlist.ID).toggleClass('tinv_in_wishlist', wishlist.in));
      },
      onSelectWishList: function onSelectWishList() {},
      onDialogShow: function onDialogShow(modal) {
        $(modal).addClass('tinv-modal-open');
        $(modal).removeClass('ftinvwl-pulse');
      },
      onDialogHide: function onDialogHide(element) {
        $(this).removeClass('tinv-modal-open');
        $(element).removeClass('ftinvwl-pulse');
      },
      onInited: function onInited() {},
      onClick: function onClick() {
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
      },
      onPrepareDataAction: function onPrepareDataAction(a, data) {
        $('body').trigger('tinvwl_wishlist_button_clicked', [a, data]);
      },
      filterProductAlreadyIn: function filterProductAlreadyIn(WList) {
        var WList = WList || [],
          data = {};
        $('form.cart[method=post], .woocommerce-variation-add-to-cart, form.vtajaxform[method=post]').find('input, select').each(function () {
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
          if ('object' === _typeof(wishlist.in) && 'string' === typeof data) {
            var number = parseInt(data);
            return 0 <= wishlist.in.indexOf(number);
          }
          return wishlist.in;
        });
      },
      onMultiProductAlreadyIn: function onMultiProductAlreadyIn(WList) {
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
        redirect: function redirect(url) {
          if (s.redirectTimer) {
            clearTimeout(s.redirectTimer);
          }
          s.redirectTimer = window.setTimeout(function () {
            window.location.href = url;
          }, 4000);
        },
        force_redirect: function force_redirect(url) {
          window.location.href = url;
        },
        wishlists: function wishlists(wishlist) {},
        msg: function msg(html) {
          if (!html) {
            return false;
          }
          var $msg = $(html).eq(0);
          if (!$('body > .tinv-wishlist').length) {
            $('body').append($('<div>').addClass('tinv-wishlist'));
          }
          $('body > .tinv-wishlist').append($msg);
          FocusTrap('body > .tinv-wishlist', this);
          if (!s.redirectTimer) {
            s.removeTimer = window.setTimeout(function () {
              $msg.remove();
              if (s.redirectTimer) {
                clearTimeout(s.redirectTimer);
              }
            }, tinvwl_add_to_wishlist.popup_timer);
          }
          $msg.on('click', '.tinv-close-modal, .tinvwl_button_close, .tinv-overlay', function (e) {
            e.preventDefault();
            removeModalResult($msg);
            if (s.redirectTimer) {
              clearTimeout(s.redirectTimer);
            }
            if (s.removeTimer) {
              clearTimeout(s.removeTimer);
            }
          });
        },
        status: function status(_status) {
          $('body').trigger('tinvwl_wishlist_added_status', [this, _status]);
        },
        removed: function removed(status) {},
        make_remove: function make_remove(status) {},
        wishlists_data: function wishlists_data(value) {
          set_hash(JSON.stringify(value));
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
          product_action: $(this).attr('data-tinv-wl-action') || 'addto',
          redirect: window.location.href
        },
        a = this,
        formEl = wishlistProductForm(a, data.product_id),
        formFields,
        hiddenFields = [],
        formData = new FormData();
      if (tinvwl_add_to_wishlist.wpml) {
        data.lang = tinvwl_add_to_wishlist.wpml;
      }
      if (tinvwl_add_to_wishlist.wpml_default) {
        data.lang_default = tinvwl_add_to_wishlist.wpml_default;
      }
      if (tinvwl_add_to_wishlist.stats) {
        data.stats = tinvwl_add_to_wishlist.stats;
        data.stats_products = JSON.stringify(wishlistStatsProductIds());
      }
      formFields = formEl.find('input:not(:disabled), select:not(:disabled), textarea:not(:disabled)');
      formFields = formFields.add($(a).closest('.tinv-wraper').filter(function () {
        return $(this).attr('data-tinvwl_product_id') === String(data.product_id);
      }).find('input:not(:disabled), select:not(:disabled), textarea:not(:disabled)').filter(function () {
        var owner = $(this).closest('form');
        return !owner.length || formEl.length && owner[0] === formEl[0];
      }));
      formFields.each(function () {
        var name_elm = $(this).attr('name'),
          file_name_elm = name_elm,
          type_elm = $(this).attr('type'),
          value_elm = $(this).val(),
          multiple_elm = $(this).is('select[multiple]'),
          count = 10,
          _ti_merge_value = function ti_merge_value(o1, o2) {
            if ('object' === _typeof(o2)) {
              if ('undefined' === typeof o1) {
                o1 = {};
              }
              for (var i in o2) {
                if ('' === i) {
                  var values = multiple_elm && Array.isArray(o2[i]) ? o2[i] : [o2[i]];
                  for (var value_index = 0; value_index < values.length; value_index++) {
                    var j = -1;
                    for (j in o1) {
                      j = j;
                    }
                    j = parseInt(j) + 1;
                    o1[j] = _ti_merge_value(o1[i], values[value_index]);
                  }
                } else {
                  o1[i] = _ti_merge_value(o1[i], o2[i]);
                }
              }
              return o1;
            } else {
              return o2;
            }
          };
        if ('button' === type_elm || 'undefined' == typeof name_elm || multiple_elm && (!value_elm || !value_elm.length)) {
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
        if ('file' === type_elm) {
          var file_data = $(this)[0].files;
          if (file_data) {
            for (var file_index = 0; file_index < file_data.length; file_index++) {
              formData.append(file_name_elm, file_data[file_index]);
            }
          }
        }
        if ('checkbox' === type_elm || 'radio' === type_elm) {
          if ($(this).is(':checked')) {
            if (!value_elm.length && 'object' !== _typeof(value_elm)) {
              value_elm = true;
            }
            data.form[name_elm] = _ti_merge_value(data.form[name_elm], value_elm);
          }
        } else {
          data.form[name_elm] = _ti_merge_value(data.form[name_elm], value_elm);
        }
        if ('hidden' === type_elm) {
          hiddenFields.push(name_elm);
        }
      });
      data.form['tinvwl-hidden-fields'] = hiddenFields;
      data = s.onPrepareDataAction.call(a, a, data) || data;
      $.each(data, function (key, value) {
        if ('form' === key) {
          $.each(value, function (k, v) {
            if ('object' === _typeof(v)) {
              v = JSON.stringify(v);
            }
            formData.append(key + '[' + k + ']', v);
          });
        } else {
          formData.append(key, value);
        }
      });
      var mutationKey = JSON.stringify(data),
        pending = a.tinvwlPendingMutations || (a.tinvwlPendingMutations = Object.create(null));
      if (pending[mutationKey]) {
        return;
      }
      pending[mutationKey] = true;
      function finishProductRequest() {
        delete pending[mutationKey];
      }
      function receiveResponse(body) {
        finishProductRequest();
        $('body').trigger('tinvwl_wishlist_ajax_response', [this, body]);
        s.onDialogHide.call(a.tinvwl_dialog, a);
        if ('object' === _typeof(body)) {
          for (var k in body) {
            if ('function' === typeof s.onAction[k]) {
              s.onAction[k].call(a, body[k]);
            }
          }
        } else {
          if ('function' === typeof s.onAction.msg) {
            s.onAction.msg.call(a, body);
          }
        }
      }
      function requestFailed(xhr) {
        finishProductRequest();
        var body = $.extend({
          error: 'request_failed'
        }, xhr && xhr.responseJSON, {
          status: false
        });
        $('body').trigger('tinvwl_wishlist_ajax_response', [this, body]);
        s.onDialogHide.call(a.tinvwl_dialog, a);
        if ('function' === typeof s.onAction.status) {
          s.onAction.status.call(a, false);
        }
        showRequestError(xhr);
      }
      function sendRequest(retried) {
        freshRequestNonce().done(function (nonces) {
          if (!nonces || 'string' !== typeof nonces.add_nonce || !nonces.add_nonce) {
            requestFailed();
            return;
          }
          // Reuse prepared fields/files; FormData.set is absent in IE11.
          if ('function' === typeof formData.set) {
            formData.set('tinvwl_add_nonce', nonces.add_nonce);
          } else {
            formData.append('tinvwl_add_nonce', nonces.add_nonce);
          }
          wishlistAjax({
            url: s.api_url,
            method: 'POST',
            contentType: false,
            processData: false,
            data: formData
          }).done(receiveResponse).fail(function (xhr) {
            if (!retried && 403 === xhr.status && xhr.responseJSON && 'invalid_nonce' === xhr.responseJSON.error) {
              sendRequest(true);
            } else {
              requestFailed.call(this, xhr);
            }
          });
        }).fail(requestFailed);
      }
      sendRequest(false);
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
          };
        }
        if ('function' !== typeof this.tinvwl_dialog.show_list) {
          this.tinvwl_dialog.show_list = function () {
            var WList = parseWishlistJSON($(this).attr('data-tinv-wl-list'));
            WList = Array.isArray(WList) ? WList : [];
            if (WList.length) {
              WList = s.onPrepareList.call(WList) || WList;
              this.tinvwl_dialog.update_list(WList);
              s.onDialogShow.call(this.tinvwl_dialog, this);
            } else {
              s.onActionProduct.call(this);
            }
          };
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
    $('body').on('click keydown', '.tinvwl_add_to_wishlist_button', function (e) {
      if ('keydown' === e.type) {
        var keyD = e.key !== undefined ? e.key : e.keyCode;

        // e.key && e.keycode have mixed support - keycode is deprecated but support is greater than e.key
        // I tested within IE11, Firefox, Chrome, Edge (latest) & all had good support for e.key

        if (!('Enter' === keyD || 13 === keyD || 0 <= ['Spacebar', ' '].indexOf(keyD) || 32 === keyD)) {
          return;
        }
        e.preventDefault();
        // Use the same click path before and after initialization, without native or held-key repeats.
        if (!e.originalEvent || !e.originalEvent.repeat) {
          $(this).trigger('click');
        }
        return;
      }
      $('body').trigger('tinvwl_add_to_wishlist_button_click', [this]);
      if ($(this).is('.disabled-add-wishlist')) {
        e.preventDefault();
        window.alert(tinvwl_add_to_wishlist.i18n_make_a_selection_text);
        return;
      }
      if ($(this).is('.inited-add-wishlist')) {
        return;
      }
      $(this).tinvwl_to_wishlist({
        onInited: function onInited(s) {
          $(this).addClass('inited-add-wishlist');
          s.onClick.call(this);
        }
      });
    });

    //Remove button ajax
    $('body').on('click keydown', 'button[name="tinvwl-remove"]', function (e) {
      if ('keydown' === e.type) {
        var keyD = e.key !== undefined ? e.key : e.keyCode;

        // e.key && e.keycode have mixed support - keycode is deprecated but support is greater than e.key
        // I tested within IE11, Firefox, Chrome, Edge (latest) & all had good support for e.key

        if (!('Enter' === keyD || 13 === keyD || 0 <= ['Spacebar', ' '].indexOf(keyD) || 32 === keyD)) {
          return;
        }
      }
      e.preventDefault();
      var el = $(this),
        actionScope = wishlistActionScope(this);
      if (el.is('.inited-wishlist-action')) {
        return;
      }
      el.addClass('inited-wishlist-action');
      actionScope.container.block({
        message: null,
        overlayCSS: {
          background: '#fff',
          opacity: 0.6
        }
      });
      var data = {
        'tinvwl-product_id': el.val(),
        'tinvwl-action': 'remove',
        'tinvwl-security': tinvwl_add_to_wishlist.nonce,
        'tinvwl-paged': el.data('tinvwl_paged') || actionScope.form.data('tinvwl_paged'),
        'tinvwl-per-page': el.data('tinvwl_per_page') || actionScope.form.data('tinvwl_per_page'),
        'tinvwl-sharekey': el.data('tinvwl_sharekey') || actionScope.form.data('tinvwl_sharekey')
      };
      if (tinvwl_add_to_wishlist.wpml) {
        data.lang = tinvwl_add_to_wishlist.wpml;
      }
      if (tinvwl_add_to_wishlist.wpml_default) {
        data.lang_default = tinvwl_add_to_wishlist.wpml_default;
      }
      if (tinvwl_add_to_wishlist.stats) {
        data.stats = tinvwl_add_to_wishlist.stats;
        data.stats_products = wishlistStatsProductIds();
      }
      wishlistAjax({
        url: tinvwl_add_to_wishlist.wc_ajax_url,
        method: 'POST',
        cache: false,
        data: data,
        beforeSend: function beforeSend(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', tinvwl_add_to_wishlist.nonce);
        }
      }).always(function () {
        finishWishlistAction(el);
      }).fail(showRequestError).done(function (response) {
        if (response.msg) {
          var $msg = $(response.msg).eq(0);
          if (!$('body > .tinv-wishlist').length) {
            $('body').append($('<div>').addClass('tinv-wishlist'));
          }
          $('body > .tinv-wishlist').append($msg);
          FocusTrap('body > .tinv-wishlist', el);
          $msg.on('click', '.tinv-close-modal, .tinvwl_button_close, .tinv-overlay', function (e) {
            e.preventDefault();
            removeModalResult($msg);
          });
          var closeTimer;
          if (!closeTimer) {
            closeTimer = window.setTimeout(function () {
              $msg.remove();
              if (closeTimer) {
                clearTimeout(closeTimer);
              }
            }, tinvwl_add_to_wishlist.popup_timer);
          }
        }
        if (response.status) {
          updateWishlistContainer(actionScope, response);
          $('.tinvwl-break-input').tinvwl_break_submit({
            selector: '.tinvwl-break-input-filed'
          });
          $('.tinvwl-break-checkbox').tinvwl_break_submit({
            selector: 'table td input[type=checkbox]',
            validate: function validate() {
              return $(this).is(':checked');
            }
          });
          jQuery.fn.tinvwl_get_wishlist_data();
        }
        if (response.wishlists_data) {
          set_hash(JSON.stringify(response.wishlists_data));
        }
        $('body').trigger('tinvwl_wishlist_ajax_response', [this, response]);
      });
    });

    //Add to cart button ajax
    $('body').on('click keydown', 'button[name="tinvwl-add-to-cart"]', function (e) {
      if ('keydown' === e.type) {
        var keyD = e.key !== undefined ? e.key : e.keyCode;

        // e.key && e.keycode have mixed support - keycode is deprecated but support is greater than e.key
        // I tested within IE11, Firefox, Chrome, Edge (latest) & all had good support for e.key

        if (!('Enter' === keyD || 13 === keyD || 0 <= ['Spacebar', ' '].indexOf(keyD) || 32 === keyD)) {
          return;
        }
      }
      e.preventDefault();
      var el = $(this),
        actionScope = wishlistActionScope(this);
      if (el.is('.inited-wishlist-action')) {
        return;
      }
      el.addClass('inited-wishlist-action');
      actionScope.container.block({
        message: null,
        overlayCSS: {
          background: '#fff',
          opacity: 0.6
        }
      });
      var data = {
        'tinvwl-product_id': el.val(),
        'tinvwl-action': 'add_to_cart_single',
        'tinvwl-security': tinvwl_add_to_wishlist.nonce,
        'tinvwl-paged': el.data('tinvwl_paged') || actionScope.form.data('tinvwl_paged'),
        'tinvwl-per-page': el.data('tinvwl_per_page') || actionScope.form.data('tinvwl_per_page'),
        'tinvwl-sharekey': el.data('tinvwl_sharekey') || actionScope.form.data('tinvwl_sharekey')
      };
      if (tinvwl_add_to_wishlist.wpml) {
        data.lang = tinvwl_add_to_wishlist.wpml;
      }
      if (tinvwl_add_to_wishlist.wpml_default) {
        data.lang_default = tinvwl_add_to_wishlist.wpml_default;
      }
      if (tinvwl_add_to_wishlist.stats) {
        data.stats = tinvwl_add_to_wishlist.stats;
        data.stats_products = wishlistStatsProductIds();
      }
      wishlistAjax({
        url: tinvwl_add_to_wishlist.wc_ajax_url,
        method: 'POST',
        cache: false,
        data: data,
        beforeSend: function beforeSend(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', tinvwl_add_to_wishlist.nonce);
        }
      }).always(function () {
        finishWishlistAction(el);
      }).fail(showRequestError).done(function (response) {
        if (response.msg) {
          var $msg = $(response.msg).eq(0);
          if (!$('body > .tinv-wishlist').length) {
            $('body').append($('<div>').addClass('tinv-wishlist'));
          }
          $('body > .tinv-wishlist').append($msg);
          FocusTrap('body > .tinv-wishlist', el);
          $msg.on('click', '.tinv-close-modal, .tinvwl_button_close, .tinv-overlay', function (e) {
            e.preventDefault();
            removeModalResult($msg);
          });
          var closeTimer;
          if (!closeTimer) {
            closeTimer = window.setTimeout(function () {
              $msg.remove();
              if (closeTimer) {
                clearTimeout(closeTimer);
              }
            }, tinvwl_add_to_wishlist.popup_timer);
          }
        }
        $(document.body).trigger('wc_fragment_refresh');
        updateWishlistContainer(actionScope, response);
        jQuery.fn.tinvwl_get_wishlist_data();
        if (response.wishlists_data) {
          set_hash(JSON.stringify(response.wishlists_data));
        }
        $('body').trigger('tinvwl_wishlist_ajax_response', [this, response]);
        if (response.redirect) {
          window.location.href = response.redirect;
        }
      });
    });

    //Add all to cart button ajax
    $('body').on('click keydown', 'button[name="tinvwl-action-product_all"]', function (e) {
      if ('keydown' === e.type) {
        var keyD = e.key !== undefined ? e.key : e.keyCode;

        // e.key && e.keycode have mixed support - keycode is deprecated but support is greater than e.key
        // I tested within IE11, Firefox, Chrome, Edge (latest) & all had good support for e.key

        if (!('Enter' === keyD || 13 === keyD || 0 <= ['Spacebar', ' '].indexOf(keyD) || 32 === keyD)) {
          return;
        }
      }
      e.preventDefault();
      var el = $(this),
        actionScope = wishlistActionScope(this);
      if (el.is('.inited-wishlist-action')) {
        return;
      }
      el.addClass('inited-wishlist-action');
      actionScope.container.block({
        message: null,
        overlayCSS: {
          background: '#fff',
          opacity: 0.6
        }
      });
      var data = {
        'tinvwl-action': 'add_to_cart_all',
        'tinvwl-security': tinvwl_add_to_wishlist.nonce,
        'tinvwl-paged': actionScope.form.data('tinvwl_paged'),
        'tinvwl-per-page': actionScope.form.data('tinvwl_per_page'),
        'tinvwl-sharekey': actionScope.form.data('tinvwl_sharekey')
      };
      if (tinvwl_add_to_wishlist.wpml) {
        data.lang = tinvwl_add_to_wishlist.wpml;
      }
      if (tinvwl_add_to_wishlist.wpml_default) {
        data.lang_default = tinvwl_add_to_wishlist.wpml_default;
      }
      if (tinvwl_add_to_wishlist.stats) {
        data.stats = tinvwl_add_to_wishlist.stats;
        data.stats_products = wishlistStatsProductIds();
      }
      wishlistAjax({
        url: tinvwl_add_to_wishlist.wc_ajax_url,
        method: 'POST',
        cache: false,
        data: data,
        beforeSend: function beforeSend(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', tinvwl_add_to_wishlist.nonce);
        }
      }).always(function () {
        finishWishlistAction(el);
      }).fail(showRequestError).done(function (response) {
        if (response.msg) {
          var $msg = $(response.msg).eq(0);
          if (!$('body > .tinv-wishlist').length) {
            $('body').append($('<div>').addClass('tinv-wishlist'));
          }
          $('body > .tinv-wishlist').append($msg);
          FocusTrap('body > .tinv-wishlist', el);
          $msg.on('click', '.tinv-close-modal, .tinvwl_button_close, .tinv-overlay', function (e) {
            e.preventDefault();
            removeModalResult($msg);
          });
          var closeTimer;
          if (!closeTimer) {
            closeTimer = window.setTimeout(function () {
              $msg.remove();
              if (closeTimer) {
                clearTimeout(closeTimer);
              }
            }, tinvwl_add_to_wishlist.popup_timer);
          }
        }
        $(document.body).trigger('wc_fragment_refresh');
        updateWishlistContainer(actionScope, response);
        jQuery.fn.tinvwl_get_wishlist_data();
        if (response.wishlists_data) {
          set_hash(JSON.stringify(response.wishlists_data));
        }
        $('body').trigger('tinvwl_wishlist_ajax_response', [this, response]);
        if (true === response.status && response.redirect) {
          window.location.href = response.redirect;
        }
      });
    });

    //Bulk action button ajax
    $('body').on('click keydown', 'button[name="tinvwl-action-product_apply"], button[name="tinvwl-action-product_selected"]', function (e) {
      if ('keydown' === e.type) {
        var keyD = e.key !== undefined ? e.key : e.keyCode;

        // e.key && e.keycode have mixed support - keycode is deprecated but support is greater than e.key
        // I tested within IE11, Firefox, Chrome, Edge (latest) & all had good support for e.key

        if (!('Enter' === keyD || 13 === keyD || 0 <= ['Spacebar', ' '].indexOf(keyD) || 32 === keyD)) {
          return;
        }
      }
      e.preventDefault();
      var products = [];
      wishlistActionScope(this).form.find('input[name="wishlist_pr[]"]:checked').each(function () {
        products.push(this.value);
      });
      var el = $(this),
        actionScope = wishlistActionScope(this);
      var retryAction = 'tinvwl-action-product_selected' === el.attr('name') ? 'add_to_cart_selected' : actionScope.form.find('select#tinvwl_product_actions option').filter(':selected').val();
      if (!products.length && !hasCartRetry(actionScope.form, retryAction) || !retryAction) {
        alert(window.tinvwl_add_to_wishlist['tinvwl_break_submit']);
        return;
      }
      if (el.is('.inited-wishlist-action')) {
        return;
      }
      el.addClass('inited-wishlist-action');
      actionScope.container.block({
        message: null,
        overlayCSS: {
          background: '#fff',
          opacity: 0.6
        }
      });
      var action = '';
      if ('tinvwl-action-product_selected' === el.attr('name')) {
        action = 'add_to_cart_selected';
      } else {
        action = wishlistActionScope(this).form.find('select#tinvwl_product_actions option').filter(':selected').val();
      }
      var data = {
        'tinvwl-products': products,
        'tinvwl-action': action,
        'tinvwl-security': tinvwl_add_to_wishlist.nonce,
        'tinvwl-paged': actionScope.form.data('tinvwl_paged'),
        'tinvwl-per-page': actionScope.form.data('tinvwl_per_page'),
        'tinvwl-sharekey': actionScope.form.data('tinvwl_sharekey')
      };
      if (tinvwl_add_to_wishlist.wpml) {
        data.lang = tinvwl_add_to_wishlist.wpml;
      }
      if (tinvwl_add_to_wishlist.wpml_default) {
        data.lang_default = tinvwl_add_to_wishlist.wpml_default;
      }
      if (tinvwl_add_to_wishlist.stats) {
        data.stats = tinvwl_add_to_wishlist.stats;
        data.stats_products = wishlistStatsProductIds();
      }
      wishlistAjax({
        url: tinvwl_add_to_wishlist.wc_ajax_url,
        method: 'POST',
        cache: false,
        data: data,
        beforeSend: function beforeSend(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', tinvwl_add_to_wishlist.nonce);
        }
      }).always(function () {
        finishWishlistAction(el);
      }).fail(showRequestError).done(function (response) {
        if (response.msg) {
          var $msg = $(response.msg).eq(0);
          if (!$('body > .tinv-wishlist').length) {
            $('body').append($('<div>').addClass('tinv-wishlist'));
          }
          $('body > .tinv-wishlist').append($msg);
          FocusTrap('body > .tinv-wishlist', el);
          $msg.on('click', '.tinv-close-modal, .tinvwl_button_close, .tinv-overlay', function (e) {
            e.preventDefault();
            removeModalResult($msg);
          });
          var closeTimer;
          if (!closeTimer) {
            closeTimer = window.setTimeout(function () {
              $msg.remove();
              if (closeTimer) {
                clearTimeout(closeTimer);
              }
            }, tinvwl_add_to_wishlist.popup_timer);
          }
        }
        if ('add_to_cart_selected' === action) {
          $(document.body).trigger('wc_fragment_refresh');
        }
        updateWishlistContainer(actionScope, response);
        jQuery.fn.tinvwl_get_wishlist_data();
        if (response.wishlists_data) {
          set_hash(JSON.stringify(response.wishlists_data));
        }
        $('body').trigger('tinvwl_wishlist_ajax_response', [this, response]);
        if (true === response.status && response.redirect) {
          window.location.href = response.redirect;
        }
      });
    });

    // Disable add to wishlist button if variations not selected
    $(document).on('hide_variation', '.variations_form', function (a) {
      var e = variationFormButtons(this);
      e.attr('data-tinv-wl-productvariation', 0);
      var originalBlockAjaxWishlistsData = tinvwl_add_to_wishlist.block_ajax_wishlists_data;
      tinvwl_add_to_wishlist.block_ajax_wishlists_data = true;
      $.fn.tinvwl_get_wishlist_data();
      tinvwl_add_to_wishlist.block_ajax_wishlists_data = originalBlockAjaxWishlistsData;
      if (e.length && !tinvwl_add_to_wishlist.allow_parent_variable) {
        a.preventDefault();
        e.addClass('disabled-add-wishlist');
      }
    });
    $(document).on('show_variation', '.variations_form', function (a, b, d) {
      var e = variationFormButtons(this);
      e.attr('data-tinv-wl-productvariation', b.variation_id);
      var originalBlockAjaxWishlistsData = tinvwl_add_to_wishlist.block_ajax_wishlists_data;
      tinvwl_add_to_wishlist.block_ajax_wishlists_data = true;
      $.fn.tinvwl_get_wishlist_data();
      tinvwl_add_to_wishlist.block_ajax_wishlists_data = originalBlockAjaxWishlistsData;
      a.preventDefault();
      e.removeClass('disabled-add-wishlist');
    });

    // A cross-tab marker invalidates the snapshot; cached private HTML is never rendered.
    $(window).off('.tinvwlWishlistData').on('storage.tinvwlWishlistData onstorage.tinvwlWishlistData', function (e) {
      var changed = e.originalEvent || e;
      if (changed.key !== hash_key && null !== changed.key) {
        return;
      }
      wishlistSnapshot = null;
      if (!tinvwl_add_to_wishlist.block_ajax_wishlists_data) {
        _get_data_ajax(undefined, true);
      }
    });

    // Get wishlist data from REST API.
    var statsReadPending = false;
    var _get_data_ajax = function get_data_ajax(refresh, force, target, statsProducts) {
      if (mutationRunning || mutationQueue.length) {
        if ('refresh' === refresh) {
          rememberWishlistViews(target);
        }
        return;
      }
      if (statsProducts && statsReadPending) {
        return;
      }
      if ('refresh' === refresh && !(target && target.jquery)) {
        $('div.tinv-wishlist.woocommerce.tinv-wishlist-clear form[data-tinvwl_sharekey]').filter(function () {
          return 'undefined' === typeof target || $(this).attr('data-tinvwl_sharekey') === String(target);
        }).each(function () {
          _get_data_ajax(refresh, force, $(this));
        });
        return;
      }
      var hasProducts = $('a.tinvwl_add_to_wishlist_button').filter(function () {
        return !!$(this).data('tinv-wl-product');
      }).length;
      if (('refresh' === refresh || hasProducts || $('.wishlist_products_counter_number').length) && (tinvwl_add_to_wishlist.user_interacted || force)) {
        var readRevision = wishlistRevision;
        var readSequence = ++latestRead;
        var fullRefreshSequence = 'refresh' === refresh ? ++latestFullRefresh : 0;
        var requestContainer = 'refresh' === refresh ? target.closest('div.tinv-wishlist.woocommerce.tinv-wishlist-clear') : $();
        var data = {
          'tinvwl-action': 'get_data',
          'tinvwl-security': tinvwl_add_to_wishlist.nonce
        };
        if ('refresh' === refresh) {
          var form = target;
          if (form.length) {
            requestContainer.data('tinvwlFullRefreshSequence', fullRefreshSequence).block({
              message: null,
              overlayCSS: {
                background: '#fff',
                opacity: 0.6
              }
            });
            data['tinvwl-paged'] = form.data('tinvwl_paged');
            data['tinvwl-per-page'] = form.data('tinvwl_per_page');
            data['tinvwl-sharekey'] = form.data('tinvwl_sharekey');
          }
        }
        if (tinvwl_add_to_wishlist.wpml) {
          data.lang = tinvwl_add_to_wishlist.wpml;
        }
        if (tinvwl_add_to_wishlist.wpml_default) {
          data.lang_default = tinvwl_add_to_wishlist.wpml_default;
        }
        if (tinvwl_add_to_wishlist.stats) {
          data.stats = tinvwl_add_to_wishlist.stats;
          data.stats_products = statsProducts || wishlistStatsProductIds();
          if (statsProducts) {
            statsReadPending = true;
          }
        }
        wishlistAjax({
          url: tinvwl_add_to_wishlist.wc_ajax_url,
          method: 'POST',
          cache: false,
          data: data,
          beforeSend: function beforeSend(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', tinvwl_add_to_wishlist.nonce);
          }
        }).always(function () {
          if (statsProducts) {
            statsReadPending = false;
          }
          if ('refresh' === refresh) {
            finishWishlistAction(undefined, requestContainer);
          }
        }).fail(function (xhr) {
          if ('refresh' === refresh) {
            showRequestError(xhr);
          }
        }).done(function (response) {
          if (!response || true !== response.status || 'refresh' === refresh && ('string' !== typeof response.content || !response.content.trim())) {
            if ('refresh' === refresh) {
              showRequestError();
            }
            return;
          }
          if ('refresh' === refresh) {
            $(document.body).trigger('wc_fragment_refresh');
            if (readRevision === wishlistRevision && fullRefreshSequence === requestContainer.data('tinvwlFullRefreshSequence')) {
              requestContainer.replaceWith(response.content);
              $.tinvwlStorage.set('localStorage', hash_key + '_refresh', '');
            }
          }
          if (response.wishlists_data && readRevision === wishlistRevision && readSequence === latestRead) {
            // A stale/disabled server or malformed response must not restart an empty batch forever.
            if (statsProducts && (!response.wishlists_data.stats || !statsProducts.some(function (id) {
              return owns(response.wishlists_data.stats, id);
            }))) {
              return;
            }
            if (statsProducts && wishlistSnapshot && wishlistSnapshot.stats && response.wishlists_data.stats) {
              response.wishlists_data.stats = $.extend({}, wishlistSnapshot.stats, response.wishlists_data.stats);
            }
            set_hash(JSON.stringify(response.wishlists_data), false);
          }
          $('body').trigger('tinvwl_wishlist_ajax_response', [this, response]);
        });
      }
    };
    $.fn.tinvwl_get_wishlist_data = function (refresh, sharekey) {
      if ('synchronize' === refresh) {
        rememberWishlistViews();
        if (mutationRunning || mutationQueue.length) {
          return;
        }
        var views = pendingWishlistViews;
        pendingWishlistViews = Object.create(null);
        $('div.tinv-wishlist.woocommerce.tinv-wishlist-clear form[data-tinvwl_sharekey]').filter(function () {
          return owns(views, $(this).attr('data-tinvwl_sharekey'));
        }).each(function () {
          _get_data_ajax('refresh', true, $(this));
        });
        // Request the canonical snapshot last so a shared view cannot replace its state.
        _get_data_ajax(undefined, true);
        return;
      }
      if ('refresh' === refresh) {
        _get_data_ajax(refresh, true, sharekey);
        return;
      }
      try {
        if ('undefined' !== typeof Cookies && Cookies.get('tinvwl_update_data') !== undefined) {
          Cookies.set('tinvwl_update_data', 0, {
            expires: -1
          });
          wishlistSnapshot = null;
          $.tinvwlStorage.remove('localStorage', hash_key);
        }
      } catch (error) {/* Cache invalidation is optional when cookies are blocked. */}
      if (wishlistSnapshot) {
        mark_products(wishlistSnapshot);
        var missingStats = tinvwl_add_to_wishlist.stats && wishlistSnapshot.stats ? wishlistStatsProductIds(wishlistSnapshot.stats) : [];
        if (missingStats.length && !tinvwl_add_to_wishlist.block_ajax_wishlists_data) {
          _get_data_ajax(undefined, true, undefined, missingStats);
        }
        return;
      }
      if (tinvwl_add_to_wishlist.block_ajax_wishlists_data) {
        return;
      }
      _get_data_ajax();
    };
    tinvwl_add_to_wishlist.user_interacted = false;
    $.fn.tinvwl_get_wishlist_data();
    $(document).one('click keydown scroll', function () {
      tinvwl_add_to_wishlist.user_interacted = true;
      $.fn.tinvwl_get_wishlist_data();
    });

    /* Dynamic buttons */
    // Create an observer instance
    var observer = new MutationObserver(function (mutations) {
      var tinvwl_products = [];
      mutations.forEach(function (mutation) {
        var newNodes = mutation.addedNodes;

        // If there are new nodes added
        if (null !== newNodes) {
          var $nodes = $(newNodes);
          $nodes.each(function () {
            var $node = $(this),
              els = $node.find('.tinvwl_add_to_wishlist_button').add($node.filter('.tinvwl_add_to_wishlist_button'));
            if (els.length) {
              els.each(function () {
                var $this = $(this),
                  productData = $this.data('tinv-wl-product');
                if ('undefined' !== typeof productData && productData) {
                  tinvwl_products.push(productData);
                }
              });
            }
          });
        }
      });
      if (tinvwl_products.length) {
        $.fn.tinvwl_get_wishlist_data();
      }
    });

    // Configuration of the observer
    var config = {
      childList: true,
      subtree: true
    };
    var targetNode = document.body;

    // Start observing
    observer.observe(targetNode, config);
  });

  /* The current page keeps live server data only; persistent values are wake-up markers. */
  var wishlistSnapshot = null;
  var wishlistRevision = 0;
  var latestRead = 0;
  var latestFullRefresh = 0;
  var hash_key = tinvwl_add_to_wishlist.hash_key;
  // Remove legacy private payloads without parsing, rendering or using them as credentials.
  $.tinvwlStorage.remove('localStorage', hash_key);
  $.tinvwlStorage.remove('sessionStorage', hash_key);

  /**
   * Marks products based on the provided data.
   *
   * @param {object} data - The data containing information about wishlists, products, and stats.
   */

  // Grouped buttons represent child simple rows, independently of variation membership.
  function groupedButtonProducts(data) {
    var products = $.extend({}, data.products || {});
    $('a.tinvwl_add_to_wishlist_button').each(function () {
      var type = $(this).attr('data-tinv-wl-producttype');
      if ('grouped' !== type && 'group' !== type) {
        return;
      }
      var children = parseWishlistJSON($(this).attr('data-tinv-wl-grouped-children')),
        lists = Object.create(null);
      if (!Array.isArray(children)) {
        return;
      }
      children.forEach(function (child) {
        $.each(data.products && data.products[child] || {}, function (key, value) {
          var item = parseWishlistJSON(value);
          if (!item || 'object' !== _typeof(item) || !Array.isArray(item.in) || !item.in.some(function (variation) {
            return 0 === variation || '0' === variation;
          })) {
            return;
          }
          var id = item.ID;
          if (!id) {
            return;
          }
          if (!lists[id]) {
            lists[id] = $.extend({}, item, {
              in: [0],
              grouped_children: []
            });
          }
          if (-1 === lists[id].grouped_children.indexOf(Number(child))) {
            lists[id].grouped_children.push(Number(child));
          }
        });
      });
      if (Object.keys(lists).length) {
        products[$(this).attr('data-tinv-wl-product')] = lists;
      }
    });
    return products;
  }
  function mark_products(data) {
    data = wishlistData(data);
    if (!data) {
      return;
    }
    var g = '1' == window.tinvwl_add_to_wishlist['simple_flow'];
    $('a.tinvwl_add_to_wishlist_button').each(function () {
      $(this).removeClass('tinvwl-product-make-remove').removeClass('tinvwl-product-in-list').attr('data-tinv-wl-action', 'addto').attr('data-tinv-wl-list', '[]');
      if (data && data.stats && owns(data.stats, $(this).attr('data-tinv-wl-product'))) {
        $(this).find('span.tinvwl-product-stats').remove();
      }
    });
    $('body').trigger('tinvwl_wishlist_mark_products', [data]);
    $.each(groupedButtonProducts(data), function (i, item) {
      if (!item || 'object' !== _typeof(item)) {
        return;
      }
      var id = i,
        e = $('a.tinvwl_add_to_wishlist_button[data-tinv-wl-product="' + id + '"]');
      e.each(function () {
        var vid = parseInt($(this).attr('data-tinv-wl-productvariation')),
          vids = $(this).data('tinv-wl-productvariations') || [],
          j = false;
        for (var i in item) {
          item[i] = parseWishlistJSON(item[i]);
          if (!item[i] || 'object' !== _typeof(item[i]) || Array.isArray(item[i])) {
            delete item[i];
            continue;
          }
          if (owns(item[i], 'in') && Array.isArray(item[i].in) && (-1 < (item[i].in || []).indexOf(id) || -1 < (item[i].in || []).indexOf(vid) || vids.some(function (r) {
            return 0 <= (item[i].in || []).indexOf(r);
          }))) {
            j = true;
          }
        }
        var validItems = Array.isArray(item) ? item.filter(function (value) {
          return !!value;
        }) : item;
        $(this).attr('data-tinv-wl-list', JSON.stringify(validItems)).toggleClass('tinvwl-product-in-list', j).toggleClass('tinvwl-product-make-remove', j && g).attr('data-tinv-wl-action', j && g ? 'remove' : 'addto');
        $('body').trigger('tinvwl_wishlist_product_marked', [this, j]);
      });
    });
    if (data && data.stats && tinvwl_add_to_wishlist.stats) {
      $.each(data.stats, function (i, item) {
        var id = i,
          e = $('a.tinvwl_add_to_wishlist_button[data-tinv-wl-product="' + id + '"]');
        e.each(function () {
          $(this).attr('data-tinv-wl-product-stats', JSON.stringify(item));
          var vid = parseInt($(this).attr('data-tinv-wl-productvariation')),
            j = false;
          for (var i in item) {
            if (String(vid) === i) {
              j = true;
              $('body').trigger('tinvwl_wishlist_product_stats', [this, j]);
              $(this).append('<span class="tinvwl-product-stats">' + item[i] + '</span>');
            }
          }
        });
      });
    }
    $('a.tinvwl_add_to_wishlist_button').each(function () {
      updateProductActionLabel(this);
    });
    update_product_counter(data.counter);
  }

  /**
   * Sets the hash in local storage.
   *
   * @param {string} hash - The hash value to set.
   */
  function set_hash(hash, notify) {
    var data = wishlistData(hash);
    if (!data) {
      return;
    }
    if (false !== notify) {
      wishlistRevision++;
    }
    wishlistSnapshot = data;
    mark_products(data);
    if (tinvwl_add_to_wishlist.stats && data.stats && wishlistStatsProductIds(data.stats).length && !tinvwl_add_to_wishlist.block_ajax_wishlists_data) {
      window.setTimeout(function () {
        $.fn.tinvwl_get_wishlist_data();
      }, 0);
    }
    if (false !== notify) {
      // Non-secret wake-up token; reads never publish it, preventing cross-tab loops.
      $.tinvwlStorage.set('localStorage', hash_key, 'tinvwl:' + Date.now() + ':' + Math.random());
    }
  }

  /**
   * Updates the product counter and mini wishlist.
   *
   * @param {string|number} counter - The counter value.
   */
  function update_product_counter(counter) {
    // Hide counter if necessary
    if ('1' == window.tinvwl_add_to_wishlist['hide_zero_counter'] && 0 === counter) {
      counter = 'false';
    }

    // Add class to wishlist icon
    jQuery('i.wishlist-icon').addClass('added');

    // Update counter and icon label if counter is not 'false'
    if ('false' !== counter) {
      jQuery('.wishlist_products_counter_number, .theme-item-count.wishlist-item-count').html(counter);
      jQuery('i.wishlist-icon').attr('data-icon-label', counter);
    } else {
      // Remove counter and icon label if counter is 'false'
      jQuery('.wishlist_products_counter_number, .theme-item-count.wishlist-item-count').html('').closest('span.wishlist-counter-with-products').removeClass('wishlist-counter-with-products');
      jQuery('i.wishlist-icon').removeAttr('data-icon-label');
    }
    var has_products = !('0' == counter || 'false' == counter);

    // Toggle class based on the presence of products
    jQuery('.wishlist_products_counter').toggleClass('wishlist-counter-with-products', has_products);

    // Remove 'added' class from wishlist icon after a delay
    setTimeout(function () {
      jQuery('i.wishlist-icon').removeClass('added');
    }, 500);
  }

  // One lifecycle for native result dialogs and pre-rendered modal panels.
  var modalStack = [],
    modalOpener = null,
    modalSequence = 0,
    movingModalFocus = false;
  var modalSelector = '.tinv-wishlist .tinv-modal.tinv-modal-open';
  var modalCloseSelector = '.tinv-close-modal, .tinvwl_button_close, .tinv-overlay';
  function modalConnected(element) {
    return element && document.documentElement.contains(element);
  }
  function modalFocusable(panel) {
    return $(panel).find('a[href], area[href], button, input:not([type="hidden"]), select, textarea, [tabindex], [contenteditable="true"]').filter(function () {
      return !$(this).is(':disabled') && 0 <= this.tabIndex && $(this).is(':visible') && !$(this).closest('[inert]').length;
    });
  }
  function modalFocus(element) {
    if (!modalConnected(element) || !$(element).is(':visible') || $(element).is(':disabled')) {
      return false;
    }
    movingModalFocus = true;
    element.focus();
    movingModalFocus = false;
    return document.activeElement === element;
  }
  function modalFocusFirst(entry) {
    modalFocus(modalFocusable(entry.panel)[0] || entry.panel);
    // Pre-rendered panels become focusable after the visibility transition starts rendering.
    var attempts = 0;
    var frame = window.requestAnimationFrame || function (callback) {
      window.setTimeout(callback, 16);
    };
    function finishOpening() {
      if (modalStack[modalStack.length - 1] === entry && !entry.panel.contains(document.activeElement)) {
        if (!modalFocus(modalFocusable(entry.panel)[0] || entry.panel) && ++attempts < 3) {
          frame(finishOpening);
        }
      }
    }
    frame(finishOpening);
  }
  function modalOrigin(element) {
    var opener = $(element)[0],
      parent = $(opener).closest('.tinv-modal');
    var entry = parent.data('tinvwlModalState');
    return entry && !parent.hasClass('tinv-modal-open') ? entry.opener : opener;
  }
  function prepareModal(modal, opener) {
    var panel = $(modal).find('.tinv-modal-inner').first();
    if (!panel.length) {
      panel = $(modal);
    }
    panel.attr('role', 'dialog');
    if (!panel.attr('tabindex')) {
      panel.attr('tabindex', '-1');
    }
    if (!panel.attr('aria-label') && !panel.attr('aria-labelledby')) {
      var heading = panel.find('h1, h2, h3, h4, h5, h6').first();
      if (heading.length && $.trim(heading.text())) {
        if (!heading.attr('id')) {
          heading.attr('id', 'tinvwl-modal-title-' + ++modalSequence);
        }
        panel.attr('aria-labelledby', heading.attr('id'));
      } else {
        var label = $.trim(panel.find('.tinv-txt, .tinvwl-txt, .delete-notification').first().text());
        panel.attr('aria-label', label || tinvwl_add_to_wishlist.i18n_modal_title);
      }
    }
    $(modal).find('.tinv-close-modal, .tinvwl_button_close').each(function () {
      var button = $(this);
      if (!$.trim(button.text()) && !button.attr('aria-label') && !button.attr('aria-labelledby') && !button.attr('title')) {
        button.attr('aria-label', tinvwl_add_to_wishlist.i18n_modal_close);
      }
    });
    if (opener && modalConnected(opener)) {
      if (!modal.id) {
        modal.id = 'tinvwl-modal-' + ++modalSequence;
      }
      $(opener).attr({
        'aria-haspopup': 'dialog',
        'aria-controls': modal.id,
        'aria-expanded': 'true'
      });
    }
    var entry = {
      modal: modal,
      panel: panel[0],
      opener: opener
    };
    $(modal).data('tinvwlModalState', entry);
    return entry;
  }
  function syncModals() {
    var previous = modalStack[modalStack.length - 1];
    var opened = $(modalSelector).filter(':visible').get();
    var remaining = modalStack.filter(function (entry) {
      if (0 <= opened.indexOf(entry.modal)) {
        return true;
      }
      $(entry.panel).removeAttr('aria-modal');
      if (entry.opener) {
        $(entry.opener).attr('aria-expanded', 'false');
      }
      return false;
    });
    $.each(opened, function (index, modal) {
      if (remaining.some(function (entry) {
        return entry.modal === modal;
      })) {
        return;
      }
      // A replaced result retains the original opener; a nested one uses its own trigger.
      var opener = modalOpener || (previous && !modalConnected(previous.modal) ? previous.opener : document.activeElement);
      remaining.push(prepareModal(modal, opener));
      modalOpener = null;
    });
    modalOpener = null;
    modalStack = remaining;
    var active = modalStack[modalStack.length - 1];
    $.each(modalStack, function (index, entry) {
      if (entry === active) {
        $(entry.panel).attr('aria-modal', 'true');
      } else {
        $(entry.panel).removeAttr('aria-modal');
      }
    });
    if (active === previous) {
      return;
    }
    if (previous && !modalConnected(previous.modal) && active && active.opener === previous.opener) {
      modalFocusFirst(active);
    } else if (previous && remaining.indexOf(previous) < 0 && modalFocus(previous.opener)) {
      // Closing a nested result returns to the still-open parent before leaving the stack.
      if (active && !active.panel.contains(document.activeElement)) {
        modalFocusFirst(active);
      }
    } else if (active) {
      modalFocusFirst(active);
    }
  }
  $.tinvwlModal = {
    open: function open(modal, opener) {
      if (!$(modal).length) {
        return;
      }
      modalOpener = modalOrigin(opener) || document.activeElement;
      $(modal).addClass('tinv-modal-open');
      syncModals();
    },
    sync: function sync(opener) {
      modalOpener = modalOrigin(opener) || null;
      syncModals();
    }
  };
  $(document).on('keydown.tinvwlModal', function (event) {
    var active = modalStack[modalStack.length - 1];
    if (!active) {
      return;
    }
    if ('Escape' === event.key || 27 === event.which) {
      event.preventDefault();
      event.stopPropagation();
      var close = $(active.modal).find(modalCloseSelector).filter(function () {
        return !$(this).is(':disabled');
      }).first();
      if (close.length) {
        close.trigger('click');
      } else {
        $(active.modal).removeClass('tinv-modal-open');
        $('body').trigger('tinvwl_modal_closed', [active.modal]);
      }
      syncModals();
    } else if ('Tab' === event.key || 9 === event.which) {
      var targets = modalFocusable(active.panel);
      var first = targets[0],
        last = targets[targets.length - 1],
        focused = document.activeElement;
      if (!targets.length || !active.panel.contains(focused) || focused === active.panel || (event.shiftKey ? focused === first : focused === last)) {
        event.preventDefault();
        modalFocus((event.shiftKey ? last : first) || active.panel);
      }
    }
  }).on('focusin.tinvwlModal', function (event) {
    var active = modalStack[modalStack.length - 1];
    if (!movingModalFocus && active && modalConnected(active.modal) && $(active.modal).hasClass('tinv-modal-open') && !active.panel.contains(event.target)) {
      modalFocusFirst(active);
    }
  });
  $(function () {
    syncModals();
    if (window.MutationObserver) {
      new MutationObserver(syncModals).observe(document.body, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['class']
      });
    }
  });
  function FocusTrap(element, opener) {
    modalOpener = modalOrigin(opener) || null;
    syncModals();
  }
  function removeModalResult(modal) {
    // Let native close/updatepage delegates see their still-connected result before removal.
    window.setTimeout(function () {
      modal.remove();
    }, 0);
  }
})(jQuery);
"use strict";

// Initialize a single guest secret before the first mutation, including virgin tabs.
(function ($) {
  $.tinvwlGuestNonceRequest = function (settings) {
    var deferred = $.Deferred(),
      started = false,
      timer;
    function send(headers) {
      return $.ajax($.extend({}, settings, {
        headers: $.extend({}, settings.headers, headers)
      })).done(function () {
        deferred.resolveWith(this, arguments);
      }).fail(function () {
        deferred.rejectWith(this, arguments);
      });
    }
    function fallback() {
      if (started) {
        return;
      }
      started = true;
      window.clearTimeout(timer);
      if (window.navigator.locks && window.navigator.locks.request) {
        window.navigator.locks.request('tinvwl-guest-bootstrap', function () {
          return new window.Promise(function (resolve) {
            send({
              'X-TInvWL-Guest-Locked': '1'
            }).always(function () {
              resolve();
            });
          });
        }).catch(function () {
          send({});
        });
      } else {
        // An existing HttpOnly cookie still works. A fresh guest fails safely
        // if neither atomic browser storage nor a cross-tab mutex is available.
        send({});
      }
    }
    try {
      var crypto = window.crypto || window.msCrypto;
      if (!window.indexedDB || !crypto || !crypto.getRandomValues) {
        fallback();
        return deferred.promise();
      }
      var open = window.indexedDB.open('tinvwl_guest_bootstrap', 1);
      timer = window.setTimeout(fallback, 2000);
      open.onupgradeneeded = function () {
        open.result.createObjectStore('identity');
      };
      open.onerror = open.onblocked = fallback;
      open.onsuccess = function () {
        var db = open.result;
        if (started) {
          db.close();
          return;
        }
        try {
          var transaction = db.transaction('identity', 'readwrite'),
            store = transaction.objectStore('identity'),
            read = store.get('session'),
            value;
          read.onsuccess = function () {
            value = read.result;
            if (!value || 'string' !== typeof value.secret || !/^[a-f0-9]{64}$/.test(value.secret) || 'number' !== typeof value.expires || !isFinite(value.expires) || value.expires <= Date.now()) {
              var bytes = crypto.getRandomValues(new Uint8Array(32)),
                secret = '';
              for (var i = 0; i < bytes.length; i++) {
                secret += ('0' + bytes[i].toString(16)).slice(-2);
              }
              value = {
                secret: secret,
                expires: Date.now() + 86400000
              };
              store.put(value, 'session');
            }
          };
          transaction.oncomplete = function () {
            db.close();
            if (started) {
              return;
            }
            started = true;
            window.clearTimeout(timer);
            send({
              'X-TInvWL-Guest-Bootstrap': value.secret
            });
          };
          transaction.onabort = transaction.onerror = function () {
            db.close();
            fallback();
          };
        } catch (error) {
          db.close();
          fallback();
        }
      };
    } catch (error) {
      fallback();
    }
    return deferred.promise();
  };
})(jQuery);
"use strict";

// Misc
(function ($) {
  $(document).ready(function () {
    $('.tinv-lists-nav').each(function () {
      if (!$(this).html().trim().length) {
        $(this).remove();
      }
    });
    $('body').on('click', '.social-buttons .social:not(.social-email,.social-whatsapp,.social-clipboard)', function (e) {
      var newWind = window.open($(this).attr('href'), $(this).attr('title'), 'width=420,height=320,resizable=yes,scrollbars=yes,status=yes');
      if (newWind) {
        newWind.focus();
        e.preventDefault();
      }
    });
    if ('undefined' !== typeof ClipboardJS) {
      var clipboard = new ClipboardJS('.social-buttons .social.social-clipboard', {
        text: function text(trigger) {
          return trigger.getAttribute('href');
        }
      });
      clipboard.on('success', function (e) {
        showTooltip(e.trigger, tinvwl_add_to_wishlist.tinvwl_clipboard);
      });
      var btns = document.querySelectorAll('.social-buttons .social.social-clipboard');
      for (var i = 0; i < btns.length; i++) {
        btns[i].addEventListener('mouseleave', clearTooltip);
        btns[i].addEventListener('blur', clearTooltip);
      }
    }
    $('body').on('click', '.social-buttons .social.social-clipboard', function (e) {
      e.preventDefault();
    });
    $('body').on('click', '.tinv-wishlist .tinv-overlay, .tinv-wishlist .tinv-close-modal, .tinv-wishlist .tinvwl_button_close', function (e) {
      e.preventDefault();
      $(this).parents('.tinv-modal:first').removeClass('tinv-modal-open');
      $('body').trigger('tinvwl_modal_closed', [this]);
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
        if (5 > navigationButtons.length) {
          navigationButtons.parent().addClass('tinvwl-btns-count-' + navigationButtons.length);
        }
      });
    }
    $('.tinv-login .showlogin').off('click').on('click', function (e) {
      e.preventDefault();
      $(this).closest('.tinv-login').find('.login').toggle();
    });
    $('.tinv-wishlist table.tinvwl-table-manage-list tfoot td').each(function () {
      $(this).toggle(!!$(this).children().not('.look_in').length || !!$(this).children('.look_in').children().length);
    });
  });
})(jQuery);
function showTooltip(elem, msg) {
  if (!Object.prototype.hasOwnProperty.call(elem, 'tinvwlTooltipLabel')) {
    elem.tinvwlTooltipLabel = elem.getAttribute('aria-label');
  }
  jQuery(elem).addClass('tooltipped tooltipped-s');
  elem.setAttribute('aria-label', msg);
}
function clearTooltip(e) {
  var elem = e.currentTarget;
  jQuery(elem).removeClass('tooltipped tooltipped-s');
  if (Object.prototype.hasOwnProperty.call(elem, 'tinvwlTooltipLabel')) {
    if (null === elem.tinvwlTooltipLabel) {
      elem.removeAttribute('aria-label');
    } else {
      elem.setAttribute('aria-label', elem.tinvwlTooltipLabel);
    }
    delete elem.tinvwlTooltipLabel;
  }
}
"use strict";

// Wishlist table
(function ($) {
  //Prevent to submit wishlist table action
  $.fn.tinvwl_break_submit = function (so) {
    var sd = {
      selector: 'input, select, textarea',
      ifempty: true,
      invert: false,
      validate: function validate() {
        return $(this).val();
      },
      rule: function rule() {
        var form_elements = $(this).parents('form').eq(0).find(s.selector),
          trigger = s.invert;
        if (0 === form_elements.length) {
          return s.ifempty;
        }
        form_elements.each(function () {
          if (trigger && !s.invert || !trigger && s.invert) {
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
        var ss = [];
        if ('undefined' !== typeof $(this).attr('tinvwl_break_submit')) {
          ss = $(this).attr('tinvwl_break_submit').split(',');
        }
        if (-1 !== jQuery.inArray(s.selector, ss)) {
          ss = [];
        }
        if (!s.rule.call($(this)) && 0 === ss.length) {
          alert(window.tinvwl_add_to_wishlist['tinvwl_break_submit']);
          event.preventDefault();
        }
        ss.push(s.selector);
        $(this).attr('tinvwl_break_submit', ss);
        if (s.rule.call($(this))) {
          $(this).removeAttr('tinvwl_break_submit');
        }
      });
    });
  };
  $(document).ready(function () {
    var wishlistCheckboxes = '.product-cb input[type=checkbox]:not(.global-cb), .wishlist-cb input[type=checkbox]:not(.global-cb)';
    function syncGlobalCheckbox(table) {
      var checkboxes = table.find(wishlistCheckboxes);
      var selected = checkboxes.filter(':checked').length;
      var globalCheckbox = table.find('.global-cb');
      globalCheckbox.prop('checked', checkboxes.length && selected === checkboxes.length);
      globalCheckbox.prop('indeterminate', selected > 0 && selected < checkboxes.length);
    }

    // Wishlist table bulk action checkbox
    $('body').on('click', '.global-cb', function () {
      var table = $(this).closest('table').eq(0);
      table.find(wishlistCheckboxes).prop('checked', $(this).is(':checked'));
      $(this).prop('indeterminate', false);
    });

    // Keep the bulk action checkbox in sync with individual wishlist items.
    $('body').on('change', wishlistCheckboxes, function () {
      syncGlobalCheckbox($(this).closest('table').eq(0));
    });
    var hash_key = tinvwl_add_to_wishlist.hash_key + '_refresh';

    // Refresh table
    $(document.body).on('tinvwl_wishlist_ajax_response', function (event, element, response) {
      // Check if the action is one of the specified values and the status is true
      if ((response.status || response.removed) && ['add_to_wishlist'].includes(response.action)) {
        // Run wishlist refresh
        if (response.wishlist && response.wishlist.share_key) {
          $.tinvwlStorage.set('localStorage', hash_key, '');
          $.tinvwlStorage.set('localStorage', hash_key, response.wishlist.share_key);
        }
      }
    });
  });
})(jQuery);