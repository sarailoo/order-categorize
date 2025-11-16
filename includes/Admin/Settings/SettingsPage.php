<?php
/**
 * Settings integration for the Order Categorize plugin.
 *
 * @package OrderCategorize\Admin\Settings
 */

declare(strict_types=1);

namespace OrderCategorize\Admin\Settings;

use OrderCategorize\Settings\SettingsRepository;
/**
 * Registers the settings page under the WooCommerce menu.
 */
class SettingsPage {
	/**
	 * Menu slug for the settings page.
	 */
	private const MENU_SLUG = 'order-categorize-settings';

	/**
	 * Settings group identifier.
	 */
	private const SETTINGS_GROUP = 'order_categorize_settings_group';

	/**
	 * Hook suffix returned by add_submenu_page.
	 *
	 * @var string|null
	 */
	private static ?string $hook_suffix = null;

	/**
	 * Boot the settings page.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ), 82 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Register the submenu page.
	 *
	 * @return void
	 */
	public static function add_page(): void {
		$hook = add_submenu_page(
			'woocommerce',
			esc_html__( 'Order Categorize Settings', 'order-categorize' ),
			esc_html__( 'Order Categorize Settings', 'order-categorize' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( __CLASS__, 'render' )
		);

		self::$hook_suffix = is_string( $hook ) ? $hook : null;
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			SettingsRepository::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => SettingsRepository::get_defaults(),
			)
		);

		add_settings_section(
			'orcz_general',
			esc_html__( 'Order categorization defaults', 'order-categorize' ),
			function () {
				echo '<p>' . esc_html__( 'Configure how the order explorer drills down through products, attributes, and orders.', 'order-categorize' ) . '</p>';
			},
			self::MENU_SLUG
		);

		add_settings_field(
			'orcz_depth',
			esc_html__( 'Navigation depth', 'order-categorize' ),
			array( __CLASS__, 'render_depth_field' ),
			self::MENU_SLUG,
			'orcz_general'
		);

		add_settings_field(
			'orcz_attribute_one',
			esc_html__( 'Second step attribute', 'order-categorize' ),
			array( __CLASS__, 'render_attribute_field' ),
			self::MENU_SLUG,
			'orcz_general',
			array(
				'index' => 1,
				'label' => esc_html__( 'Choose the attribute shown after selecting a product.', 'order-categorize' ),
			)
		);

		add_settings_field(
			'orcz_attribute_two',
			esc_html__( 'Third step attribute', 'order-categorize' ),
			array( __CLASS__, 'render_attribute_field' ),
			self::MENU_SLUG,
			'orcz_general',
			array(
				'index' => 2,
				'label' => esc_html__( 'Optional attribute to drill down before orders.', 'order-categorize' ),
			)
		);

		add_settings_field(
			'orcz_statuses',
			esc_html__( 'Order statuses to include', 'order-categorize' ),
			array( __CLASS__, 'render_status_field' ),
			self::MENU_SLUG,
			'orcz_general'
		);
	}

	/**
	 * Sanitize and normalize settings payload.
	 *
	 * @param array<string,mixed> $raw Raw submitted settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( array $raw ): array {
		$raw = wp_parse_args(
			$raw,
			array(
				'depth'            => SettingsRepository::get_defaults()['depth'],
				'attribute_step_1' => '',
				'attribute_step_2' => '',
				'order_statuses'   => SettingsRepository::get_defaults()['order_statuses'],
			)
		);

		$depth = absint( $raw['depth'] );
		$depth = max( 2, min( 4, $depth ) );

		$attribute_choices = array(
			1 => '',
			2 => '',
		);

		for ( $i = 1; $i <= 2; $i++ ) {
			$value = sanitize_key( (string) ( $raw[ "attribute_step_{$i}" ] ?? '' ) );
			$attribute_choices[ $i ] = $value;
		}

		$hierarchy = array(
			array( 'type' => 'product' ),
		);

		if ( $depth >= 3 && '' !== $attribute_choices[1] ) {
			$hierarchy[] = array(
				'type'      => 'attribute',
				'attribute' => $attribute_choices[1],
			);
		}

		if ( $depth >= 4 && '' !== $attribute_choices[2] ) {
			$hierarchy[] = array(
				'type'      => 'attribute',
				'attribute' => $attribute_choices[2],
			);
		}

		$hierarchy[] = array( 'type' => 'orders' );

		$statuses = $raw['order_statuses'];
		if ( ! is_array( $statuses ) ) {
			$statuses = array();
		}

		return SettingsRepository::normalize(
			array(
				'depth'            => $depth,
				'hierarchy'        => $hierarchy,
				'order_statuses'   => $statuses,
				'attribute_step_1' => $attribute_choices[1],
				'attribute_step_2' => $attribute_choices[2],
			)
		);
	}

	/**
	 * Render the settings page markup.
	 *
	 * @return void
	 */
	public static function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Order Categorize Settings', 'order-categorize' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::MENU_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the depth dropdown.
	 *
	 * @return void
	 */
	public static function render_depth_field(): void {
		$settings = SettingsRepository::get();
		$depth    = (int) ( $settings['depth'] ?? SettingsRepository::get_defaults()['depth'] );

		?>
		<select name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[depth]">
			<option value="2" <?php selected( $depth, 2 ); ?>>
				<?php esc_html_e( 'Product → Orders', 'order-categorize' ); ?>
			</option>
			<option value="3" <?php selected( $depth, 3 ); ?>>
				<?php esc_html_e( 'Product → Attribute → Orders', 'order-categorize' ); ?>
			</option>
			<option value="4" <?php selected( $depth, 4 ); ?>>
				<?php esc_html_e( 'Product → Attribute → Attribute → Orders', 'order-categorize' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Controls how many steps operators see before viewing the final order list.', 'order-categorize' ); ?>
		</p>
		<?php
	}

	/**
	 * Render an attribute select field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 *
	 * @return void
	 */
	public static function render_attribute_field( array $args ): void {
		$index     = absint( $args['index'] ?? 1 );
		$settings = SettingsRepository::get();
		$selected = (string) ( $settings[ "attribute_step_{$index}" ] ?? '' );

		if ( '' === $selected ) {
			$hierarchy = $settings['hierarchy'] ?? array();
			if ( isset( $hierarchy[ $index ] ) && 'attribute' === ( $hierarchy[ $index ]['type'] ?? '' ) ) {
				$selected = (string) ( $hierarchy[ $index ]['attribute'] ?? '' );
			}
		}

		$attributes = wc_get_attribute_taxonomies();

		$options = array( '' => esc_html__( '— Select an attribute —', 'order-categorize' ) );
		foreach ( $attributes as $attribute ) {
			$options[ 'pa_' . $attribute->attribute_name ] = $attribute->attribute_label;
		}

		$field_name = SettingsRepository::OPTION_NAME . "[attribute_step_{$index}]";

		?>
		<select name="<?php echo esc_attr( $field_name ); ?>">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $args['label'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['label'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the order statuses field.
	 *
	 * @return void
	 */
	public static function render_status_field(): void {
		$statuses        = wc_get_order_statuses();
		$selected_status = SettingsRepository::get_statuses();

		foreach ( $statuses as $status_key => $status_label ) :
			?>
			<label>
				<input
					type="checkbox"
					name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[order_statuses][]"
					value="<?php echo esc_attr( $status_key ); ?>"
					<?php checked( in_array( $status_key, $selected_status, true ) ); ?>
				/>
				<?php echo esc_html( $status_label ); ?>
			</label>
			<br/>
			<?php
		endforeach;

		?>
		<p class="description">
			<?php esc_html_e( 'Only orders in the selected statuses will contribute to counts and results.', 'order-categorize' ); ?>
		</p>
		<?php
	}

	/**
	 * Retrieve the hook suffix for the settings page.
	 *
	 * @return string|null
	 */
	public static function get_hook_suffix(): ?string {
		return self::$hook_suffix;
	}
}
