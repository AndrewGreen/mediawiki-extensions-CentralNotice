<?php

class Campaign {

	protected $id = null;
	protected $name = null;

	/** @var MWTimestamp Start datetime of campaign  */
	protected $start = null;

	/** @var MWTimestamp End datetime of campaign */
	protected $end = null;

	/** @var int Priority level of the campaign, higher is more important */
	protected $priority = null;

	/** @var bool True if the campaign is enabled for showing */
	protected $enabled = null;

	/** @var bool True if the campaign is currently non editable  */
	protected $locked = null;

	/** @var bool True if there is geo-targeting data for ths campaign */
	protected $geotargeted = null;

	/** @var int The number of buckets in this campaign */
	protected $buckets = null;

	/**
	 * Construct a lazily loaded CentralNotice campaign object
	 *
	 * @param string|int $campaignIdentifier Either an ID or name for the campaign
	 */
	public function __construct( $campaignIdentifier ) {
		if ( is_int( $campaignIdentifier ) ) {
			$this->id = $campaignIdentifier;
		} else {
			$this->name = $campaignIdentifier;
		}
	}

	/**
	 * Get the unique numerical ID for this campaign
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return int
	 */
	public function getId() {
		if ( $this->id === null ) {
			$this->loadBasicSettings();
		}

		return $this->id;
	}

	/**
	 * Get the unique name for this campaign
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return string
	 */
	public function getName() {
		if ( $this->name === null ) {
			$this->loadBasicSettings();
		}

		return $this->name;
	}

	/**
	 * Get the start time for the campaign. Only applicable if the campaign is enabled.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return MWTimestamp
	 */
	public function getStartTime() {
		if ( $this->start === null ) {
			$this->loadBasicSettings();
		}

		return $this->start;
	}

	/**
	 * Get the end time for the campaign. Only applicable if the campaign is enabled.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return MWTimestamp
	 */
	public function getEndTime() {
		if ( $this->end === null ) {
			$this->loadBasicSettings();
		}

		return $this->end;
	}

	/**
	 * Get the priority level for this campaign. The larger this is the higher the priority is.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return int
	 */
	public function getPriority() {
		if ( $this->priority === null ) {
			$this->loadBasicSettings();
		}

		return $this->priority;
	}

	/**
	 * Returns the enabled/disabled status of the campaign.
	 *
	 * If a campaign is enabled it is eligible to be shown to users.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return bool
	 */
	public function isEnabled() {
		if ( $this->enabled === null ) {
			$this->loadBasicSettings();
		}

		return $this->enabled;
	}

	/**
	 * Returns the locked/unlocked status of the campaign. A locked campaign is not able to be
	 * edited until unlocked.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return bool
	 */
	public function isLocked() {
		if ( $this->locked === null ) {
			$this->loadBasicSettings();
		}

		return $this->priority;
	}

	/**
	 * Returned the geotargeted status of this campaign. Will be true if GeoIP information should
	 * be used to determine user eligibility.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return bool
	 */
	public function isGeotargeted() {
		if ( $this->geotargeted === null ) {
			$this->loadBasicSettings();
		}

		return $this->geotargeted;
	}

	/**
	 * Get the number of buckets in this campaign.
	 *
	 * @throws CampaignExistenceException If lazy loading failed.
	 * @return int
	 */
	public function getBuckets() {
		if ( $this->priority === null ) {
			$this->loadBasicSettings();
		}

		return $this->priority;
	}

	/**
	 * Load basic campaign settings from the database table cn_notices
	 *
	 * @throws CampaignExistenceException If the campaign doesn't exist
	 */
	protected function loadBasicSettings() {
		$db = CNDatabase::getDb();

		// What selector are we using?
		if ( $this->id !== null ) {
			$selector = array( 'not_id' => $this->id );
		} elseif ( $this->name !== null ) {
			$selector = array( 'not_name' => $this->name );
		} else {
			throw new CampaignExistenceException( "No valid database key available for campaign." );
		}

		// Get campaign info from database
		$row = $db->selectRow(
			array('notices' => 'cn_notices'),
			array(
				 'not_id',
				 'not_name',
				 'not_start',
				 'not_end',
				 'not_enabled',
				 'not_preferred',
				 'not_locked',
				 'not_geo',
				 'not_buckets',
			),
			$selector,
			__METHOD__
		);
		if ( $row ) {
			$this->start = new MWTimestamp( $row->not_start );
			$this->end = new MWTimestamp( $row->not_end );
			$this->enabled = (bool)$row->not_enabled;
			$this->priority = (int)$row->not_preferred;
			$this->locked = (bool)$row->not_locked;
			$this->geotargeted = (bool)$row->not_geo;
			$this->buckets = (int)$row->not_buckets;
		} else {
			throw new CampaignExistenceException(
				"Campaign could not be retrieved from database with id '{$this->id}' or name '{$this->name}'"
			);
		}
	}

	/**
	 * See if a given campaign exists in the database
	 *
	 * @param $campaignName string
	 *
	 * @return bool
	 */
	static function campaignExists( $campaignName ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		$eCampaignName = htmlspecialchars( $campaignName );
		return (bool)$dbr->selectRow( 'cn_notices', 'not_name', array( 'not_name' => $eCampaignName ) );
	}

	/**
	 * Returns a list of campaigns. May be filtered on optional constraints.
	 * By default returns only enabled and active campaigns in all projects, languages and
	 * countries.
	 *
	 * @param null|string $project  The name of the project, ie: 'wikipedia'; if null select all
	 *                              projects.
	 * @param null|string $language ISO language code, if null select all languages
	 * @param null|string $location ISO country code, if null select all campaigns
	 * @param null|date   $date     Campaigns must start before and end after this date
	 *                              If the parameter is null, it takes the current date/time
	 * @param bool        $enabled  If true, select only active campaigns. If false select all.
	 *
	 * @return array Array of campaign IDs that matched the filter.
	 */
	static function getCampaigns( $project = null, $language = null, $location = null, $date = null,
	                              $enabled = true ) {
		global $wgCentralDBname;

		$notices = array();

		// Database setup
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		// We will perform two queries (one geo-targeted, the other not) to
		// catch all notices. We do it bifurcated because otherwise the query
		// would be really funky (if even possible) to pass to the underlying
		// DB layer.

		// Therefore... construct the common components : cn_notices
		if ( $date ) {
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp( $date ) );
		} else {
			$encTimestamp = $dbr->addQuotes( $dbr->timestamp() );
		}

		$tables = array( 'notices' => 'cn_notices' );
		$conds = array(
			"not_start <= $encTimestamp",
			"not_end >= $encTimestamp",
		);

		if ( $enabled ) {
			$conds[ 'not_enabled' ] = 1;
		}

		// common components: cn_notice_projects
		if ( $project ) {
			$tables[ 'notice_projects' ] = 'cn_notice_projects';

			$conds[ ] = 'np_notice_id = notices.not_id';
			$conds[ 'np_project' ] = $project;
		}

		// common components: language
		if ( $language ) {
			$tables[ 'notice_languages' ] = 'cn_notice_languages';

			$conds[ ] = 'nl_notice_id = notices.not_id';
			$conds[ 'nl_language' ] = $language;
		}

		if ( $location ) {
			$conds['not_geo'] = 0;
		}

		// Pull the notice IDs of the non geotargeted campaigns
		$res = $dbr->select(
			$tables,
			'not_id',
			$conds,
			__METHOD__
		);
		foreach ( $res as $row ) {
			$notices[ ] = $row->not_id;
		}

		// If a location is passed, also pull geotargeted campaigns that match the location
		if ( $location ) {
			$tables[ 'notice_countries' ] = 'cn_notice_countries';

			$conds[ ] = 'nc_notice_id = notices.not_id';
			$conds[ 'nc_country' ] = $location;
			$conds[ 'not_geo' ] = 1;

			// Pull the notice IDs
			$res = $dbr->select(
				$tables,
				'not_id',
				$conds,
				__METHOD__
			);

			// Loop through result set and return ids
			foreach ( $res as $row ) {
				$notices[ ] = $row->not_id;
			}
		}

		return $notices;
	}

	/**
	 * Return settings for a campaign
	 *
	 * @param $campaignName string: The name of the campaign
	 *
	 * @return array|bool an array of settings or false if the campaign does not exist
	 */
	static function getCampaignSettings( $campaignName ) {
		global $wgCentralDBname;

		// Read from the master database to avoid concurrency problems
		$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );

		$campaign = array();

		// Get campaign info from database
		$row = $dbr->selectRow(
			array('notices' => 'cn_notices'),
			array(
				'not_id',
				'not_start',
				'not_end',
				'not_enabled',
				'not_preferred',
				'not_locked',
				'not_geo',
				'not_buckets',
			),
			array( 'not_name' => $campaignName ),
			__METHOD__
		);
		if ( $row ) {
			$campaign = array(
				'start'     => $row->not_start,
				'end'       => $row->not_end,
				'enabled'   => $row->not_enabled,
				'preferred' => $row->not_preferred,
				'locked'    => $row->not_locked,
				'geo'       => $row->not_geo,
				'buckets'   => $row->not_buckets,
			);
		} else {
			return false;
		}

		$projects = Campaign::getNoticeProjects( $campaignName );
		$languages = Campaign::getNoticeLanguages( $campaignName );
		$geo_countries = Campaign::getNoticeCountries( $campaignName );
		$campaign[ 'projects' ] = implode( ", ", $projects );
		$campaign[ 'languages' ] = implode( ", ", $languages );
		$campaign[ 'countries' ] = implode( ", ", $geo_countries );

		$bannersIn = Banner::getCampaignBanners( $row->not_id, true );
		$bannersOut = array();
		// All we want are the banner names, weights, and buckets
		foreach ( $bannersIn as $key => $row ) {
			$outKey = $bannersIn[ $key ][ 'name' ];
			$bannersOut[ $outKey ]['weight'] = $bannersIn[ $key ][ 'weight' ];
			$bannersOut[ $outKey ]['bucket'] = $bannersIn[ $key ][ 'bucket' ];
		}
		// Encode into a JSON string for storage
		$campaign[ 'banners' ] = FormatJson::encode( $bannersOut );

		return $campaign;
	}

	/**
	 * Get all campaign configurations as of timestamp $ts
	 *
	 * @return array of settings structs having the following properties:
	 *     id
	 *     name
	 *     enabled
	 *     projects: array of sister project names
	 *     languages: array of language codes
	 *     countries: array of country codes
	 *     preferred: campaign priority
	 *     geo: is geolocated?
	 *     buckets: number of buckets
	 *     banners: array of banner objects, as returned by getHistoricalBanner,
	 *       plus the following information from the parent campaign:
	 *         campaign: name of the campaign
	 *         campaign_z_index
	 *         campaign_num_buckets
	 */
	static function getHistoricalCampaigns( $ts ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$res = $dbr->select(
			"cn_notice_log",
			array(
				"log_id" => "MAX(notlog_id)",
			),
			array(
				"notlog_timestamp <= $ts",
				"notlog_end_start <= $ts",
				"notlog_end_end >= $ts",
			),
			__METHOD__,
			array(
				"GROUP BY" => "notlog_not_id",
			)
		);

		$banners = array();
		$campaigns = array();
		foreach ( $res as $row ) {
			$singleRes = $dbr->select(
				"cn_notice_log",
				array(
					"id" => "notlog_not_id",
					"name" => "notlog_not_name",
					"enabled" => "notlog_end_enabled",
					"projects" => "notlog_end_projects",
					"languages" => "notlog_end_languages",
					"countries" => "notlog_end_countries",
					"preferred" => "notlog_end_preferred",
					"geo" => "notlog_end_geo",
					"banners" => "notlog_end_banners",
					"buckets" => "notlog_end_buckets",
				),
				array(
					"notlog_id = {$row->log_id}",
				),
				__METHOD__
			);

			$campaign = $singleRes->fetchRow();
			if ( $campaign['enabled'] !== "1" ) {
				continue;
			}
			$campaign['projects'] = explode( ", ", $campaign['projects'] );
			$campaign['languages'] = explode( ", ", $campaign['languages'] );
			$campaign['countries'] = explode( ", ", $campaign['countries'] );
			if ( $campaign['banners'] === null ) {
				$campaign['banners'] = array();
			} else {
				$campaign['banners'] = FormatJson::decode( $campaign['banners'], true );
			}
			foreach ( $campaign['banners'] as $name => &$banner ) {
				$historical_banner = Banner::getHistoricalBanner( $name, $ts );

				if ( $historical_banner === null ) {
					//FIXME: crazy hacks
					$historical_banner = Banner::getBannerSettings( $name );
					$historical_banner['label'] = wfMessage( 'centralnotice-damaged-log', $name );
					$historical_banner['display_anon'] = $historical_banner['anon'];
					$historical_banner['display_account'] = $historical_banner['account'];
					$historical_banner['device'] = 'desktop';
				}
				$banner['name'] = $name;
				$banner['label'] = $name;

				$campaign_info = array(
					'campaign' => $campaign['name'],
					'campaign_z_index' => $campaign['preferred'],
					'campaign_num_buckets' => $campaign['buckets'],
				);

				$banner = array_merge( $banner, $campaign_info, $historical_banner );
			}

			$campaigns[] = $campaign;
		}
		return $campaigns;
	}

	/**
	 * Get all the campaigns in the database
	 *
	 * @return array an array of campaign names
	 */
	static function getAllCampaignNames() {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$res = $dbr->select( 'cn_notices', 'not_name', null, __METHOD__ );
		$notices = array();
		foreach ( $res as $row ) {
			$notices[ ] = $row->not_name;
		}
		return $notices;
	}

	/**
	 * Add a new campaign to the database
	 *
	 * @param $noticeName        string: Name of the campaign
	 * @param $enabled           int: Boolean setting, 0 or 1
	 * @param $startTs           string: Campaign start in UTC
	 * @param $projects          array: Targeted project types (wikipedia, wikibooks, etc.)
	 * @param $project_languages array: Targeted project languages (en, de, etc.)
	 * @param $geotargeted       int: Boolean setting, 0 or 1
	 * @param $geo_countries     array: Targeted countries
	 * @param $user              User adding the campaign
	 *
	 * @throws MWException
	 * @return bool|string True on success, string with message key for error
	 */
	static function addCampaign( $noticeName, $enabled, $startTs, $projects, $project_languages,
								 $geotargeted, $geo_countries, $user ) {
		$noticeName = trim( $noticeName );
		if ( Campaign::campaignExists( $noticeName ) ) {
			return 'centralnotice-notice-exists';
		} elseif ( empty( $projects ) ) {
			return 'centralnotice-no-project';
		} elseif ( empty( $project_languages ) ) {
			return 'centralnotice-no-language';
		}

		if ( !$geo_countries ) {
			$geo_countries = array();
		}

		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$dbw->begin();

		$endTime = strtotime( '+1 hour', wfTimestamp( TS_UNIX, $startTs ) );
		$endTs = wfTimestamp( TS_MW, $endTime );

		$dbw->insert( 'cn_notices',
			array( 'not_name'    => $noticeName,
				'not_enabled' => $enabled,
				'not_start'   => $dbw->timestamp( $startTs ),
				'not_end'     => $dbw->timestamp( $endTs ),
				'not_geo'     => $geotargeted
			)
		);
		$not_id = $dbw->insertId();

		if ( $not_id ) {
			// Do multi-row insert for campaign projects
			$insertArray = array();
			foreach ( $projects as $project ) {
				$insertArray[ ] = array( 'np_notice_id' => $not_id, 'np_project' => $project );
			}
			$dbw->insert( 'cn_notice_projects', $insertArray,
				__METHOD__, array( 'IGNORE' ) );

			// Do multi-row insert for campaign languages
			$insertArray = array();
			foreach ( $project_languages as $code ) {
				$insertArray[ ] = array( 'nl_notice_id' => $not_id, 'nl_language' => $code );
			}
			$dbw->insert( 'cn_notice_languages', $insertArray,
				__METHOD__, array( 'IGNORE' ) );

			if ( $geotargeted ) {
				// Do multi-row insert for campaign countries
				$insertArray = array();
				foreach ( $geo_countries as $code ) {
					$insertArray[ ] = array( 'nc_notice_id' => $not_id, 'nc_country' => $code );
				}
				$dbw->insert( 'cn_notice_countries', $insertArray,
					__METHOD__, array( 'IGNORE' ) );
			}

			$dbw->commit();

			// Log the creation of the campaign
			$beginSettings = array();
			$endSettings = array(
				'projects'  => implode( ", ", $projects ),
				'languages' => implode( ", ", $project_languages ),
				'countries' => implode( ", ", $geo_countries ),
				'start'     => $dbw->timestamp( $startTs ),
				'end'       => $dbw->timestamp( $endTs ),
				'enabled'   => $enabled,
				'preferred' => 0,
				'locked'    => 0,
				'geo'       => $geotargeted
			);
			Campaign::logCampaignChange( 'created', $not_id, $user,
				$beginSettings, $endSettings );

			return true;
		}

		throw new MWException( 'insertId() did not return a value.' );
	}

	/**
	 * Remove a campaign from the database
	 *
	 * @param $campaignName string: Name of the campaign
	 * @param $user User removing the campaign
	 *
	 * @return bool|string True on success, string with message key for error
	 */
	static function removeCampaign( $campaignName, $user ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		$res = $dbr->select( 'cn_notices', 'not_name, not_locked',
			array( 'not_name' => $campaignName )
		);
		if ( $dbr->numRows( $res ) < 1 ) {
			return 'centralnotice-remove-notice-doesnt-exist';
		}
		$row = $dbr->fetchObject( $res );
		if ( $row->not_locked == '1' ) {
			return 'centralnotice-notice-is-locked';
		}

		Campaign::removeCampaignByName( $campaignName, $user );

		return true;
	}

	private static function removeCampaignByName( $campaignName, $user ) {
		// Log the removal of the campaign
		$campaignId = Campaign::getNoticeId( $campaignName );
		Campaign::logCampaignChange( 'removed', $campaignId, $user );

		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$dbw->begin();
		$dbw->delete( 'cn_assignments', array( 'not_id' => $campaignId ) );
		$dbw->delete( 'cn_notices', array( 'not_name' => $campaignName ) );
		$dbw->delete( 'cn_notice_languages', array( 'nl_notice_id' => $campaignId ) );
		$dbw->delete( 'cn_notice_projects', array( 'np_notice_id' => $campaignId ) );
		$dbw->delete( 'cn_notice_countries', array( 'nc_notice_id' => $campaignId ) );
		$dbw->commit();
	}

	/**
	 * Assign a banner to a campaign at a certain weight
	 * @param $noticeName string
	 * @param $templateName string
	 * @param $weight
	 * @return bool|string True on success, string with message key for error
	 */
	static function addTemplateTo( $noticeName, $templateName, $weight ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		$eNoticeName = htmlspecialchars( $noticeName );
		$noticeId = Campaign::getNoticeId( $eNoticeName );
		$templateId = Banner::getTemplateId( $templateName );
		$res = $dbr->select( 'cn_assignments', 'asn_id',
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);

		if ( $dbr->numRows( $res ) > 0 ) {
			return 'centralnotice-template-already-exists';
		}
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$dbw->begin();
		$noticeId = Campaign::getNoticeId( $eNoticeName );
		$dbw->insert( 'cn_assignments',
			array(
				'tmp_id'     => $templateId,
				'tmp_weight' => $weight,
				'not_id'     => $noticeId
			)
		);
		$dbw->commit();

		return true;
	}

	/**
	 * Remove a banner assignment from a campaign
	 */
	static function removeTemplateFor( $noticeName, $templateName ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$dbw->begin();
		$noticeId = Campaign::getNoticeId( $noticeName );
		$templateId = Banner::getTemplateId( $templateName );
		$dbw->delete( 'cn_assignments', array( 'tmp_id' => $templateId, 'not_id' => $noticeId ) );
		$dbw->commit();
	}

	/**
	 * Lookup the ID for a campaign based on the campaign name
	 */
	static function getNoticeId( $noticeName ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		if ( $row ) {
			return $row->not_id;
		} else {
			return null;
		}
	}

	/**
	 * Lookup the name of a campaign based on the campaign ID
	 */
	static function getNoticeName( $noticeId ) {
		// Read from the master database to avoid concurrency problems
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		if ( is_numeric( $noticeId ) ) {
			$row = $dbr->selectRow( 'cn_notices', 'not_name', array( 'not_id' => $noticeId ) );
			if ( $row ) {
				return $row->not_name;
			}
		}
		return null;
	}

	static function getNoticeProjects( $noticeName ) {
		// Read from the master database to avoid concurrency problems
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$projects = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_projects', 'np_project',
				array( 'np_notice_id' => $row->not_id ) );
			foreach ( $res as $projectRow ) {
				$projects[ ] = $projectRow->np_project;
			}
		}
		return $projects;
	}

	static function getNoticeLanguages( $noticeName ) {
		// Read from the master database to avoid concurrency problems
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$languages = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_languages', 'nl_language',
				array( 'nl_notice_id' => $row->not_id ) );
			foreach ( $res as $langRow ) {
				$languages[ ] = $langRow->nl_language;
			}
		}
		return $languages;
	}

	static function getNoticeCountries( $noticeName ) {
		// Read from the master database to avoid concurrency problems
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$eNoticeName = htmlspecialchars( $noticeName );
		$row = $dbr->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $eNoticeName ) );
		$countries = array();
		if ( $row ) {
			$res = $dbr->select( 'cn_notice_countries', 'nc_country',
				array( 'nc_notice_id' => $row->not_id ) );
			foreach ( $res as $countryRow ) {
				$countries[ ] = $countryRow->nc_country;
			}
		}
		return $countries;
	}

	/**
	 * @param $noticeName string
	 * @param $start string Date
	 * @param $end string Date
	 * @return bool|string True on success, string with message key for error
	 */
	static function updateNoticeDate( $noticeName, $start, $end ) {
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_SLAVE, array(), $wgCentralDBname );

		// Start/end don't line up
		if ( $start > $end || $end < $start ) {
			return 'centralnotice-invalid-date-range';
		}

		// Invalid campaign name
		if ( !Campaign::campaignExists( $noticeName ) ) {
			return 'centralnotice-notice-doesnt-exist';
		}

		// Overlap over a date within the same project and language
		$startDate = $dbr->timestamp( $start );
		$endDate = $dbr->timestamp( $end );

		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$dbw->update( 'cn_notices',
			array(
				'not_start' => $startDate,
				'not_end'   => $endDate
			),
			array( 'not_name' => $noticeName )
		);

		return true;
	}

	/**
	 * Update a boolean setting on a campaign
	 *
	 * @param $noticeName string: Name of the campaign
	 * @param $settingName string: Name of a boolean setting (enabled, locked, or geo)
	 * @param $settingValue int: Value to use for the setting, 0 or 1
	 */
	static function setBooleanCampaignSetting( $noticeName, $settingName, $settingValue ) {
		if ( !Campaign::campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			global $wgCentralDBname;
			$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
			$dbw->update( 'cn_notices',
				array( 'not_' . $settingName => $settingValue ),
				array( 'not_name' => $noticeName )
			);
		}
	}

	/**
	 * Updates a numeric setting on a campaign
	 *
	 * @param string $noticeName Name of the campaign
	 * @param string $settingName Name of a numeric setting (preferred)
	 * @param int $settingValue Value to use
	 * @param int $max The max that the value can take, default 1
	 * @param int $min The min that the value can take, default 0
	 * @throws MWException|RangeException
	 */
	static function setNumericCampaignSetting( $noticeName, $settingName, $settingValue, $max = 1, $min = 0 ) {
		if ( $max <= $min ) {
			throw new RangeException( 'Max must be greater than min.' );
		}

		if ( !is_numeric( $settingValue ) ) {
			throw new MWException( 'Setting value must be numeric.' );
		}

		if ( $settingValue > $max ) {
			$settingValue = $max;
		}

		if ( $settingValue < $min ) {
			$settingValue = $min;
		}

		if ( !Campaign::campaignExists( $noticeName ) ) {
			// Exit quietly since campaign may have been deleted at the same time.
			return;
		} else {
			$settingName = strtolower( $settingName );
			global $wgCentralDBname;
			$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
			$dbw->update( 'cn_notices',
				array( 'not_'.$settingName => $settingValue ),
				array( 'not_name' => $noticeName )
			);
		}
	}

	/**
	 * Updates the weight of a banner in a campaign.
	 *
	 * @param $noticeName   Name of the campaign to update
	 * @param $templateId   ID of the banner in the campaign
	 * @param $weight       New banner weight
	 */
	static function updateWeight( $noticeName, $templateId, $weight ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$noticeId = Campaign::getNoticeId( $noticeName );
		$dbw->update( 'cn_assignments',
			array( 'tmp_weight' => $weight ),
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);
	}

	/**
	 * Updates the bucket of a banner in a campaign. Buckets alter what is shown to the end user
	 * which can affect the relative weight of the banner in a campaign.
	 *
	 * @param $noticeName   Name of the campaign to update
	 * @param $templateId   ID of the banner in the campaign
	 * @param $bucket       New bucket number
	 */
	static function updateBucket( $noticeName, $templateId, $bucket ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$noticeId = Campaign::getNoticeId( $noticeName );
		$dbw->update( 'cn_assignments',
			array( 'asn_bucket' => $bucket ),
			array(
				'tmp_id' => $templateId,
				'not_id' => $noticeId
			)
		);
	}

	// @todo FIXME: Unused.
	static function updateProjectName( $notice, $projectName ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$dbw->update( 'cn_notices',
			array( 'not_project' => $projectName ),
			array(
				'not_name' => $notice
			)
		);
	}

	static function updateProjects( $notice, $newProjects ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$dbw->begin();

		// Get the previously assigned projects
		$oldProjects = Campaign::getNoticeProjects( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );

		// Add newly assigned projects
		$addProjects = array_diff( $newProjects, $oldProjects );
		$insertArray = array();
		foreach ( $addProjects as $project ) {
			$insertArray[ ] = array( 'np_notice_id' => $row->not_id, 'np_project' => $project );
		}
		$dbw->insert( 'cn_notice_projects', $insertArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated projects
		$removeProjects = array_diff( $oldProjects, $newProjects );
		if ( $removeProjects ) {
			$dbw->delete( 'cn_notice_projects',
				array( 'np_notice_id' => $row->not_id, 'np_project' => $removeProjects )
			);
		}

		$dbw->commit();
	}

	static function updateProjectLanguages( $notice, $newLanguages ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$dbw->begin();

		// Get the previously assigned languages
		$oldLanguages = Campaign::getNoticeLanguages( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );

		// Add newly assigned languages
		$addLanguages = array_diff( $newLanguages, $oldLanguages );
		$insertArray = array();
		foreach ( $addLanguages as $code ) {
			$insertArray[ ] = array( 'nl_notice_id' => $row->not_id, 'nl_language' => $code );
		}
		$dbw->insert( 'cn_notice_languages', $insertArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated languages
		$removeLanguages = array_diff( $oldLanguages, $newLanguages );
		if ( $removeLanguages ) {
			$dbw->delete( 'cn_notice_languages',
				array( 'nl_notice_id' => $row->not_id, 'nl_language' => $removeLanguages )
			);
		}

		$dbw->commit();
	}

	static function updateCountries( $notice, $newCountries ) {
		global $wgCentralDBname;
		$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );

		// Get the previously assigned languages
		$oldCountries = Campaign::getNoticeCountries( $notice );

		// Get the notice id
		$row = $dbw->selectRow( 'cn_notices', 'not_id', array( 'not_name' => $notice ) );

		// Add newly assigned countries
		$addCountries = array_diff( $newCountries, $oldCountries );
		$insertArray = array();
		foreach ( $addCountries as $code ) {
			$insertArray[ ] = array( 'nc_notice_id' => $row->not_id, 'nc_country' => $code );
		}
		$dbw->insert( 'cn_notice_countries', $insertArray, __METHOD__, array( 'IGNORE' ) );

		// Remove disassociated countries
		$removeCountries = array_diff( $oldCountries, $newCountries );
		if ( $removeCountries ) {
			$dbw->delete( 'cn_notice_countries',
				array( 'nc_notice_id' => $row->not_id, 'nc_country' => $removeCountries )
			);
		}
	}

	/**
	 * Log any changes related to a campaign
	 *
	 * @param $action           string: 'created', 'modified', or 'removed'
	 * @param $campaignId       int: ID of campaign
	 * @param $user             User causing the change
	 * @param $beginSettings    array of campaign settings before changes (optional)
	 * @param $endSettings      array of campaign settings after changes (optional)
	 * @param $beginAssignments array of banner assignments before changes (optional)
	 * @param $endAssignments   array of banner assignments after changes (optional)
	 *
	 * @return integer: ID of log entry (or null)
	 */
	static function logCampaignChange( $action, $campaignId, $user, $beginSettings = array(),
								$endSettings = array(), $beginAssignments = array(), $endAssignments = array()
	) {
		// Only log the change if it is done by an actual user (rather than a testing script)
		if ( $user->getId() > 0 ) { // User::getID returns 0 for anonymous or non-existant users
			global $wgCentralDBname;
			$dbw = wfGetDB( DB_MASTER, array(), $wgCentralDBname );

			$log = array(
				'notlog_timestamp' => $dbw->timestamp(),
				'notlog_user_id'   => $user->getId(),
				'notlog_action'    => $action,
				'notlog_not_id'    => $campaignId,
				'notlog_not_name'  => Campaign::getNoticeName( $campaignId )
			);

			foreach ( $beginSettings as $key => $value ) {
				$log[ 'notlog_begin_' . $key ] = $value;
			}
			foreach ( $endSettings as $key => $value ) {
				$log[ 'notlog_end_' . $key ] = $value;
			}

			$dbw->insert( 'cn_notice_log', $log );
			$log_id = $dbw->insertId();
			return $log_id;
		} else {
			return null;
		}
	}

	static function campaignLogs( $campaign=false, $username=false, $start=false, $end=false, $limit=50, $offset=0 ) {

		$conds = array();
		if ( $start ) {
			$conds[] = "notlog_timestamp >= $start";
		}
		if ( $end ) {
			$conds[] = "notlog_timestamp < $end";
		}
		if ( $campaign ) {
			$conds[] = "notlog_not_name LIKE '$campaign'";
		}
		if ( $username ) {
			$user = User::newFromName( $username );
			if ( $user ) {
				$conds[] = "notlog_user_id = {$user->getId()}";
			}
		}

		// Read from the master database to avoid concurrency problems
		global $wgCentralDBname;
		$dbr = wfGetDB( DB_MASTER, array(), $wgCentralDBname );
		$res = $dbr->select( 'cn_notice_log', '*', $conds,
			__METHOD__,
			array(
				"ORDER BY" => "notlog_timestamp DESC",
				"LIMIT" => $limit,
				"OFFSET" => $offset,
			)
		);
		$logs = array();
		foreach ( $res as $row ) {
			$entry = new CampaignLog( $row );
			$logs[] = array_merge( get_object_vars( $entry ), $entry->changes() );
		}
		return $logs;
	}
}

class CampaignExistenceException extends MWException {}
