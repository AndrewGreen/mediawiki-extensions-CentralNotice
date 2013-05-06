<?php

/**
 * Special page for management of CentralNotice banners
 */
class SpecialCentralNoticeBanners extends CentralNotice {
	/** @var string Name of the banner we're currently editing */
	protected $bannerName = '';

	/** @var string Filter to apply to the banner search when generating the list */
	protected $bannerFilterString = '';

	/** @var string Language code to render preview materials in */
	protected $bannerLanguagePreview;

	/** @var bool If true, form execution must stop and the page will be redirected */
	protected $bannerFormRedirectRequired = false;

	function __construct() {
		SpecialPage::__construct( 'CentralNoticeBanners' );

		// Make sure we have a session
		wfSetupSession();

		// Load things that may have been serialized into the session
		$this->bannerFilterString = $this->getCNSessionVar( 'bannerFilterString', '' );
		$this->bannerLanguagePreview = $this->getCNSessionVar(
			'bannerLanguagePreview',
			$this->getLanguage()->getCode()
		);
	}

	function __destruct() {
		$this->setCNSessionVar( 'bannerFilterString', $this->bannerFilterString );
	}

	/**
	 * Whether this special page is listed in Special:SpecialPages
	 * @return Bool
	 */
	public function isListed() {
		return false;
	}

	/**
	 * Handle all the different types of page requests determined by $action
	 *
	 * Valid actions are:
	 *    Null      - Display a list of banners
	 *    Edit      - Edits an existing banner
	 */
	public function execute( $page ) {
		// Do all the common setup
		$this->setHeaders();
		$this->editable = $this->getUser()->isAllowed( 'centralnotice-admin' );

		// User settable text for some custom message, like usage instructions
		$this->getOutput()->setPageTitle( $this->msg( 'noticetemplate' ) );
		$this->getOutput()->addWikiMsg( 'centralnotice-summary' );

		// Now figure out wth to display
		$parts = explode( '/', $page );
		$action = ( isset( $parts[0] ) && $parts[0] ) ? $parts[0]: 'list';

		switch ( strtolower( $action ) ) {
			case 'list':
				// Display the list of banners
				$this->showBannerList();
				break;

			case 'edit':
				// Display the banner editor form
				if ( array_key_exists( 1, $parts ) ) {
					$this->bannerName = $parts[1];
					$this->showBannerEditor();
				} else {
					throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
				}
				break;

			case 'preview':
				// Preview all available translations
				// Display the banner editor form
				if ( array_key_exists( 1, $parts ) ) {
					$this->bannerName = $parts[1];
					$this->showAllLanguages();
				} else {
					throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
				}
				break;

			default:
				// Something went wrong; display error page
				throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
				break;
		}
	}

	/**
	 * Process the 'banner list' form and display a new one.
	 */
	protected function showBannerList() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'centralnotice-manage-templates' ) );
		$out->addModules( 'ext.centralNotice.adminUi.bannerManager' );

		// Process the form that we sent out
		$formDescriptor = $this->generateBannerListForm( $this->bannerFilterString );
		$htmlForm = new CentralNoticeHtmlForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitCallback( array( $this, 'processBannerList' ) );
		$htmlForm->loadData();
		$formResult = $htmlForm->trySubmit();

		if ( $this->bannerFormRedirectRequired ) {
			return;
		}

		// Re-generate the form in case they changed the filter string, archived something,
		// deleted something, etc...
		$formDescriptor = $this->generateBannerListForm( $this->bannerFilterString );
		$htmlForm = new CentralNoticeHtmlForm( $formDescriptor, $this->getContext() );

		$htmlForm->setId('cn-banner-manager')->
			suppressDefaultSubmit()->
			setDisplayFormat( 'div' )->
			prepareForm()->
			displayForm( $formResult );
	}

	/**
	 * Generates the HTMLForm entities for the 'banner list' form.
	 *
	 * @param string $filter Filter to use for the banner list
	 *
	 * @return array of HTMLForm entities
	 */
	protected function generateBannerListForm( $filter = '' ) {
		// --- Create the banner search form --- //
		$formDescriptor = array(
			'bannerNameLike' => array(
				'section' => 'header/banner-search',
				'class' => 'HTMLTextField',
				'placeholder' => wfMessage( 'centralnotice-filter-template-prompt' ),
				'filter-callback' => array( $this, 'sanitizeSearchTerms' ),
				'default' => $filter,
			),
			'filterSubmit' => array(
				'section' => 'header/banner-search',
				'class' => 'HTMLSubmitField',
				'default' => wfMessage( 'centralnotice-filter-template-submit' )->text(),
			)
		);

		// --- Create the management options --- //
		$formDescriptor += array(
			'selectAllBanners' => array(
				'section' => 'header/banner-bulk-manage',
				'class' => 'HTMLCheckField',
				'disabled' => !$this->editable,
			),
			/* TODO: Actually enable this feature
			'archiveSelectedBanners' => array(
				'section' => 'header/banner-bulk-manage',
				'class' => 'HTMLButtonField',
				'default' => 'Archive',
				'disabled' => !$this->editable,
			),
			*/
			'deleteSelectedBanners' => array(
				'section' => 'header/banner-bulk-manage',
				'class' => 'HTMLButtonField',
				'default' => wfMessage( 'centralnotice-remove' )->text(),
				'disabled' => !$this->editable,
			),
			'addNewBanner' => array(
				'section' => 'header/one-off',
				'class' => 'HTMLButtonField',
				'default' => wfMessage( 'centralnotice-add-template' )->text(),
				'disabled' => !$this->editable,
			),
			'newBannerName' => array(
				'section' => 'addBanner',
				'class' => 'HTMLTextField',
				'disabled' => !$this->editable,
				'label' => wfMessage( 'centralnotice-banner-name' )->text(),
			),
			'action' => array(
				'type' => 'hidden',
			)
		);

		// --- Add all the banners via the fancy pager object ---
		$pager = new CNBannerPager(
			$this->getTitle(),
			'banner-list',
			array(
				 'applyTo' => array(
					 'section' => 'banner-list',
					 'class' => 'HTMLCheckField',
					 'cssclass' => 'cn-bannerlist-check-applyto',
				 )
			),
			array(),
			$filter,
			$this->editable
		);
		$formDescriptor[ 'topPagerNav' ] = $pager->getNavigationBar();
		$formDescriptor += $pager->getBody();
		$formDescriptor[ 'bottomPagerNav' ] = $pager->getNavigationBar();

		return $formDescriptor;
	}

	/**
	 * Callback function from the showBannerList() form that actually processes the
	 * response data.
	 *
	 * @param $formData
	 *
	 * @return null|string|array
	 */
	public function processBannerList( $formData ) {
		$this->bannerFilterString = $formData[ 'bannerNameLike' ];

		if ( $formData[ 'action' ] && $this->editable ) {
			switch ( strtolower( $formData[ 'action' ] ) ) {
				case 'create':
					// Attempt to create a new banner and redirect; we validate here because it's
					// a hidden field and that doesn't work so well with the form
					if ( !Banner::isValidBannerName( $formData[ 'newBannerName' ] ) ) {
						return wfMessage( 'centralnotice-banner-name-error' );
					} else {
						$this->bannerName = $formData[ 'newBannerName' ];
					}

					if ( Banner::bannerExists( $this->bannerName ) ) {
						return wfMessage( 'centralnotice-template-exists' )->text();
					} else {
						$retval = Banner::addTemplate(
							$this->bannerName,
							"<!-- Empty banner -->",
							$this->getUser(),
							false,
							false
						);

						if ( $retval ) {
							// Something failed; display error to user
							return wfMessage( $retval )->text();
						} else {
							$this->getOutput()->redirect(
								SpecialPage::getTitleFor( 'CentralNoticeBanners', "edit/{$this->bannerName}" )->
									getFullURL()
							);
							$this->bannerFormRedirectRequired = true;
						}
					}
					break;

				case 'archive':
					return ('Archiving not yet implemented!');
					break;

				case 'remove':
					$failed = array();
					foreach( $formData as $element => $value ) {
						$parts = explode( '-', $element, 2 );
						if ( ( $parts[0] === 'applyTo' ) && ( $value === true ) ) {
							try {
								Banner::removeTemplate( $parts[1], $this->getUser() );
							} catch ( Exception $ex ) {
								$failed[] = $parts[1];
							}
						}
					}
					if ( $failed ) {
						return 'some banners were not deleted';
					}
					break;
			}
		} elseif ( $formData[ 'action' ] ) {
			// Oh noes! The l33t hakorz are here...
			return wfMessage( 'centralnotice-generic-error' )->text();
		}

		return null;
	}

	/**
	 * Display the banner editor and process edits
	 */
	protected function showBannerEditor() {
		$out = $this->getOutput();
		$out->addModules( 'ext.centralNotice.adminUi.bannerEditor' );

		if ( !Banner::isValidBannerName( $this->bannerName ) ) {
			throw new ErrorPageError( 'noticetemplate', 'centralnotice-generic-error' );
		}
		$out->setPageTitle( $this->bannerName );

		// Generate the form
		$formDescriptor = $this->generateBannerEditForm( $this->bannerName );

		// Now begin form processing
		$htmlForm = new CentralNoticeHtmlForm( $formDescriptor, $this->getContext(), 'centralnotice' );
		$htmlForm->setSubmitCallback( array( $this, 'processEditBanner' ) );
		$htmlForm->loadData();

		$formResult = $htmlForm->tryAuthorizedSubmit();

		if ( $this->bannerFormRedirectRequired ) {
			return;
		}

		// Recreate the form because something could have changed
		$formDescriptor = $this->generateBannerEditForm( $this->bannerName );

		$htmlForm = new CentralNoticeHtmlForm( $formDescriptor, $this->getContext(), 'centralnotice' );
		$htmlForm->setSubmitCallback( array( $this, 'processEditBanner' ) )->setId( 'cn-banner-editor' );

		// Push the form back to the user
		$htmlForm->suppressDefaultSubmit()->
			setId( 'cn-banner-editor' )->
			setDisplayFormat( 'div' )->
			prepareForm()->
			displayForm( $formResult );
	}

	protected function generateBannerEditForm() {
		global $wgNoticeUseTranslateExtension, $wgNoticeFundraisingUrl, $wgLanguageCode;

		$languages = Language::fetchLanguageNames( $this->getLanguage()->getCode() );
		array_walk( $languages, function( &$val, $index ) { $val = "$index - $val"; } );
		$languages = array_flip( $languages );

		$banner = new Banner( $this->bannerName );
		$bannerSettings = $banner->getBannerSettings( $this->bannerName, true );

		$formDescriptor = array();

		/* --- Banner Preview Section --- */
		$formDescriptor[ 'preview' ] = array(
			'section' => 'preview',
			'class' => 'HTMLCentralNoticeBanner',
			'banner' => $this->bannerName,
			'language' => $this->getLanguage()->getCode(),
		);

		/* --- Banner Settings --- */
		// TODO: Make this far more flexible with the option for arbitrary banner classes
		$class = ( $bannerSettings[ 'fundraising' ] === 1 ) ? 'fundraising' : 'generic';
		$formDescriptor[ 'banner-class' ] = array(
			'section' => 'settings',
			'type' => 'select',
			'disabled' => !$this->editable,
			'label-message' => 'centralnotice-banner-class',
			'help-message' => 'centralnotice-banner-class-desc',
			'options' => array(
				$this->msg( 'centralnotice-banner-class-generic' )->text() => 'generic',
				$this->msg( 'centralnotice-banner-class-fundraising' )->text() => 'fundraising'
			),
			'default' => $class,
			'cssclass' => 'separate-form-element',
		);

		$selected = array();
		if ( $bannerSettings[ 'anon' ] === 1 ) { $selected[] = 'anonymous'; }
		if ( $bannerSettings[ 'account' ] === 1 ) { $selected[] = 'registered'; }
		$formDescriptor[ 'display-to' ] = array(
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label-message' => 'centralnotice-banner-display',
			'options' => array(
				$this->msg( 'centralnotice-banner-logged-in' )->text() => 'registered',
				$this->msg( 'centralnotice-banner-anonymous' )->text() => 'anonymous'
			),
			'default' => $selected,
			'cssclass' => 'separate-form-element',
		);

		$assignedDevices = array_values( CNDeviceTarget::getDevicesAssociatedWithBanner( $banner->getId() ) );
		$availableDevices = array();
		foreach ( CNDeviceTarget::getAvailableDevices() as $k => $value ) {
			$header = $value[ 'header' ];
			$label = $this->getOutput()->parseInline( $value[ 'label' ] );
			$availableDevices[ "($header) $label" ] = $header;
		}
		$formDescriptor[ 'device-classes' ] = array(
			'section' => 'settings',
			'type' => 'multiselect',
			'disabled' => !$this->editable,
			'label' => 'Display on',
			'options' => $availableDevices,
			'default' => $assignedDevices,
			'cssclass' => 'separate-form-element',
		);

		$formDescriptor[ 'create-landingpage-link' ] = array(
			'section' => 'settings',
			'type' => 'toggle',
			'disabled' => !$this->editable,
			'hidelabel' => true,
			'label-message' => 'centralnotice-banner-autolink',
			'help' => $this->msg( 'centralnotice-banner-autolink-help' )->
				rawParams( 'id="cn-landingpage-link"', 'JimmyAppeal01', $wgNoticeFundraisingUrl )->
				escaped(),
			'cssclass' => 'separate-form-element',
			'default' => $bannerSettings[ 'autolink' ],
		);

		$formDescriptor[ 'landing-pages' ] = array(
			'section' => 'settings',
			'type' => 'text',
			'label-message' => 'centralnotice-banner-landing-pages',
			'placeholder' => $this->msg( 'centralnotice-banner-landing-pages-default' )->escaped(),
			'cssclass' => 'separate-form-element',
		);
		if ( !$this->editable ) {
			$formDescriptor[ 'landing-pages' ][ 'readonly' ] = true;
		}

		/* --- Translatable Messages Section --- */
		$messages = $banner->extractMessageFields( $banner->getContent() );

		if ( $messages ) {
			// Only show this part of the form if messages exist

			$formDescriptor[ 'translate-language' ] = array(
				'section' => 'banner-messages',
				'class' => 'LanguageSelectHeaderElement',
				'label-message' => 'centralnotice-language',
				'options' => $languages,
				'default' => $this->bannerLanguagePreview,
				'cssclass' => 'separate-form-element',
			);

			$messageReadOnly = false;
			if ( $wgNoticeUseTranslateExtension && ( $this->bannerLanguagePreview !== $wgLanguageCode ) ) {
				$messageReadOnly = true;
			}
			foreach ( $messages as $messageName => $count ) {
				if ( $wgNoticeUseTranslateExtension ) {
					// Create per message link to the translate extension
					$title = SpecialPage::getTitleFor( 'Translate' );
					$label = Xml::tags( 'td', null,
						Linker::link( $title, htmlspecialchars( $messageName ), array(), array(
								'group' => BannerMessageGroup::getTranslateGroupName( $banner->getName() ),
								'task' => 'view'
							)
						)
					);
				} else {
					$label = htmlspecialchars( $messageName );
				}

				$formDescriptor[ "message-$messageName" ] = array(
					'section' => 'banner-messages',
					'class' => 'HTMLCentralNoticeBannerMessage',
					'label-raw' => $label,
					'banner' => $this->bannerName,
					'message' => $messageName,
					'language' => $this->bannerLanguagePreview,
					'cssclass' => 'separate-form-element',
				);

				if ( !$this->editable || $messageReadOnly ) {
					$formDescriptor[ "message-$messageName" ][ 'readonly' ] = true;
				}
			}

			if ( $wgNoticeUseTranslateExtension ) {
				$formDescriptor[ 'priority-langs' ] = array(
					'section' => 'banner-messages',
					'class' => 'HTMLLargeMultiSelectField',
					'disabled' => !$this->editable,
					'label-message' => 'centralnotice-prioritylangs',
					'options' => $languages,
					'default' => $bannerSettings[ 'prioritylangs' ],
					'help-message' => 'centralnotice-prioritylangs-explain',
					'cssclass' => 'separate-form-element cn-multiselect',
				);
			}

			$liveMessageNames = $banner->getAvailableLanguages();
			if ( $liveMessageNames ) {
				$formDescriptor[ 'approved-languages' ] = array(
					'section' => 'banner-messages',
					'class' => 'HTMLInfoField',
					'disabled' => !$this->editable,
					'label-raw' => Linker::link(
							$this->getTitle( "Preview/{$this->bannerName}" ),
							$this->msg( 'centralnotice-preview-all-template-translations' )->escaped()
						),
					'default' => implode( ', ', $liveMessageNames ),
					'cssclass' => 'separate-form-element',
				);
			}

			if ( $wgNoticeUseTranslateExtension && BannerMessageGroup::isUsingGroupReview() ) {
				$readyStateLangs = BannerMessageGroup::getLanguagesInState(
					$this->bannerName,
					'ready'
				);

				if ( $readyStateLangs ) {
					$formDescriptor[ 'pending-languages' ] = array(
						'section' => 'banner-messages',
						'class' => 'HTMLInfoField',
						'disabled' => !$this->editable,
						'label-message' => 'centralnotice-messages-pending-approval',
						'default' => implode( ', ', $readyStateLangs ),
						'cssclass' => 'separate-form-element',
					);
				}
			}
		}

		/* -- The banner editor -- */
		$formDescriptor[ 'banner-magic-words' ] = array(
			'section' => 'edit-template',
			'class' => 'HTMLInfoField',
			'default' => Html::rawElement(
				'div',
				array( 'class' => 'separate-form-element' ),
				$this->msg( 'centralnotice-edit-template-summary' )->escaped() ),
			'rawrow' => true,
		);

		$renderer = new BannerRenderer( $this->getContext(), $banner );
		$magicWords = $renderer->getMagicWords();
		foreach ( $magicWords as &$word ) {
			$word = '{{{' . $word . '}}}';
		}
		$formDescriptor[ 'banner-mixin-words' ] = array(
			'section' => 'edit-template',
			'type' => 'info',
			'default' => $this->msg(
					'centralnotice-edit-template-magicwords',
					$this->getLanguage()->listToText( $magicWords )
				)->text(),
			'rawrow' => true,
		);

		$buttons = array();
		// TODO: Fix this gawdawful method of inserting the close button
		$buttons[ ] =
			'<a href="#" onclick="mw.centralNotice.adminUi.bannerEditor.insertButton(\'close\');return false;">' .
				$this->msg( 'centralnotice-close-button' )->text() . '</a>';
		$formDescriptor[ 'banner-insert-button' ] = array(
			'section' => 'edit-template',
			'class' => 'HTMLInfoField',
			'rawrow' => true,
			'default' => Html::rawElement(
				'div',
				array( 'class' => 'banner-editing-top-hint separate-form-element' ),
				$this->msg( 'centralnotice-insert' )->
					rawParams( $this->getLanguage()->commaList( $buttons ) )->
					escaped() ),
		);

		$formDescriptor[ 'banner-body' ] = array(
			'section' => 'edit-template',
			'type' => 'textarea',
			'readonly' => !$this->editable,
			'hidelabel' => true,
			'placeholder' => '<!-- blank banner -->',
			'default' => $banner->getContent(),
			'cssclass' => 'separate-form-element'
		);

		$links = array();
		foreach( $banner->getIncludedTemplates() as $titleObj ) {
			$links[] = Linker::link( $titleObj );
		}
		if ( $links ) {
			$formDescriptor[ 'links' ] = array(
				'section' => 'edit-template',
				'type' => 'info',
				'label-message' => 'centralnotice-templates-included',
				'default' => implode( '<br />', $links ),
				'raw' => true
			);
		}

		/* --- Form bottom options --- */
		$formDescriptor[ 'save-button' ] = array(
			'section' => 'form-actions',
			'class' => 'HTMLSubmitField',
			'default' => $this->msg( 'centralnotice-save-banner' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		);


		$formDescriptor[ 'clone-button' ] = array(
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'centralnotice-clone' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		);

		/* TODO: Add this back in when we can actually support it
		$formDescriptor[ 'archive-button' ] = array(
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'centralnotice-archive-banner' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		);
		*/

		$formDescriptor[ 'delete-button' ] = array(
			'section' => 'form-actions',
			'class' => 'HTMLButtonField',
			'default' => $this->msg( 'centralnotice-delete-banner' )->text(),
			'disabled' => !$this->editable,
			'cssclass' => 'cn-formbutton',
			'hidelabel' => true,
		);

		/* --- Hidden fields and such --- */
		$formDescriptor[ 'cloneName' ] = array(
			'section' => 'clone-banner',
			'type' => 'text',
			'disabled' => !$this->editable,
			'label-message' => 'centralnotice-clone-name',
		);

		$formDescriptor[ 'action' ] = array(
			'section' => 'form-actions',
			'type' => 'hidden',
			// The default is save so that we can still save the banner/form if the banner
			// preview has seriously borked JS. Maybe one day we'll be able to get Caja up
			// and working and not have this issue.
			'default' => 'save',
		);

		return $formDescriptor;
	}

	public function processEditBanner( $formData ) {
		// First things first! Figure out what the heck we're actually doing!
		switch ( $formData[ 'action' ] ) {
			case 'update-lang':
				$newLanguage = $formData[ 'translate-language' ];
				$this->setCNSessionVar( 'bannerLanguagePreview', $newLanguage );
				$this->bannerLanguagePreview = $newLanguage;
				break;

			case 'delete':
				if ( !$this->editable ) {
					return null;
				}
				try {
					Banner::removeTemplate( $this->bannerName, $this->getUser() );
					$this->getOutput()->redirect( $this->getTitle( '' )->getCanonicalURL() );
					$this->bannerFormRedirectRequired = true;
				} catch ( MWException $ex ) {
					return $ex->getMessage() . " <br /> " . $this->msg( 'centralnotice-template-still-bound', $this->bannerName );
				}
				break;

			case 'archive':
				if ( !$this->editable ) {
					return null;
				}
				return 'Archiving currently does not work';
				break;

			case 'clone':
				if ( !$this->editable ) {
					return null;
				}
				$newBannerName = $formData[ 'cloneName' ];
				Banner::cloneTemplate(
					$this->bannerName,
					$newBannerName,
					$this->getUser()
				);
				$this->getOutput()->redirect(
					$this->getTitle( "Edit/$newBannerName" )->getCanonicalURL()
				);
				$this->bannerFormRedirectRequired = true;
				break;

			case 'save':
				if ( !$this->editable ) {
					return null;
				}
				return $this->processSaveBannerAction( $formData );
				break;

			default:
				// Nothing was requested, so do nothing
				break;
		}
	}

	protected function processSaveBannerAction( $formData ) {
		global $wgNoticeUseTranslateExtension, $wgLanguageCode;

		$banner = new Banner( $this->bannerName );

		/* --- Update the translations --- */
		// But only if we aren't using translate or if the preview language is the content language
		if ( !$wgNoticeUseTranslateExtension || ( $this->bannerLanguagePreview === $wgLanguageCode ) ) {
			foreach( $formData as $key => $value ) {
				$parts = explode( '-', $key );
				if ( ( count( $parts ) === 2 ) && ( $parts[0] === 'message' ) ) {
					$bannerMessage = $banner->getMessageField( $parts[1] );
					$bannerMessage->update( $value, $this->bannerLanguagePreview, $this->getUser() );
				}
			}
		}

		/* --- Banner settings --- */
		if ( array_key_exists( 'priority-langs', $formData ) ) {
			$prioLang = $formData[ 'priority-langs' ];
			if ( !is_array( $prioLang ) ) {
				$prioLang = array( $prioLang );
			}
		} else {
			$prioLang = array();
		}

		$banner->editTemplate(
			$this->getUser(),
			$formData[ 'banner-body' ],
			in_array( 'anonymous', $formData[ 'display-to' ] ),
			in_array( 'registered', $formData[ 'display-to' ] ),
			$formData[ 'banner-class' ] === 'fundraising',
			$formData[ 'create-landingpage-link' ],
			$formData[ 'landing-pages' ],
			'',
			$prioLang,
			$formData[ 'device-classes' ]
		);

		return null;
	}

	/**
	 * Preview all available translations of a banner
	 */
	protected function showAllLanguages() {
		$out = $this->getOutput();

		if ( !Banner::isValidBannerName( $this->bannerName ) ) {
			$out->addHTML(
				Xml::element( 'div', array( 'class' => 'error' ), wfMessage( 'centralnotice-generic-error' ) )
			);
			return;
		}
		$out->setPageTitle( $this->bannerName );

		// Large amounts of memory apparently required to do this
		ini_set( 'memory_limit', '120M' );

		$banner = new Banner( $this->bannerName );

		// Pull all available text for a banner
		$langs = $banner->getAvailableLanguages();
		$htmlOut = '';

		$langContext = new DerivativeContext( $this->getContext() );
		$bannerRenderer = new BannerRenderer( $langContext, $banner );

		foreach ( $langs as $lang ) {
			$langContext->setLanguage( $lang );
			// Link and Preview all available translations
			$htmlOut .= Xml::tags(
				'td',
				array( 'valign' => 'top' ),
				$bannerRenderer->previewFieldSet()
			);
		}

		$this->getOutput()->addHtml( $htmlOut );
	}
}

/**
 * Class CentralNoticeHtmlForm
 */
class CentralNoticeHtmlForm extends HTMLForm {
	/**
	 * Get the whole body of the form.
	 * @return string
	 */
	function getBody() {
		return $this->displaySection( $this->mFieldTree, '', 'cn-formsection-' );
	}
}

/**
 * Acts as a header to the translatable banner message list
 *
 * Class LanguageSelectHeaderElement
 */
class LanguageSelectHeaderElement extends HTMLSelectField {
	public function getInputHTML( $value ) {
		global $wgContLang;

		$html = Xml::openElement( 'table', array( 'class' => 'cn-message-table' ) );
		$html .= Xml::openElement( 'tr' );

		$html .= Xml::element( 'td', array( 'class' => 'cn-message-text-origin-header' ),
			$wgContLang->fetchLanguageName( $wgContLang->getCode() )
		);

		$html .= Xml::openElement( 'td', array( 'class' => 'cn-message-text-native-header' ) );
		$html .= parent::getInputHTML( $value );
		$html .= Xml::closeElement( 'td' );

		$html .= Xml::closeElement( 'tr' );
		$html .= Xml::closeElement( 'table' );

		return $html;
	}
}

class HTMLLargeMultiSelectField extends HTMLMultiSelectField {
	public function getInputHTML( $value ) {
		if ( !is_array( $value ) ) {
			$value = array( $value );
		}

		$options = "\n";
		foreach ( $this->mParams[ 'options' ] as $name => $optvalue ) {
			$options .= Xml::option(
				$name,
				$optvalue,
				in_array( $optvalue, $value )
			) . "\n";
		}

		$properties = array(
			'multiple' => 'multiple',
			'id' => $this->mID,
			'name' => "$this->mName[]",
		);

		if ( !empty( $this->mParams[ 'disabled' ] ) ) {
			$properties[ 'disabled' ] = 'disabled';
		}

		if ( !empty( $this->mParams[ 'cssclass' ] ) ) {
			$properties[ 'class' ] = $this->mParams[ 'cssclass' ];
		}

		return Xml::tags( 'select', $properties, $options );
	}
}
