<?php

namespace GeminiLabs\Pollux\PostType;

use GeminiLabs\Pollux\Application;
use GeminiLabs\Pollux\Facades\ArchiveMeta;
use GeminiLabs\Pollux\Helper;
use GeminiLabs\Pollux\Settings\Settings;

class Archive extends Settings
{
	/**
	 * @var string
	 */
	CONST ID = 'archives';

	public $hooks = [];

	/**
	 * {@inheritdoc}
	 */
	public function init()
	{
		parent::init();

		add_action( 'pollux/archives/init',              [$this, 'registerFeaturedImageMetaBox'] );
		add_action( 'pollux/archives/editor',            [$this, 'renderEditor'], 10, 2 );
		add_filter( 'pollux/archives/metabox/submit',    [$this, 'filterSubmitMetaBox'] );
		add_filter( 'pollux/archives/show/instructions', '__return_true' );
	}

	/**
	 * @return void
	 * @action admin_menu
	 */
	public function addPage()
	{
		foreach( $this->getPostTypesWithArchive() as $type => $page ) {
			$labels = get_post_type_labels( get_post_type_object( $type ));
			$this->hooks[$type] = call_user_func_array( 'add_submenu_page', $this->filter( 'page', [
				$page,
				sprintf( _x( '%s Archive', 'post archive', 'pollux' ), $labels->singular_name ),
				__( 'Archive', 'pollux' ),
				'edit_theme_options',
				sprintf( '%s_archive', $type ),
				[$this, 'renderPage'],
			]));
		}
	}

	/**
	 * @return string
	 * @filter pollux/{static::ID}/before/instructions
	 */
	public function filterBeforeInstructions()
	{
		return sprintf( '<pre class="my-sites nav-tab-active misc-pub-section">%s</pre>',
			array_reduce( ['title', 'content', 'featured'], function( $instructions, $id ) {
				return $instructions . $this->filterInstruction( null, ['slug' => $id], ['slug' => $this->getPostType()] ) . PHP_EOL;
			})
		);
	}

	/**
	 * @param string $instruction
	 * @return string
	 * @action pollux/{static::ID}/instruction
	 */
	public function filterInstruction( $instruction, array $field, array $metabox )
	{
		return sprintf( "ArchiveMeta::%s('%s');", $metabox['slug'], $field['slug'] );
	}

	/**
	 * @return array
	 * @action pollux/{static::ID}/metabox/submit
	 */
	public function filterSubmitMetaBox( array $args )
	{
		$args[1] = __( 'Save Archive', 'pollux' );
		return $args;
	}

	/**
	 * @param string $key
	 * @param mixed $fallback
	 * @param string $group
	 * @return string|array
	 */
	public function getMetaValue( $key, $fallback = '', $group = '' )
	{
		return ArchiveMeta::get( $key, $fallback, $group );
	}

	/**
	 * @return void
	 * @action current_screen
	 */
	public function register()
	{
		$screenId = ( new Helper )->getCurrentScreen()->id;
		if( in_array( $screenId, $this->hooks )) {
			$this->hook = $screenId;
		}
		parent::register();
	}

	/**
	 * @return void
	 * @action pollux/archives/init
	 */
	public function registerFeaturedImageMetaBox()
	{
		if( !current_user_can( 'upload_files' ))return;
		add_meta_box( 'postimagediv', __( 'Featured Image', 'pollux' ), [$this, 'renderFeaturedImageMetaBox'], null, 'side', 'low' );
	}

	/**
	 * @return void
	 * @action pollux/archives/editor
	 */
	public function renderEditor( $content, $type )
	{
		wp_editor( $content, 'content', [
			'_content_editor_dfw' => true,
			'drag_drop_upload' => true,
			'editor_height' => 300,
			'tabfocus_elements' => 'content-html, publishing-action',
			'textarea_name' => sprintf( '%s[%s][content]', static::id(), $type ),
			'tinymce' => [
				'add_unload_trigger' => false,
				'resize' => false,
				'wp_autoresize_on' => true,
			],
		]);
	}

	/**
	 * @return void
	 * @callback add_meta_box
	 */
	public function renderFeaturedImageMetaBox()
	{
		$imageId = ArchiveMeta::get( 'featured', -1, $this->getPostType() );
		$imageSize = isset( wp_get_additional_image_sizes()['post-thumbnail'] )
			? 'post-thumbnail'
			: [266, 266];
		$thumbnail = get_post( $imageId )
			? wp_get_attachment_image( $imageId, $imageSize )
			: __( 'Set Featured Image', 'pollux' );

		$this->render( 'archive/featured', [
			'edit_image' => __( 'Click the image to edit or update', 'pollux' ),
			'id' => static::id(),
			'image_id' => $imageId,
			'post_type' => $this->getPostType(),
			'remove_image' => __( 'Remove featured image', 'pollux' ),
			'thickbox_url' => '',
			'thumbnail' => $thumbnail,
		]);
	}

	/**
	 * @return void
	 * @callback add_menu_page
	 */
	public function renderPage()
	{
		$type = $this->getPostType();
		if( empty( $type ))return;
		$labels = get_post_type_labels( get_post_type_object( $type ));
		$this->render( 'archive/index', [
			'columns' => get_current_screen()->get_columns(),
			'content' => ArchiveMeta::get( 'content', '', $type ),
			'heading' => sprintf( _x( '%s Archive', 'post archive', 'pollux' ), $labels->singular_name ),
			'id' => static::id(),
			'post_type' => $type,
			'title' => ArchiveMeta::get( 'title', '', $type ),
		]);
	}

	/**
	 * @return array
	 */
	protected function getDefaults()
	{
		return [];
	}

	/**
	 * @return string
	 */
	protected function getPostType()
	{
		$type = array_search( $this->hook, $this->hooks );
		return !empty( $type ) && is_string( $type )
			? $type
			: '';
	}

	/**
	 * @return array
	 */
	protected function getPostTypesWithArchive()
	{
		$types = array_map( function( $value ) {
			return sprintf( 'edit.php?post_type=%s', $value );
		}, get_post_types( ['has_archive' => 1] ));
		return array_merge( $types, ['post' => 'edit.php'] );
	}

	/**
	 * @return array
	 */
	protected function getSettings()
	{
		return (array) ArchiveMeta::all();
	}
}
