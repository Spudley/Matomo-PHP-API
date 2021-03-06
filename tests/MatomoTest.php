<?php

require(__DIR__ . '/../src/Matomo.php');

use VisualAppeal\Matomo;

class MatomoTest extends \PHPUnit\Framework\TestCase
{
	const TEST_SITE_URL = 'https://demo.matomo.org/';

	const TEST_SITE_ID = 7;

	const TEST_TOKEN = 'anonymous';

	/**
	 * Matomo api instance.
	 *
	 * @var \VisualAppeal\Matomo
	 */
	private $_matomo = null;

	protected function setUp()
	{
		$this->_matomo = new Matomo(self::TEST_SITE_URL, self::TEST_TOKEN, self::TEST_SITE_ID);
	}

	protected function tearDown()
	{
		unset($this->_matomo);
		$this->_matomo = null;
	}

	/**
	 * Test creation of class instance.
	 */
	public function testInit()
	{
		$this->assertInstanceOf(\VisualAppeal\Matomo::class, $this->_matomo);
	}

	/**
	 * Test the default api call.
	 */
	public function testDefaultCall()
	{
		$result = $this->_matomo->getVisits();

		$this->assertInternalType('int', $result);
		$this->assertEquals('', implode(',', $this->_matomo->getErrors()));
	}

	/**
	 * Test the result of a time range.
	 *
	 * @depends testDefaultCall
	 */
	public function testRangePeriod()
	{
		$this->_matomo->setPeriod(Matomo::PERIOD_RANGE);
		$this->_matomo->setRange(date('Y-m-d', time() - 3600 * 24), date('Y-m-d'));
		$result = $this->_matomo->getVisitsSummary();

		$this->assertInternalType('object', $result);
		$this->assertEquals('', implode(',', $this->_matomo->getErrors()));
	}

	/**
	 * Test the result of one day
	 *
	 * @depends testDefaultCall
	 */
	public function testDayPeriod()
	{
		$this->_matomo->setPeriod(Matomo::PERIOD_DAY);
		$this->_matomo->setDate(date('Y-m-d', time() - 3600 * 24));
		$result = $this->_matomo->getVisitsSummary();

		$this->assertInternalType('object', $result);
		$this->assertEquals('', implode(',', $this->_matomo->getErrors()));
	}

	/**
	 * Test the result of multiple dates.
	 *
	 * @depends testDayPeriod
	 * @link https://github.com/VisualAppeal/Matomo-PHP-API/issues/14
	 */
	public function testMultipleDates()
	{
		$this->_matomo->setPeriod(Matomo::PERIOD_DAY);
		$this->_matomo->setRange(date('Y-m-d', time() - 3600 * 24 * 6), date('Y-m-d'));
		$result = $this->_matomo->getVisitsSummary();

		$this->assertInternalType('object', $result);
		$this->assertEquals(7, count((array) $result));
		$this->assertEquals('', implode(',', $this->_matomo->getErrors()));
	}

	/**
	 * Test if all dates and ranges get the same result
	 *
	 * @depends testRangePeriod
	 * @depends testMultipleDates
	 */
	public function testDateEquals()
	{
		$date = date('Y-m-d', time() - 3600 * 24 * 7);

		// Range
		$this->_matomo->setPeriod(Matomo::PERIOD_RANGE);
		$this->_matomo->setRange($date, $date);

		$result1 = $this->_matomo->getVisits();
		$this->_matomo->reset();

		// Single date
		$this->_matomo->setPeriod(Matomo::PERIOD_DAY);
		$this->_matomo->setDate($date);

		$result2 = $this->_matomo->getVisits();
		$this->_matomo->reset();

		// Multiple dates
		$this->_matomo->setPeriod(Matomo::PERIOD_DAY);
		$this->_matomo->setRange($date, $date);

		$result3 = $this->_matomo->getVisits();
		$result3 = $result3->$date;
		$this->_matomo->reset();

		// Multiple dates with default range end
		$this->_matomo->setPeriod(Matomo::PERIOD_DAY);
		$this->_matomo->setRange($date);

		$result4 = $this->_matomo->getVisits();
		$result4 = $result4->$date;
		$this->_matomo->reset();

		// previousX respectively lastX date
		$this->_matomo->setPeriod(Matomo::PERIOD_DAY);
		$this->_matomo->setRange('previous7');

		$result5 = $this->_matomo->getVisits();
		$result5 = $result5->$date;


		// Compare results
		$this->assertEquals($result1, $result2);
		$this->assertEquals($result2, $result3);
		$this->assertEquals($result3, $result4);
		$this->assertEquals($result4, $result5);
	}

	/**
	 * Test call with no date or range set
	 *
	 * @depends testDefaultCall
	 */
	public function testNoPeriodOrDate()
	{
		$this->_matomo->setRange(null, null);
		$this->_matomo->setDate(null);
		$result = $this->_matomo->getVisitsSummary();

		$this->assertFalse($result);
		$this->assertEquals(1, count($this->_matomo->getErrors()));
	}

	/**
	 * Test that multiple errors were added.
	 */
	public function testMultipleErrors()
	{
		// Test with no access => error 1
		$this->_matomo->setToken('403');
		$result = $this->_matomo->getVisitsSummary();

		$this->assertFalse($result);
		$this->assertEquals(1, count($this->_matomo->getErrors()));

		// Test with wrong url => error 2
		$this->_matomo->setSite('http://example.com/404');
		$result = $this->_matomo->getVisitsSummary();

		$this->assertFalse($result);
		$this->assertEquals(2, count($this->_matomo->getErrors()));
	}

	/**
	 * Test if optional parameters work.
	 */
	public function testOptionalParameters()
	{
		$this->_matomo->setDate('2011-01-11');
		$this->_matomo->setPeriod(Matomo::PERIOD_WEEK);
		$result = $this->_matomo->getWebsites('', [
			'flat' => 1,
		]);

		$this->assertInternalType('array', $result);
		$this->assertEquals('', implode(',', $this->_matomo->getErrors()));
		$this->assertEquals(388, $result[0]->nb_visits);
	}

	/**
	 * Test if the response contains custom variables
	 */
	public function testCustomVariables()
	{
		$this->_matomo->setDate('2011-11-08');
		$this->_matomo->setPeriod(Matomo::PERIOD_WEEK);
		$result = $this->_matomo->getCustomVariables();

		$this->assertEquals(1, count($result));
	}
}
