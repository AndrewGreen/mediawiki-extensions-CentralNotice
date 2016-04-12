( function ( mw ) {

	var realMwRequestIdleCallback = mw.requestIdleCallback,
		d;

	QUnit.module( 'ext.centralNotice.kvStore', QUnit.newMwEnvironment( {

		setup: function() {
			mw.requestIdleCallback = function ( callback ) {
				setTimeout( function () {
					callback( {
						didTimeout: false,
	
						// This value should be greater than REMAINING_IDLE_TIME_TO_STOP
						// in ext.centralNotice.kvStoreMaintenance.js
						timeRemaining: function () { return 10; }
					} );
					d.resolve();
				}, 1 );
			};
		},
		teardown: function () {
			var key, i = localStorage.length;
			// Loop backwards since removal affects the key index,
			// causing items to consistently be skipped over
			while ( i-- > 0 ) {
				key = localStorage.key( i );
				if ( /^CentralNoticeKV.+\|unittest/.test( key ) ) {
					localStorage.removeItem( key );
				}
			}

			mw.requestIdleCallback = realMwRequestIdleCallback;
		}
	} ) );

	QUnit.test( 'getItem', function ( assert ) {
		var kvStore = mw.centralNotice.kvStore,
			context = kvStore.contexts.GLOBAL;
		kvStore.setItem( 'unittest-New', 'x', context, 1 );
		kvStore.setItem( 'unittest-Old', 'x', context, -2 );

		assert.strictEqual( kvStore.getError(), null, 'no errors' );
		assert.strictEqual( kvStore.getItem( 'unittest-New', context ), 'x', 'retrieve valid item' );
		// Verify that expiry is verified at run-time regardless of kvStoreMaintenance
		assert.strictEqual( kvStore.getItem( 'unittest-Old', context ), null, 'ignore expired item' );
	} );

	QUnit.test( 'maintenance', function ( assert ) {
		var kvStore = mw.centralNotice.kvStore,
			context = kvStore.contexts.GLOBAL;
			done = assert.async();

		assert.expect( 7 );
		d = $.Deferred();

		kvStore.setItem( 'unittest-New', 'x', context, 1 );

		// TTL for this item must be less than 0 and greater than
		// the equivalent value in days of 0 - LEEWAY_FOR_REMOVAL (in
		// ext.centralNotice.kvStoreMaintenance.js). This tests an item that
		// should be treated as expired by kvStore but left alone by
		// kvStoreMaintenance.
		kvStore.setItem( 'unittest-Old', 'x', context, -0.5 );
		kvStore.setItem( 'unittest-Older', 'x', context, -2 );

		assert.ok(
			localStorage.getItem( 'CentralNoticeKV|global|unittest-New' ),
			'item "New" found in storage'
		);
		assert.ok(
			localStorage.getItem( 'CentralNoticeKV|global|unittest-Old' ),
			'item "Old" found in storage'
		);
		assert.ok(
			localStorage.getItem( 'CentralNoticeKV|global|unittest-Older' ),
			'item "Older" found in storage'
		);

		mw.centralNotice.kvStoreMaintenance.removeExpiredItemsWhenIdle();

		d.then( function () {
			assert.strictEqual( kvStore.getItem( 'unittest-Old', context ), null,
				'ignore expired "Old" item' );
	
			assert.notEqual(
				localStorage.getItem( 'CentralNoticeKV|global|unittest-New' ),
				null,
				'item "New" kept in storage'
			);
			assert.notEqual(
				localStorage.getItem( 'CentralNoticeKV|global|unittest-Old' ),
				null,
				'item "Old" kept in storage'
			);
			assert.strictEqual(
				localStorage.getItem( 'CentralNoticeKV|global|unittest-Older' ),
				null,
				'item "Older" removed from storage'
			);

			done();
		} );
	} );

}( mediaWiki ) );
