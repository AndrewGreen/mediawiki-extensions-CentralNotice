<?php
/**
 * General hook definitions
 *
 * This file is part of the CentralNotice Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:CentralNotice
 *
 * @file
 * @ingroup Extensions
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

global $wgExtensionFunctions, $wgHooks;

$wgExtensionFunctions[] = 'efCentralNoticeSetup';
$wgHooks[ 'LoadExtensionSchemaUpdates' ][ ] = 'CNDatabasePatcher::applyUpdates';
$wgHooks[ 'SkinTemplateNavigation::SpecialPage' ][ ] = array( 'CentralNotice::addNavigationTabs' );

/**
 * Load all the classes, register special pages, etc. Called through wgExtensionFunctions.
 */
function efCentralNoticeSetup() {
	global $wgHooks, $wgNoticeInfrastructure, $wgAutoloadClasses, $wgSpecialPages,
		   $wgCentralNoticeLoader, $wgSpecialPageGroups, $wgCentralPagePath, $wgScript,
		   $wgNoticeUseTranslateExtension, $wgAPIModules, $wgAPIListModules,
		   $wgAvailableRights, $wgGroupPermissions;

	// If $wgCentralPagePath hasn't been set, set it to the local script path.
	// We do this here since $wgScript isn't set until after LocalSettings.php loads.
	if ( $wgCentralPagePath === false ) {
		$wgCentralPagePath = $wgScript;
	}

	$dir = __DIR__ . '/';
	$specialDir = $dir . 'special/';
	$apiDir = $dir . 'api/';
	$includeDir = $dir . 'includes/';
	$htmlFormDir = $includeDir . '/HtmlFormElements/';

	// Register files
	$wgAutoloadClasses[ 'CentralNotice' ] = $specialDir . 'SpecialCentralNotice.php';
	$wgAutoloadClasses[ 'CentralNoticeBannerLogPager' ] = $dir . 'CentralNoticeBannerLogPager.php';
	$wgAutoloadClasses[ 'CentralNoticeCampaignLogPager' ] = $dir . 'CentralNoticeCampaignLogPager.php';
	$wgAutoloadClasses[ 'CentralNoticePageLogPager' ] = $dir . 'CentralNoticePageLogPager.php';
	$wgAutoloadClasses[ 'CentralNoticePager' ] = $dir . 'CentralNoticePager.php';
	$wgAutoloadClasses[ 'SpecialBannerAllocation' ] = $specialDir . 'SpecialBannerAllocation.php';
	$wgAutoloadClasses[ 'SpecialBannerLoader' ] = $specialDir . 'SpecialBannerLoader.php';
	$wgAutoloadClasses[ 'SpecialBannerRandom' ] = $specialDir . 'SpecialBannerRandom.php';
	$wgAutoloadClasses[ 'SpecialCentralNoticeBanners' ] = $specialDir . 'SpecialCentralNoticeBanners.php';
	$wgAutoloadClasses[ 'SpecialCentralNoticeLogs' ] = $specialDir . 'SpecialCentralNoticeLogs.php';
	$wgAutoloadClasses[ 'SpecialGlobalAllocation' ] = $specialDir . 'SpecialGlobalAllocation.php';
	$wgAutoloadClasses[ 'SpecialNoticeTemplate' ] = $specialDir . 'SpecialNoticeTemplate.php';
	$wgAutoloadClasses[ 'SpecialRecordImpression' ] = $specialDir . 'SpecialRecordImpression.php';
	$wgAutoloadClasses[ 'SpecialHideBanners' ] = $specialDir . 'SpecialHideBanners.php';
	$wgAutoloadClasses[ 'SpecialCNReporter' ] = $specialDir . 'SpecialCNReporter.php';

	$wgAutoloadClasses[ 'BannerLoaderException' ] = $specialDir . 'SpecialBannerLoader.php';

	$wgAutoloadClasses[ 'Banner' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerAllocationCalculator' ] = $includeDir . 'BannerAllocationCalculator.php';
	$wgAutoloadClasses[ 'BannerDataException' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerContentException' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerExistenceException' ] = $includeDir . 'Banner.php';
	$wgAutoloadClasses[ 'BannerMessage' ] = $includeDir . 'BannerMessage.php';
	$wgAutoloadClasses[ 'BannerMessageGroup' ] = $includeDir . 'BannerMessageGroup.php';
	$wgAutoloadClasses[ 'BannerChooser' ] = $includeDir . 'BannerChooser.php';
	$wgAutoloadClasses[ 'BannerRenderer' ] = $includeDir . 'BannerRenderer.php';
	$wgAutoloadClasses[ 'BannerChoiceDataProvider' ] = $includeDir . 'BannerChoiceDataProvider.php';
	$wgAutoloadClasses[ 'CNBannerChoiceDataResourceLoaderModule' ] = $includeDir . 'CNBannerChoiceDataResourceLoaderModule.php';
	$wgAutoloadClasses[ 'Campaign' ] = $includeDir . 'Campaign.php';
	$wgAutoloadClasses[ 'CampaignLog' ] = $includeDir . 'CampaignLog.php';
	$wgAutoloadClasses[ 'CNBannerPager' ] = $includeDir . 'CNBannerPager.php';
	$wgAutoloadClasses[ 'CNCampaignPager' ] = $includeDir . 'CNCampaignPager.php';
	$wgAutoloadClasses[ 'CNDeviceTarget' ] = $includeDir . 'CNDeviceTarget.php';
	$wgAutoloadClasses[ 'GeoTarget' ] = $includeDir . 'GeoTarget.php';
	$wgAutoloadClasses[ 'IBannerMixin' ] = $includeDir . 'IBannerMixin.php';
	$wgAutoloadClasses[ 'AllocationContext' ] = $includeDir . 'AllocationContext.php';
	$wgAutoloadClasses[ 'MixinController' ] = $includeDir . 'MixinController.php';

	$wgAutoloadClasses[ 'HTMLCentralNoticeBanner' ] = $htmlFormDir . 'HTMLCentralNoticeBanner.php';
	$wgAutoloadClasses[ 'HTMLCentralNoticeBannerMessage' ] = $htmlFormDir . 'HTMLCentralNoticeBannerMessage.php';

	$wgAutoloadClasses[ 'CNDatabasePatcher' ] = $dir . 'patches/CNDatabasePatcher.php';

	$wgAutoloadClasses[ 'ApiCentralNoticeAllocationBase' ] = $apiDir . 'ApiCentralNoticeAllocationBase.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeAllocations' ] = $apiDir . 'ApiCentralNoticeAllocations.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeBannerChoiceData' ] = $apiDir . 'ApiCentralNoticeBannerChoiceData.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeQueryCampaign' ] = $apiDir . 'ApiCentralNoticeQueryCampaign.php';
	$wgAutoloadClasses[ 'ApiCentralNoticeLogs' ] = $apiDir . 'ApiCentralNoticeLogs.php';
	$wgAutoloadClasses[ 'TemplatePager' ] = $dir . 'TemplatePager.php';

	$wgAutoloadClasses[ 'CNDatabase' ] = $includeDir . 'CNDatabase.php';
	$wgAPIModules[ 'centralnoticebannerchoicedata' ] = 'ApiCentralNoticeBannerChoiceData';
	$wgAPIModules[ 'centralnoticeallocations' ] = 'ApiCentralNoticeAllocations';
	$wgAPIModules[ 'centralnoticequerycampaign' ] = 'ApiCentralNoticeQueryCampaign';
	$wgAPIListModules[ 'centralnoticelogs' ] = 'ApiCentralNoticeLogs';

	// Register hooks
	// TODO: replace ef- global functions with static methods in CentralNoticeHooks
	$wgHooks['ResourceLoaderTestModules'][] = 'efCentralNoticeResourceLoaderTestModules';
	$wgHooks['UnitTestsList'][] = 'efCentralNoticeUnitTests';

	// If CentralNotice banners should be shown on this wiki, load the components we need for
	// showing banners. For discussion of banner loading strategies, see
	// http://wikitech.wikimedia.org/view/CentralNotice/Optimizing_banner_loading
	if ( $wgCentralNoticeLoader ) {
		$wgHooks[ 'MakeGlobalVariablesScript' ][ ] = 'efCentralNoticeDefaults';
		$wgHooks[ 'BeforePageDisplay' ][ ] = 'efCentralNoticeLoader';
		$wgHooks[ 'SiteNoticeAfter' ][ ] = 'efCentralNoticeDisplay';
		$wgHooks[ 'ResourceLoaderGetConfigVars' ][] = 'efResourceLoaderGetConfigVars';
		// Register mobile modules
		$wgHooks[ 'SkinMinervaDefaultModules' ][] = 'onSkinMinervaDefaultModules';
	}

	// Tell the UserMerge extension where we store user ids
	$wgHooks[ 'UserMergeAccountFields' ][] = function( &$updateFields ) {
		// array( tableName, idField, textField )
		$updateFields[] = array( 'cn_notice_log', 'notlog_user_id' );
		$updateFields[] = array( 'cn_template_log', 'tmplog_user_id' );
		return true;
	};

	// Register special pages
	$wgSpecialPages[ 'BannerLoader' ] = 'SpecialBannerLoader';
	$wgSpecialPages[ 'BannerRandom' ] = 'SpecialBannerRandom';
	$wgSpecialPages[ 'RecordImpression' ] = 'SpecialRecordImpression';
	$wgSpecialPages[ 'HideBanners' ] = 'SpecialHideBanners';
	$wgSpecialPages[ 'CNReporter' ] = 'SpecialCNReporter';

	// If this is the wiki that hosts the management interface, load further components
	if ( $wgNoticeInfrastructure ) {
		if ( $wgNoticeUseTranslateExtension ) {
			$wgHooks[ 'TranslatePostInitGroups' ][ ] = 'BannerMessageGroup::registerGroupHook';
			$wgHooks[ 'TranslateEventMessageGroupStateChange' ][] = 'BannerMessageGroup::updateBannerGroupStateHook';
		}

		$wgSpecialPages[ 'CentralNotice' ] = 'CentralNotice';
		$wgSpecialPageGroups[ 'CentralNotice' ] = 'wiki'; // Wiki data and tools
		$wgSpecialPages[ 'NoticeTemplate' ] = 'SpecialNoticeTemplate';
		$wgSpecialPages[ 'GlobalAllocation' ] = 'SpecialGlobalAllocation';
		$wgSpecialPages[ 'BannerAllocation' ] = 'SpecialBannerAllocation';
		$wgSpecialPages[ 'CentralNoticeLogs' ] = 'SpecialCentralNoticeLogs';
		$wgSpecialPages[ 'CentralNoticeBanners'] = 'SpecialCentralNoticeBanners';

		// Register user rights for editing
		$wgAvailableRights[] = 'centralnotice-admin';
		$wgGroupPermissions[ 'sysop' ][ 'centralnotice-admin' ] = true; // Only sysops can make change
	}
}

/**
 * CanonicalNamespaces hook; adds the CentralNotice namespaces if this is an infrastructure
 * wiki, and if CentralNotice is configured to use the Translate extension.
 *
 * We do this here because there are initialization problems wrt Translate and MW core if
 * the language object is initialized before all namespaces are registered -- which would
 * be the case if we just used the wgExtensionFunctions hook system.
 *
 * @param array $namespaces Modifiable list of namespaces -- similar to $wgExtraNamespaces
 *
 * @return bool True if the hook completed successfully.
 */
$wgHooks[ 'CanonicalNamespaces' ][ ] = function( &$namespaces ) {
	global $wgExtraNamespaces, $wgNamespacesWithSubpages, $wgTranslateMessageNamespaces;
	global $wgNoticeUseTranslateExtension, $wgNoticeInfrastructure;

	if ( $wgNoticeInfrastructure && $wgNoticeUseTranslateExtension ) {
		$wgExtraNamespaces[NS_CN_BANNER] = 'CNBanner';
		$wgTranslateMessageNamespaces[] = NS_CN_BANNER;

		$wgExtraNamespaces[NS_CN_BANNER_TALK] = 'CNBanner_talk';
		$wgNamespacesWithSubpages[NS_CN_BANNER_TALK] = true;

		$namespaces[NS_CN_BANNER] = 'CNBanner';
		$namespaces[NS_CN_BANNER_TALK] = 'CNBanner_talk';
	}

	return true;
};

/**
 * BeforePageDisplay hook handler
 * This function adds the banner controller and geoIP lookup to the page
 *
 * @param $out  OutputPage
 * @param $skin Skin
 * @return bool
 */
function efCentralNoticeLoader( $out, $skin ) {
	global $wgCentralHost, $wgCentralGeoScriptURL, $wgServer;

	// Insert the geoIP lookup
	if ( is_string( $wgCentralGeoScriptURL ) ) {
		$out->addHeadItem( 'geoip', '<script src="' . htmlspecialchars( $wgCentralGeoScriptURL ) . '"></script>' );
	}

	// Insert DNS prefetch for banner loading
	if ( $wgCentralHost && $wgCentralHost !== $wgServer ) {
		$out->addHeadItem( 'dns-prefetch', '<link rel="dns-prefetch" href="' . htmlspecialchars( $wgCentralHost ) . '" />' );
	}
	// Insert the banner controller
	$out->addModules( 'ext.centralNotice.bannerController' );
	return true;
}

/**
 * MakeGlobalVariablesScript hook handler
 * This function sets the psuedo-global Javascript variables that are used by CentralNotice
 *
 * @param $vars array
 * @return bool
 */
function efCentralNoticeDefaults( &$vars ) {
	// Using global $wgUser for compatibility with 1.18
	global $wgNoticeProject, $wgUser, $wgMemc;

	// FIXME Is this no longer used anywhere in JS following the switch to
	// client-side banner selection? If so, remove it.
	$vars[ 'wgNoticeProject' ] = $wgNoticeProject;

	// Output the user's registration date, total edit count, and past year's edit count.
	// This is useful for banners that need to be targeted to specific types of users.
	// Only do this for logged-in users, keeping anonymous user output equal (for Squid-cache).
	if ( $wgUser->isLoggedIn() ) {

		$cacheKey = wfMemcKey( 'CentralNotice', 'UserData', $wgUser->getId() );
		$userData = $wgMemc->get( $cacheKey );

		// Cached?
		if ( !$userData ) {
			// Exclude bots
			if ( $wgUser->isAllowed( 'bot' ) ) {
				$userData = false;
			} else {
				$userData = array();

				// Add the user's registration date (MediaWiki timestamp)
				$registrationDate = $wgUser->getRegistration() ? $wgUser->getRegistration() : 0;
				$userData[ 'registration' ] = $registrationDate;

				// Make sure UserDailyContribs extension is installed.
				if ( function_exists( 'getUserEditCountSince' ) ) {

					// Add the user's total edit count
					if ( $wgUser->getEditCount() == null ) {
						$userData[ 'editcount' ] = 0;
					} else {
						$userData[ 'editcount' ] = intval( $wgUser->getEditCount() );
					}

					// Add the user's edit count for the past year
					$userData[ 'pastyearseditcount' ] = getUserEditCountSince(
						time() - ( 365 * 24 * 3600 ), // from a year ago
						$wgUser,
						time() // until now
					);
				}
			}

			// Cache the data for 7 days
			$wgMemc->set( $cacheKey, $userData, 7 * 86400 );
		}

		// Set the variable that will be output to the page
		$vars[ 'wgNoticeUserData' ] = $userData;

	}

	return true;
}

/**
 * SiteNoticeAfter hook handler
 * This function outputs the siteNotice div that the banners are loaded into.
 *
 * @param $notice string
 * @return bool
 */
function efCentralNoticeDisplay( &$notice ) {
	// Ensure that the div including #siteNotice is actually included!
	$notice = "<!-- CentralNotice -->$notice";
	return true;
}

/**
 * ResourceLoaderGetConfigVars hook handler
 * Send php config vars to js via ResourceLoader
 *
 * @param &$vars: variables to be added to the output of the startup module
 * @return bool
 */
function efResourceLoaderGetConfigVars( &$vars ) {
	global $wgNoticeFundraisingUrl, $wgCentralPagePath, $wgContLang, $wgNoticeXXCountries,
		   $wgNoticeInfrastructure, $wgNoticeCloseButton, $wgCentralBannerDispatcher,
		   $wgCentralBannerRecorder, $wgNoticeNumberOfBuckets, $wgNoticeBucketExpiry,
		   $wgNoticeNumberOfControllerBuckets, $wgNoticeCookieDurations, $wgScript,
		   $wgNoticeHideUrls, $wgNoticeOldCookieApocalypse;

	// Making these calls too soon will causes issues with the namespace localisation cache. This seems
	// to be just right. We require them at all because MW will 302 page requests made to non localised
	// namespaces which results in wasteful extra calls.
	if ( !$wgCentralBannerDispatcher ) {
		$wgCentralBannerDispatcher = SpecialPage::getTitleFor( 'BannerRandom' )->getLocalUrl();
	}
	if ( !$wgCentralBannerRecorder ) {
		$wgCentralBannerRecorder = SpecialPage::getTitleFor( 'RecordImpression' )->getLocalUrl();
	}

	// Mangle infrastructure URLs for mobile use -- this should always be last.
	if ( class_exists( 'MobileContext' ) ) {
		// Where possible; make things mobile friendly
		$mc = MobileContext::singleton();
		if ( $mc->shouldDisplayMobileView() ) {
			$wgNoticeFundraisingUrl = $mc->getMobileUrl( $wgNoticeFundraisingUrl );
			$wgCentralPagePath = $mc->getMobileUrl( $wgCentralPagePath );
			$wgCentralBannerDispatcher = $mc->getMobileUrl( $wgCentralBannerDispatcher );
			$wgCentralBannerRecorder = $mc->getMobileUrl( $wgCentralBannerRecorder );
		}
	}

	$vars[ 'wgNoticeFundraisingUrl' ] = $wgNoticeFundraisingUrl;
	$vars[ 'wgCentralPagePath' ] = $wgCentralPagePath;
	$vars[ 'wgCentralBannerDispatcher' ] = $wgCentralBannerDispatcher;
	$vars[ 'wgCentralBannerRecorder' ] = $wgCentralBannerRecorder;

	$vars[ 'wgNoticeXXCountries' ] = $wgNoticeXXCountries;
	$vars[ 'wgNoticeNumberOfBuckets' ] = $wgNoticeNumberOfBuckets;
	$vars[ 'wgNoticeBucketExpiry' ] = $wgNoticeBucketExpiry;
	$vars[ 'wgNoticeNumberOfControllerBuckets' ] = $wgNoticeNumberOfControllerBuckets;
	$vars[ 'wgNoticeCookieDurations' ] = $wgNoticeCookieDurations;
	$vars[ 'wgNoticeHideUrls' ] = $wgNoticeHideUrls;
	$vars[ 'wgNoticeOldCookieApocalypse' ] = $wgNoticeOldCookieApocalypse;

	if ( $wgNoticeInfrastructure ) {
		$vars[ 'wgNoticeCloseButton' ] = $wgNoticeCloseButton;
	}
	return true;
}

/**
 * UnitTestsList hook handler
 *
 * @param $files array
 * @return bool
 */
function efCentralNoticeUnitTests( &$files ) {
	global $wgAutoloadClasses;

	$wgAutoloadClasses['CentralNoticeTestFixtures'] = __DIR__ . '/tests/CentralNoticeTestFixtures.php';
	$wgAutoloadClasses['ComparisonUtil'] = __DIR__ . '/tests/ComparisonUtil.php';

	$files[ ] = __DIR__ . '/tests/ApiAllocationsTest.php';
	$files[ ] = __DIR__ . '/tests/BannerChooserTest.php';
	$files[ ] = __DIR__ . '/tests/ApiCentralNoticeBannerChoiceDataTest.php';
	$files[ ] = __DIR__ . '/tests/CentralNoticeTest.php';
	$files[ ] = __DIR__ . '/tests/BannerAllocationCalculatorTest.php';
	$files[ ] = __DIR__ . '/tests/BannerChoiceDataProviderTest.php';
	$files[ ] = __DIR__ . '/tests/BannerTest.php';
	return true;
}

/**
 * Place CentralNotice ResourceLoader modules onto mobile pages.
 * ResourceLoaderTestModules hook handler
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
 *
 * @param array $testModules
 * @param ResourceLoader $resourceLoader
 * @return bool
 */
function efCentralNoticeResourceLoaderTestModules( array &$testModules,
	ResourceLoader $resourceLoader
) {
	global $wgResourceModules;

	$testModuleBoilerplate = array(
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'CentralNotice',
	);

	// TODO: Something similar should be provided by core.
	// find test files for every RL module
	$prefix = 'ext.centralNotice';
	foreach ( $wgResourceModules as $key => $module ) {
		if ( substr( $key, 0, strlen( $prefix ) ) === $prefix && isset( $module['scripts'] ) ) {
			$testFiles = array();
			foreach ( ((array) $module['scripts'] ) as $script ) {
				$testFile = 'tests/qunit/' . $key . '/' . basename( $script );
				$testFile = preg_replace( '/.js$/', '.tests.js', $testFile );
				// if a test file exists for a given JS file, add it
				if ( file_exists( __DIR__ . '/' . $testFile ) ) {
					$testFiles[] = $testFile;
				}
			}
			// if test files exist for given module, create a corresponding test module
			if ( count( $testFiles ) > 0 ) {
				$testModules['qunit']["$key.tests"] = $testModuleBoilerplate + array(
					'dependencies' => array( $key ),
					'scripts' => $testFiles,
				);
			}
		}
	}

	return true;
}

/**
 * EnableMobileModules callback for placing the CN resourceloader
 * modules onto mobile pages.
 *
 * @param Skin $skin
 * @param array $modules
 *
 * @return bool
 */
function onSkinMinervaDefaultModules( Skin $skin, array &$modules ) {
	$modules[ 'centralnotice' ] = array(
		'ext.centralNotice.bannerController.mobiledevice',
		'ext.centralNotice.bannerController.mobile',
		'ext.centralNotice.bannerController.lib',
		'ext.centralNotice.bannerChoiceData',
	);

	return true;
}
