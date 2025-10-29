<?php
/**
 * Shared setup/teardown utilities for unit tests.
 *
 * @package OrderCategorize\Tests
 */

declare(strict_types=1);

namespace OrderCategorize\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Base PHPUnit test case that wires up Brain Monkey.
 */
abstract class AbstractUnitTestcase extends TestCase {
	/**
	 * Adds Mockery expectations to the PHPUnit assertions count.
	 */
	use MockeryPHPUnitIntegration;

	/**
	 * Sets up the environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tears down the environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
