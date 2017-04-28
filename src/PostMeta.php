<?php

namespace GeminiLabs\Pollux;

use GeminiLabs\Pollux\Application;

class PostMeta
{
	public function get( $metaKey, array $args = [] )
	{
		if( empty( $metaKey ))return;

		$args = $this->normalize( $args );
		$metaKey = $this->buildMetaKey( $metaKey, $args['prefix'] );
		$metaValue = get_post_meta( $args['id'], $metaKey, $args['single'] );

		if( is_string( $metaValue )) {
			$metaValue = trim( $metaValue );
		}
		return empty( $metaValue )
			? $args['fallback']
			: $metaValue;
	}

	protected function buildMetaKey( $metaKey, $prefix )
	{
		return ( substr( $metaKey, 0, 1 ) == '_' && !empty( $prefix ))
			? sprintf( '_%s%s', rtrim( $prefix, '_' ), $metaKey )
			: $prefix . $metaKey;
	}

	protected function normalize( array $args )
	{
		$defaults = [
			'id'       => get_the_ID(),
			'fallback' => '',
			'single'   => true,
			'prefix'   => Application::PREFIX,
		];
		return shortcode_atts( $defaults, array_change_key_case( $args ));
	}
}
