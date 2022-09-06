// Misc
( function( $ ) {
	$( document ).ready( function() {

		$( '.tinv-lists-nav' ).each( function() {
			if ( ! $( this ).html().trim().length ) {
				$( this ).remove();
			}
		});

		$( 'body' ).on( 'click', '.social-buttons .social:not(.social-email,.social-whatsapp,.social-clipboard)', function( e ) {
			var newWind = window.open( $( this ).attr( 'href' ), $( this ).attr( 'title' ), 'width=420,height=320,resizable=yes,scrollbars=yes,status=yes' );
			if ( newWind ) {
				newWind.focus();
				e.preventDefault();
			}
		});
		if ( 'undefined' !== typeof ClipboardJS ) {
			var clipboard = new ClipboardJS( '.social-buttons .social.social-clipboard', {
				text: function( trigger ) {
					return trigger.getAttribute( 'href' );
				}
			});

			clipboard.on( 'success', function( e ) {
				showTooltip( e.trigger, tinvwl_add_to_wishlist.tinvwl_clipboard );
			});

			var btns = document.querySelectorAll( '.social-buttons .social.social-clipboard' );
			for ( var i = 0; i < btns.length; i++ ) {
				btns[i].addEventListener( 'mouseleave', clearTooltip );
				btns[i].addEventListener( 'blur', clearTooltip );
			}
		}

		$( 'body' ).on( 'click', '.social-buttons .social.social-clipboard', function( e ) {
			e.preventDefault();
		});


		$( 'body' ).on( 'click', '.tinv-wishlist .tinv-overlay, .tinv-wishlist .tinv-close-modal, .tinv-wishlist .tinvwl_button_close', function( e ) {
			e.preventDefault();
			$( this ).parents( '.tinv-modal:first' ).removeClass( 'tinv-modal-open' );
			$( 'body' ).trigger( 'tinvwl_modal_closed', [ this ]);
		});

		$( 'body' ).on( 'click', '.tinv-wishlist .tinvwl-btn-onclick', function( e ) {
			var url = $( this ).data( 'url' );
			if ( url ) {
				e.preventDefault();
				window.location = $( this ).data( 'url' );
			}
		});

		var navigationButton = $( '.tinv-wishlist .navigation-button' );
		if ( navigationButton.length ) {
			navigationButton.each( function() {
				var navigationButtons = $( this ).find( '> li' );
				if ( 5 > navigationButtons.length ) {
					navigationButtons.parent().addClass( 'tinvwl-btns-count-' + navigationButtons.length );
				}
			});
		}

		$( '.tinv-login .showlogin' ).off( 'click' ).on( 'click', function( e ) {
			e.preventDefault();
			$( this ).closest( '.tinv-login' ).find( '.login' ).toggle();
		});

		$( '.tinv-wishlist table.tinvwl-table-manage-list tfoot td' ).each( function() {
			$( this ).toggle( !! $( this ).children().not( '.look_in' ).length || !! $( this ).children( '.look_in' ).children().length );
		});

	});
}( jQuery ) );

function showTooltip( elem, msg ) {
	elem.setAttribute( 'class', 'social social-clipboard tooltipped tooltipped-s' );
	elem.setAttribute( 'aria-label', msg );
}

function clearTooltip( e ) {
	e.currentTarget.setAttribute( 'class', 'social social-clipboard ' );
	e.currentTarget.removeAttribute( 'aria-label' );
}
