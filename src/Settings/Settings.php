<?php

namespace GeminiLabs\Pollux\Settings;

use GeminiLabs\Pollux\Application;
use GeminiLabs\Pollux\Facades\SiteMeta;
use GeminiLabs\Pollux\Helper;
use GeminiLabs\Pollux\MetaBox\MetaBox;
use GeminiLabs\Pollux\Settings\RWMetaBox;

class Settings extends MetaBox
{
	/**
	 * @var string
	 */
	const ID = 'settings';

	/**
	 * @var array
	 */
	public static $conditions = [
		'class_exists', 'defined', 'function_exists', 'hook', 'is_plugin_active',
		'is_plugin_inactive',
	];

	/**
	 * @var string
	 */
	public $hook;

	/**
	 * @return string
	 */
	public static function id()
	{
		return apply_filters( sprintf( 'pollux/%s/id', static::ID ), Application::prefix() . static::ID );
	}

	/**
	 * {@inheritdoc}
	 */
	public function init()
	{
		// @todo: run GateKeeper to check dependencies and capability (make sure it it run on the correct hook!)
		// if( !is_plugin_active( 'meta-box/meta-box.php' ))return;

		$this->normalize( $this->app->config[ static::ID ]);

		add_action( 'admin_menu',                        [$this, 'addPage'] );
		add_action( 'pollux/'.static::ID.'/init',        [$this, 'addSubmitMetaBox'] );
		add_action( 'current_screen',                    [$this, 'register'] );
		add_action( 'admin_menu',                        [$this, 'registerSetting'] );
		add_action( 'pollux/'.static::ID.'/init',        [$this, 'reset'] );
		add_action( 'admin_print_footer_scripts',        [$this, 'renderFooterScript'] );
		add_filter( 'pollux/'.static::ID.'/instruction', [$this, 'filterInstruction'], 10, 3 );
	}

	/**
	 * @return void
	 */
	public function action()
	{
		$args = func_get_args();
		$hook = sprintf( 'pollux/%s/%s', static::ID, array_shift( $args ));
		return do_action_ref_array( $hook, $args );
	}

	/**
	 * @return void
	 * @action admin_menu
	 */
	public function addPage()
	{
		$this->hook = call_user_func_array( 'add_menu_page', $this->filter( 'page', [
			__( 'Site Settings', 'pollux' ),
			__( 'Site Settings', 'pollux' ),
			'edit_theme_options',
			static::id(),
			[$this, 'renderPage'],
			'dashicons-screenoptions',
			1313
		]));
	}

	/**
	 * @return void
	 * @action pollux/{static::ID}/init
	 */
	public function addSubmitMetaBox()
	{
		call_user_func_array( 'add_meta_box', $this->filter( 'metabox/submit', [
			'submitdiv',
			__( 'Save Settings', 'pollux' ),
			[$this, 'renderSubmitMetaBox'],
			$this->hook,
			'side',
			'high',
		]));
	}

	/**
	 * @return mixed
	 */
	public function filter()
	{
		$args = func_get_args();
		$hook = sprintf( 'pollux/%s/%s', static::ID, array_shift( $args ));
		return apply_filters_ref_array( $hook, $args );
	}

	/**
	 * @param string $instruction
	 * @return string
	 * @action pollux/{static::ID}/instruction
	 */
	public function filterInstruction( $instruction, array $field, array $metabox )
	{
		return sprintf( "SiteMeta::%s('%s');", $metabox['slug'], $field['slug'] );
	}

	/**
	 * @param null|array $settings
	 * @return array
	 * @callback register_setting
	 */
	public function filterSavedSettings( $settings )
	{
		if( is_null( $settings )) {
			$settings = [];
		}
		return $this->filter( 'save', $settings );
	}

	/**
	 * @return void
	 * @action current_screen
	 */
	public function register()
	{
		if(( new Helper )->getCurrentScreen()->id != $this->hook )return;
		foreach( parent::register() as $metabox ) {
			new RWMetaBox( $metabox, static::ID, $this->hook );
		}
		add_screen_option( 'layout_columns', [
			'max' => 2,
			'default' => 2,
		]);
		$this->action( 'init' );
	}

	/**
	 * @return void
	 * @action admin_menu
	 */
	public function registerSetting()
	{
		register_setting( static::id(), static::id(), [$this, 'filterSavedSettings'] );
	}

	/**
	 * @return void
	 * @action admin_print_footer_scripts
	 */
	public function renderFooterScript()
	{
		if(( new Helper )->getCurrentScreen()->id != $this->hook )return;
		$this->render( 'settings/script', [
			'confirm' => __( 'Are you sure want to do this?', 'pollux' ),
			'hook' => $this->hook,
			'id' => static::id(),
		]);
	}

	/**
	 * @return void
	 * @callback add_menu_page
	 */
	public function renderPage()
	{
		$this->render( 'settings/index', [
			'columns' => get_current_screen()->get_columns(),
			'heading' => __( 'Site Settings', 'pollux' ),
			'id' => static::id(),
		]);
	}

	/**
	 * @return void
	 * @callback add_meta_box
	 */
	public function renderSubmitMetaBox()
	{
		global $pagenow;
		$query = [
			'_wpnonce' => wp_create_nonce( $this->hook ),
			'action' => 'reset',
			'page' => static::id(),
		];
		$this->render( 'settings/submit', [
			'reset' => __( 'Reset all', 'pollux' ),
			'reset_url' => esc_url( add_query_arg( $query, admin_url( $pagenow ))),
			'submit' => get_submit_button( __( 'Save', 'pollux' ), 'primary', 'submit', false ),
		]);
	}

	/**
	 * @return void
	 * @action pollux/{static::ID}/init
	 */
	public function reset()
	{
		if( filter_input( INPUT_GET, 'page' ) !== static::id()
			|| filter_input( INPUT_GET, 'action' ) !== 'reset'
		)return;
		if( wp_verify_nonce( filter_input( INPUT_GET, '_wpnonce' ), $this->hook )) {
			update_option( static::id(), $this->getDefaults() );
			add_settings_error( static::id(), 'reset', __( 'Reset successful.', 'pollux' ), 'updated' );
		}
		else {
			add_settings_error( static::id(), 'failed', __( 'Failed to reset. Please try again.', 'pollux' ));
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true',  wp_get_referer() ));
		exit;
	}

	/**
	 * @param string $key
	 * @return array
	 */
	protected function filterArrayByKey( array $array, $key )
	{
		return array_filter( $array, function( $value ) use( $key ) {
			return !empty( $value[$key] );
		});
	}

	/**
	 * @return array
	 */
	protected function getDefaults()
	{
		$metaboxes = $this->filterArrayByKey( $this->metaboxes, 'slug' );

		array_walk( $metaboxes, function( &$metabox ) {
			$fields = array_map( function( $field ) {
				$field = wp_parse_args( $field, ['std' => ''] );
				return [$field['slug'] => $field['std']];
			}, $this->filterArrayByKey( $metabox['fields'], 'slug' ));
			$metabox = [
				$metabox['slug'] => call_user_func_array( 'array_merge', $fields ),
			];
		});
		return call_user_func_array( 'array_merge', $metaboxes );
	}

	/**
	 * @return string|array
	 */
	protected function getValue( $key, $group )
	{
		return SiteMeta::get( $group, $key, false );
	}

	/**
	 * @param string $name
	 * @param string $parentId
	 * @return string
	 */
	protected function normalizeFieldName( $name, array $data, $parentId )
	{
		return sprintf( '%s[%s][%s]', static::id(), $parentId, $data['slug'] );
	}

	/**
	 * @param string $id
	 * @param string $parentId
	 * @return string
	 */
	protected function normalizeId( $id, array $data, $parentId )
	{
		return $parentId == $id
			? sprintf( '%s-%s', static::id(), $id )
			: sprintf( '%s-%s-%s', static::id(), $parentId, $id );
	}
}
