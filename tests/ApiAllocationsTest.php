<?php

require_once 'CentralNoticeTestFixtures.php';
require_once 'ComparisonUtil.php';

/**
 * @group CentralNotice
 * @group medium
 * @group Database
 *
 * This is a little sloppy, it is testing both the api and the allocations algorithms
 */
class ApiAllocationsTest extends ApiTestCase {
	protected $cnFixtures;

	protected function setUp() {
		parent::setUp();

		$this->userUser = self::$users['uploader']->user;

		$this->cnFixtures = new CentralNoticeTestFixtures( $this->userUser );
	}

	protected function tearDown() {
		parent::tearDown();
	}

	public function testEqualAllocations() {
		// Campaign has two banners, with default parameters
		$this->cnFixtures->addFixtures( array(
			'campaigns' => array(
				array(
					'banners' => array(
						array(),
						array()
					),
				),
			),
		) );
		$expected = array(
			'centralnoticeallocations' => array(
				'banners' => array(
					array (
						'name' => $this->cnFixtures->spec['campaigns'][0]['banners'][0]['name'],
						'fundraising' => 1,
						'campaign' => $this->cnFixtures->spec['campaigns'][0]['name'],
						'bucket' => 0,
						'allocation' => .5,
					),
					array (
						'name' => $this->cnFixtures->spec['campaigns'][0]['banners'][1]['name'],
						'fundraising' => 1,
						'campaign' => $this->cnFixtures->spec['campaigns'][0]['name'],
						'bucket' => 0,
						'allocation' => .5,
					),
				),
			),
		);

		$ret = $this->doApiRequest( array(
            'action' => 'centralnoticeallocations',
		) );
		$this->assertTrue( ComparisonUtil::assertSuperset( $ret[0], $expected ) );
	}

	//TODO:
	//function testInvalid() {

	//function testFilters() {

	//function testUnderallocation() {
	// * driven below 1 / total_weight -> 1
	// * 1/4 edge?
	// * an underallocation -> in a predictable order
	//function testKnownUnequal() {
	//function testPriorities() {
	//function test() {
}
