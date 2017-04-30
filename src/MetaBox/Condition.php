<?php

namespace GeminiLabs\Pollux\MetaBox;

use GeminiLabs\Pollux\Helper;

trait Condition
{
	/**
	 * @var Application
	 */
	protected $app;

	protected static $conditions = [
		'class_exists', 'defined', 'function_exists', 'hook', 'is_front_page', 'is_home',
		'is_page_template', 'is_plugin_active', 'is_plugin_inactive',
	];

	/**
	 * @return bool
	 */
	public function validate( array $conditions )
	{
		array_walk( $conditions, function( &$value, $key ) {
			$method = ( new Helper )->buildMethodName( $key, 'validate' );
			$value = method_exists( $this, $method )
				? $this->$method( $value )
				: $this->validateUnknown( $key, $value );
		});
		return !in_array( false, $conditions );
	}

	/**
	 * @param mixed $conditions
	 * @return array
	 */
	protected function normalizeCondition( $conditions )
	{
		$conditions = ( new Helper )->toArray( $conditions );
		if( count( array_filter( array_keys( $conditions ), 'is_string' )) == 0 ) {
			foreach( $conditions as $key ) {
				$conditions[str_replace( '!', '', $key )] = substr( $key, 0, 1 ) == '!' ? 0 : 1;
			}
			$conditions = array_filter( $conditions, function( $key ) {
				return !is_numeric( $key );
			}, ARRAY_FILTER_USE_KEY );
		}
		$hook = sprintf( 'pollux/%s/conditions', ( new Helper )->getClassname() );
		return array_intersect_key(
			$conditions,
			array_flip( apply_filters( $hook, static::$conditions ))
		);
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function validateClassExists( $value )
	{
		return class_exists( $value );
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function validateDefined( $value )
	{
		return defined( $value );
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function validateFunctionExists( $value )
	{
		return function_exists( $value );
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function validateHook( $value )
	{
		return apply_filters( $value, true );
	}

	/**
	 * @param bool $value
	 * @return bool
	 */
	protected function validateIsFrontPage( $value )
	{
		return $value == ( $this->getPostId() == get_option( 'page_on_front' ));
	}

	/**
	 * @param bool $value
	 * @return bool
	 */
	protected function validateIsHome( $value )
	{
		return $value == ( $this->getPostId() == get_option( 'page_for_posts' ));
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function validateIsPageTemplate( $value )
	{
		return basename( get_page_template_slug( $this->getPostId() )) == $value;
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function validateIsPluginActive( $value )
	{
		return is_plugin_active( $value );
	}

	/**
	 * @param string $value
	 * @return bool
	 */
	protected function validateIsPluginInactive( $value )
	{
		return is_plugin_inactive( $value );
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return bool
	 */
	protected function validateUnknown( $key, $value )
	{
		return apply_filters( 'pollux/metabox/condition', true, $key, $value );
	}

	/**
	 * @return int
	 */
	abstract protected function getPostId();
}
