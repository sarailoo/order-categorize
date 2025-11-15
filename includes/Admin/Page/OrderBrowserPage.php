<?php
/**
 * Registers the custom order browser page inside WooCommerce.
 *
 * @package OrderCategorize\Admin
 */

declare(strict_types=1);

namespace OrderCategorize\Admin\Page;

/**
 * Handles registration and rendering for the Order Categorize dashboard page.
 */
class OrderBrowserPage {
	/**
	 * DOM node ID used by the React application.
	 */
	public const ROOT_NODE_ID = 'order-categorize-app';

	/**
	 * Stores the hook suffix returned by add_submenu_page.
	 *
	 * @var string|null
	 */
	private static ?string $hook_suffix = null;

	/**
	 * Wire up the admin menu hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ), 60 );
	}

	/**
	 * Add the submenu page beneath the WooCommerce menu.
	 *
	 * @return void
	 */
	public static function add_page(): void {
		self::$hook_suffix = add_submenu_page(
			'woocommerce',
			esc_html__( 'Order Categorize', 'order-categorize' ),
			esc_html__( 'Order Categorize', 'order-categorize' ),
			'manage_woocommerce',
			'order-categorize',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render the page shell consumed by the React app.
	 *
	 * @return void
	 */
	public static function render(): void {
		?>
		<div class="wrap orcz-order-browser">
			<h1 class="orcz-order-browser__title">
				<?php esc_html_e( 'Order Categorize', 'order-categorize' ); ?>
			</h1>
			<div
				id="<?php echo esc_attr( self::ROOT_NODE_ID ); ?>"
				class="orcz-order-browser__app"
			></div>
			<noscript>
				<p>
					<?php esc_html_e( 'This interface requires JavaScript to run.', 'order-categorize' ); ?>
				</p>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Retrieve the hook suffix for the page.
	 *
	 * @return string|null
	 */
	public static function get_hook_suffix(): ?string {
		return self::$hook_suffix;
	}
}
