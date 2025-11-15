<?php
/**
 * Main plugin bootstrap.
 *
 * @package OrderCategorize
 */

declare(strict_types=1);

namespace OrderCategorize;

use OrderCategorize\Admin\Assets\AdminAssets;
use OrderCategorize\Admin\Orders\ListTableFilters;
use OrderCategorize\Admin\Page\OrderBrowserPage;
use OrderCategorize\Admin\Settings\SettingsPage;
use OrderCategorize\Rest\OrderHierarchyController;

/**
 * Primary plugin bootstrap class.
 */
class Plugin {
	/**
	 * Shared instance of the plugin bootstrap.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Return an instance of the plugin bootstrap.
	 *
	 * @return Plugin class instance
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	/**
	 * Finish bootstrapping once plugins have loaded.
	 *
	 * @return void
	 */
	public function on_plugins_loaded(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			if ( is_admin() ) {
				add_action( 'admin_notices', array( $this, 'render_missing_dependency_notice' ) );
			}

			return;
		}

		OrderHierarchyController::register();
		ListTableFilters::register();

		if ( ! is_admin() ) {
			return;
		}

		SettingsPage::register();
		OrderBrowserPage::register();
		AdminAssets::register();
	}

	/**
	 * Render an admin notice when WooCommerce is not active.
	 *
	 * @return void
	 */
	public function render_missing_dependency_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s plugin name. */
					esc_html__( '%s requires WooCommerce to be installed and activated.', 'order-categorize' ),
					'<strong>' . esc_html__( 'Order Categorize', 'order-categorize' ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
