( function( mw, $ ) {
	'use strict';

	var defaultCampaignData = {
		banners: [],
		bucket_count: 1,
		countries: [],
		geotargeted: 0,
		preferred: 1,
		throttle: 100
	}, defaultBannerData = {
		bucket: 0,
		devices: [ 'desktop' ],
		weight: 25,
		display_anon: true,
		display_account: true
	};

	QUnit.module( 'ext.centralNotice.bannerController.lib', QUnit.newMwEnvironment( {
		setup: function() {
			mw.centralNotice.data.country = 'XX';
			mw.centralNotice.data.device = 'desktop';
			mw.centralNotice.data.anonymous = true;
		}
	} ) );

	QUnit.asyncTest( 'allocations test cases', function( assert ) {
		$.ajax( {
			url: mw.config.get( 'wgExtensionAssetsPath' )
				+ '/CentralNotice/tests/data/AllocationsFixtures.json'
		} ).done( function( testCases ) {
			// Declare the number of test cases
			assert.ok( testCases.length );
			QUnit.expect( testCases.length + 1 );

			$.each( testCases, function( index, testCaseInputs ) {
				var testCase = testCaseInputs[0],
					lib = mw.cnBannerControllerLib,
					choices,
					choice,
					i,
					allocatedBanner;

				// Flesh out choice data with some default values
				// BOOM on priority case
				choices = $.map( testCase.choices, function( campaign, index ) {
					return $.extend(
						{ name: index },
						defaultCampaignData,
						campaign,
						{
							banners: $.map( campaign.banners, function( banner ) {
								return $.extend( {}, defaultBannerData, banner );
							} )
						} );
				} );

				// Set per-campaign buckets to 0 for all campaigns
				// FIXME Allow testing of different buckets
				lib.bucketsByCampaign = {};
				for ( i = 0; i < choices.length; i++ ) {
					choice = choices[i];
					lib.bucketsByCampaign[choice.name] = { val: 0 };
				}

				// TODO: would like to declare individual tests here, but I
				// haven't been able to make that work, yet.
				lib.setChoiceData( choices );
				lib.filterChoiceData();
				lib.makePossibleBanners();
				lib.calculateBannerAllocations();

				// TODO: the errors will not reveal anything useful about
				// which case this is, and what happened.  So we throw
				// exceptions manually.  The horror!
				try {
					if ( lib.possibleBanners.length !== Object.keys( testCase.allocations ).length ) {
						throw 'Wrong number of banners allocated in "' + testCase.title + '".';
					}
					for ( i = 0; i < lib.possibleBanners.length; i++ ) {
						allocatedBanner = lib.possibleBanners[i];
						if ( Math.abs( allocatedBanner.allocation - testCase.allocations[allocatedBanner.name] ) > 0.001 ) {
							throw 'Banner ' + allocatedBanner.name + ' was misallocated in "' + testCase.title + '".';
						}
					}
				} catch ( error ) {
					assert.ok( false, error
						+ " expected: " + QUnit.jsDump.parse( testCase.allocations )
						+ ", actual: " + QUnit.jsDump.parse( lib.possibleBanners )
					);
					return;
				}
				assert.ok( true, 'Allocations match in "' + testCase.title + '"' );
			} );

			QUnit.start();
		} );
	} );

	// TODO: chooser tests

} ( mediaWiki, jQuery ) );
