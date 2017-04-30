<?php

namespace GeminiLabs\Pollux\PostType;

use GeminiLabs\Pollux\Helper;
use GeminiLabs\Pollux\PostMeta;

trait Columns
{
	/**
	 * @var array
	 */
	public $columns = [];

	/**
	 * @var array
	 */
	public $types = [];

	/**
	 * @var Application
	 */
	protected $app;

	/**
	 * @var void
	 */
	public function initColumns()
	{
		foreach( $this->types as $type => $args ) {
			add_action( "manage_{$type}_posts_custom_column", [$this, 'printColumnValue'], 10, 2 );
			add_filter( "manage_{$type}_posts_columns", function( $columns ) use( $args ) {
				return count( $args['columns'] ) > 1
					? $args['columns']
					: $columns;
			});
		}
	}

	/**
	 * @param string $name
	 * @param int $postId
	 * @return void
	 * @action manage_{$type}_posts_custom_column
	 */
	public function printColumnValue( $name, $postId )
	{
		$method = ( new Helper )->buildMethodName( $name, 'getColumn' );
		echo method_exists( $this, $method )
			? $this->$method( $postId )
			: apply_filters( "pollux/post_type/column/{$name}", '' );
	}

	/**
	 * @param int $postId
	 * @return string
	 */
	protected function getColumnImage( $postId )
	{
		if( has_post_thumbnail( $postId ) ) {
			list( $src, $width, $height ) = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), [96, 48] );
			$image = sprintf( '<img src="%s" alt="%s" width="%s" height="%s">',
				esc_url( set_url_scheme( $src )),
				esc_attr( get_the_title( $postId )),
				$width,
				$height
			);
		}
		return empty( $image )
			? '&mdash;'
			: $image;
	}

	/**
	 * @return int
	 */
	protected function getColumnMedia()
	{
		return count(( new PostMeta )->get( 'media', [
			'fallback' => [],
			'single' => false,
		]));
	}

	/**
	 * @param int $postId
	 * @return string
	 */
	protected function getColumnSlug( $postId )
	{
		return get_post( $postId )->post_name;
	}

	/**
	 * @return array
	 */
	protected function normalizeColumns( array $columns )
	{
		$columns = array_flip( $columns );
		$columns = array_merge( $columns, array_intersect_key( $this->columns, $columns ));
		return ['cb' => '<input type="checkbox">'] + $columns;
	}

	/**
	 * @return void
	 */
	protected function setColumns()
	{
		$comments = sprintf(
			'<span class="vers comment-grey-bubble" title="%1$s"><span class="screen-reader-text">%1$s</span></span>',
			$this->app->config['columns']['comments']
		);
		$columns = wp_parse_args( $this->app->config['columns'], [
			'comments' => $comments,
		]);
		$this->columns = apply_filters( 'pollux/post_type/columns', $columns );
	}
}