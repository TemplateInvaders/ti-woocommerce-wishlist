// Initialize a single guest secret before the first mutation, including virgin tabs.
( function( $ ) {
	$.tinvwlGuestNonceRequest = function( settings ) {
		var deferred = $.Deferred(), started = false, timer;
		function send( headers ) {
			return $.ajax( $.extend( {}, settings, { headers: $.extend( {}, settings.headers, headers ) } ) )
				.done( function() { deferred.resolveWith( this, arguments ); } )
				.fail( function() { deferred.rejectWith( this, arguments ); } );
		}
		function fallback() {
			if ( started ) { return; }
			started = true;
			window.clearTimeout( timer );
			if ( window.navigator.locks && window.navigator.locks.request ) {
				window.navigator.locks.request( 'tinvwl-guest-bootstrap', function() {
					return new window.Promise( function( resolve ) {
						send( { 'X-TInvWL-Guest-Locked': '1' } ).always( function() { resolve(); } );
					} );
				} ).catch( function() { send( {} ); } );
			} else {
				// An existing HttpOnly cookie still works. A fresh guest fails safely
				// if neither atomic browser storage nor a cross-tab mutex is available.
				send( {} );
			}
		}
		try {
			var crypto = window.crypto || window.msCrypto;
			if ( ! window.indexedDB || ! crypto || ! crypto.getRandomValues ) { fallback(); return deferred.promise(); }
			var open = window.indexedDB.open( 'tinvwl_guest_bootstrap', 1 );
			timer = window.setTimeout( fallback, 2000 );
			open.onupgradeneeded = function() { open.result.createObjectStore( 'identity' ); };
			open.onerror = open.onblocked = fallback;
			open.onsuccess = function() {
				var db = open.result;
				if ( started ) { db.close(); return; }
				try {
					var transaction = db.transaction( 'identity', 'readwrite' ), store = transaction.objectStore( 'identity' ),
						read = store.get( 'session' ), value;
					read.onsuccess = function() {
						value = read.result;
						if ( ! value || 'string' !== typeof value.secret || ! /^[a-f0-9]{64}$/.test( value.secret ) || 'number' !== typeof value.expires || ! isFinite( value.expires ) || value.expires <= Date.now() ) {
							var bytes = crypto.getRandomValues( new Uint8Array( 32 ) ), secret = '';
							for ( var i = 0; i < bytes.length; i++ ) { secret += ( '0' + bytes[i].toString( 16 ) ).slice( -2 ); }
							value = { secret: secret, expires: Date.now() + 86400000 };
							store.put( value, 'session' );
						}
					};
					transaction.oncomplete = function() {
						db.close();
						if ( started ) { return; }
						started = true;
						window.clearTimeout( timer );
						send( { 'X-TInvWL-Guest-Bootstrap': value.secret } );
					};
					transaction.onabort = transaction.onerror = function() { db.close(); fallback(); };
				} catch ( error ) { db.close(); fallback(); }
			};
		} catch ( error ) { fallback(); }
		return deferred.promise();
	};
}( jQuery ) );
