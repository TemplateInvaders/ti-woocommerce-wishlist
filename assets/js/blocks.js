// WooCommerce Blocks All Products

( function( wp ) {

	if ( ! wp || ( 'undefined' == typeof ( wp.element ) ) || 'undefined' == typeof ( wp.i18n ) ) {
		console.log( 'wp.element or wp.i18n not found - cannot load support for WooCommerce blocks' );
	}
	if ( 'undefined' == typeof ( window.React ) ) {
		console.log( 'No react, so we can\'t continue adding support for TI WooCOmmerce Wishlist to blocks.' );
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	const TinvWLButton = ( props ) => {
		if ( 'undefined' != typeof props.product.description && props.product.description.match( /tinvwl-loop-button-wrapper/ ) ) {
			var btn = jQuery( '<div>' + props.product.description.replace( /<[\/]{0,1}(p)[^><]*>/ig, '' ) + '</div>' ).find( '.tinvwl-loop-button-wrapper' );

			if ( jQuery( btn[0]).length ) {
				return el( 'div', {
					dangerouslySetInnerHTML: {
						__html: jQuery( btn[0]).prop( 'outerHTML' )
					}
				});
			}
		}
		return null;
	}
	;

	const {registerBlockComponent} = wc.wcBlocksRegistry;
	const mainBlock = 'woocommerce/all-products';

	registerBlockComponent({
		main: mainBlock,
		blockName: 'tinvwl/add-to-wishlist',
		component: TinvWLButton,
		context: mainBlock
	});

}
( window.wp ) );
