/**
 * Module for maintenance of items in kvStore. During idle time, it checks the
 * expiry times of items and removes those that expired a specified "leeway"
 * time ago.
 *
 * This module provides an API at mw.centralNotice.kvStoreMaintenance.
 */
( function ( $, mw ) {
	var	now = Math.round( ( new Date() ).getTime() / 1000 ),
		cn,

		// Regex to find kvStore localStorage keys. Must correspond with PREFIX
		// in ext.centralNotice.kvStore.js.
		PREFIX_REGEX = /^CentralNoticeKV/,

		// Time past expiry before actually removing items: 1 day (in seconds).
		// (This should prevent race conditions among browser tabs.)
		LEEWAY_FOR_REMOVAL = 86400;

	function makeRemoveExpiredFunction( length, d ) {
		// We don't know how far we'll get before the user navigates away. Start at a random index
		// and wrap around. This makes checking all items eventually more likely, even if there
		// are many keys and/or each page view is very short.
		var start = Math.floor( Math.random() * length ),
			wrapped = false,
			i = null;
		function next() {
			if ( i === null ) {
				i = start;
			} else {
				// Iterate backwards because removing items changes the key index. Otherwise we'd
				// consistently miss keys. Example:
				//
				//     keys = [ foo, bar, baz ]
				//     start = i = random() // 1
				//     remove( i );
				//     keys = [ foo, baz ]
				//
				// To avoid an infinite loop, we stop if we're back at the start. But, the start
				// must be moved along after each removal as otherwise we'd still some keys.
				//
				//     keys = [ foo, bar, baz, quux ]
				//     start = i = random() // 1
				//     remove( from i to 0 )
				//     keys = [ baz, quux ]
				//     i = length - 1 // Wrap around
				//     remove( from i to 1 )
				//     keys = [ baz ] // missed!
				//
				// Note: This is by no means guruanteed since users can close the page, and keys
				// may be missed due to races with other tabs. But those have their own removal
				// process at least and that's why we start at a random index.
				i--;
				if ( i < 0 ) {
					// Wrap around, continue from the end
					i = length - 1;
					wrapped = true;
				}
				if ( wrapped && ( i === start || i <= 0 ) ) {
					// Complete
					i = null;
				}
			}
			return i;
		}
		return function removeExpired( deadline ) {
			var index, key, rawValue, value;
			while ( deadline.timeRemaining() > 5 ) {
				index = next();
				if ( index === null ) {
					// Reached end
					break;
				}
				key = localStorage.key( index );

				// Recheck key existence. While JS execution is single-threaded, storage is
				// shared across multiple browsing contexts. It may race with other tabs.
				// Operate only on localStorage items used by the kvStore.
				if ( key === null || !PREFIX_REGEX.test( key ) ) {
					continue;
				}

				try {
					rawValue = localStorage.getItem( key );
				} catch ( e ) {
					return;
				}

				// The item might have been removed already
				if ( rawValue === null ) {
					continue;
				}

				try {
					value = JSON.parse( rawValue );
				} catch ( e ) {
					// Remove any unparseable items and maybe set an error
					localStorage.removeItem( key );
					if ( !wrapped ) {
						start--;
					}

					if ( cn.kvStore ) {
						cn.kvStore.setMaintenanceError( key );
					}

					continue;
				}

				if ( !value.expiry ||
					( value.expiry + LEEWAY_FOR_REMOVAL ) < now ) {

					localStorage.removeItem( key );
					if ( !wrapped ) {
						start--;
					}
				}
			}
			if ( index !== null ) {
				// Time's up, continue later
				mw.requestIdleCallback( removeExpired );
			} else {
				d.resolve();
			}
		};
	}

	// Don't assume mw.centralNotice has or hasn't been initialized
	mw.centralNotice = cn = ( mw.centralNotice || {} );

	/**
	 * Public API
	 */
	cn.kvStoreMaintenance = {

		/**
		 * Schedule the removal of expired KVStore items.
		 *
		 * @return {jQuery.Promise}
		 */
		removeExpiredItemsWhenIdle: function () {
			var d = $.Deferred();
			try {
				if ( !window.localStorage || !localStorage.length ) {
					return d.resolve();
				}
			} catch ( e ) {
				return d.resolve();
			}

			// Schedule
			mw.requestIdleCallback( makeRemoveExpiredFunction( localStorage.length, d ) );

			return d.promise();
		}
	};

} )( jQuery, mediaWiki );
