// Wishlist table
( function( $ ) {

	//Prevent to submit wishlist table action
	$.fn.tinvwl_break_submit = function( so ) {
		var sd = {
			selector: 'input, select, textarea',
			ifempty: true,
			invert: false,
			validate: function() {
				return $( this ).val();
			},
			rule: function() {
				var form_elements = $( this ).parents( 'form' ).eq( 0 ).find( s.selector ),
					trigger = s.invert;
				if ( 0 === form_elements.length ) {
					return s.ifempty;
				}
				form_elements.each( function() {
					if ( ( trigger && ! s.invert ) || ( ! trigger && s.invert ) ) {
						return;
					}
					trigger = Boolean( s.validate.call( $( this ) ) );
				});
				return trigger;
			}
		};
		var s = $.extend( true, {}, sd, so );
		return $( this ).each( function() {
			$( this ).on( 'click', function( event ) {
				var ss = [];

				if ( 'undefined' !== typeof $( this ).attr( 'tinvwl_break_submit' ) ) {
					ss = $( this ).attr( 'tinvwl_break_submit' ).split( ',' );
				}

				if ( -1 !== jQuery.inArray( s.selector, ss ) ) {
					ss = [];
				}

				if ( ! s.rule.call( $( this ) ) && 0 === ss.length ) {
					alert( window.tinvwl_add_to_wishlist['tinvwl_break_submit']);
					event.preventDefault();
				}
				ss.push( s.selector );
				$( this ).attr( 'tinvwl_break_submit', ss );
				if ( s.rule.call( $( this ) ) ) {
					$( this ).removeAttr( 'tinvwl_break_submit' );
				}
			});
		});
	};

	$( document ).ready( function() {

		// Wishlist table bulk action checkbox
		$( 'body' ).on( 'click', '.global-cb', function() {
			$( this ).closest( 'table' ).eq( 0 ).find( '.product-cb input[type=checkbox], .wishlist-cb input[type=checkbox]' ).prop( 'checked', $( this ).is( ':checked' ) );
		});

		var hash_key = tinvwl_add_to_wishlist.hash_key + '_refresh';

		// Refresh table
		$( document.body ).on( 'tinvwl_wishlist_ajax_response', function( event, element, response ) {

			// Check if the action is one of the specified values and the status is true
			if ( ( response.status || response.removed ) && [ 'add_to_wishlist' ].includes( response.action ) ) {

				// Run wishlist refresh
				if ( response.wishlist && response.wishlist.share_key ) {
					localStorage.setItem( hash_key, '' );
					localStorage.setItem( hash_key, response.wishlist.share_key );
				}
			}
		});
	});
}( jQuery ) );
