(function (wp) {

	if (!wp) {
		return;
	}
	if ('undefined' == typeof (wp.blocks)) {
		return;
	}
	if ('undefined' == typeof (wp.element)) {
		return;
	}

	const {getCategories, registerBlockType, setCategories} = wp.blocks;
	var el = wp.element.createElement;
	var __ = wp.i18n.__;


	const TinvWLButton = (props) => {
		return el('div', {'class': 'wp-block-button  wc-block-components-product-button wc-block-button-tinvwl'},
			el('a', {
					'javascript': 'void(0)', 'data-product_id': props.product.id,
					'class': 'single-product button wp-block-button__link',
					'title': __('Add to Wishlist', 'ti-woocommerce-wishlist')
				},
				el('span', {'class': ''}, __('Add to Wishlist', 'ti-woocommerce-wishlist')),
			));
	};


	const blockConfig = {
		category: 'woocommerce-product-elements',
		keywords: [__('WooCommerce', 'woo-gutenberg-products-block')],
		supports: {
			html: false
		},
		parent: ['woocommerce/all-products'],
		icon: el('img', {
			'class': 'tinvwl-component-icon',
			'src': tinvwl_add_to_wishlist.plugin_url + '/assets/img/logo_heart.png'
		}),
		title: __('Add to Wishlist', 'ti-woocommerce-wishlist'),
		description: __('Display an add to wishlist button for the product.', 'ti-woocommerce-wishlist'),
		edit: function (props) {
			return el(TinvWLButton, {product: {}});
		}
	};

	registerBlockType('tinvwl/add-to-wishlist', blockConfig);

}(
	window.wp
));


