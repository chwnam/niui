<?php
/**
 * Plugin Name: Naran Image URL Interpolator
 * Description: All your attachehd local images URLs are substituted with your real server URLS.
 * Author:      Changwoo
 * Author URI:  mailto://cs.chwnam@gmail.com
 * Version:     1.0.2
 * Plugin URI:  https://github.com/chwnam/niui
 * License:     GPLv2 or later
 */

if (
	in_array( wp_get_environment_type(), [ 'local', 'development' ], true ) &&
	defined( 'NIUI_HOST' ) && ( $host = parse_url( NIUI_HOST ) ) && ( $host['scheme'] && $host['host'] )
) {
	new class( "{$host['scheme']}://{$host['host']}" ) {

		private $basedir = '';
		private $home_url = '';
		private $host = '';

		public function __construct( string $host ) {
			$upload_dirs = wp_get_upload_dir();

			$this->basedir  = $upload_dirs['basedir'];
			$this->home_url = untrailingslashit( home_url() );
			$this->host     = untrailingslashit( $host );

			add_filter( 'wp_get_attachment_image_src', [ $this, 'attachment_image_src' ], 9999, 2 );
			add_filter( 'wp_calculate_image_srcset', [ $this, 'image_srcset' ], 9999, 5 );
			add_filter( 'the_content', [ $this, 'image_content' ], 9999 );
			add_filter( 'wp_prepare_attachment_for_js', [ $this, 'attachment_for_js' ], 9999, 2 );
		}

		public function attachment_image_src( $image, $attachment_id ) {
			if ( ! $this->is_image_exist( $attachment_id ) ) {
				$image[0] = str_replace( $this->home_url, $this->host, $image[0] );
			}

			return $image;
		}

		public function image_srcset( array $sources ): array {
			$attachment_id = func_get_arg( 4 );

			if ( ! $this->is_image_exist( $attachment_id ) ) {
				foreach ( $sources as $key => $source ) {
					$sources[ $key ]['url'] = str_replace( $this->home_url, $this->host, $sources[ $key ]['url'] );
				}
			}

			return $sources;
		}

		public function image_content( string $content ): string {
			$home_url = preg_quote( $this->home_url, '/' );
			$regex    = "/\s+src=(\"|'){$home_url}(.+?)(\"|')\s+/";

			$content = preg_replace_callback( $regex, function( $matches ) {
				$maybe_file = untrailingslashit( ABSPATH ) . $matches[2];
				if ( file_exists( $maybe_file ) ) {
					return $matches[0];
				} else {
					return " src={$this->host}{$matches[2]}";
				}
			}, $content);

			return $content;
		}

		public function attachment_for_js( array $response, WP_Post $attachment ): array {
			if ( ! $this->is_image_exist( $attachment->ID ) ) {
				$response['url'] = str_replace( $this->home_url, $this->host, $response['url'] );
				foreach ( array_keys( $response['sizes'] ) as $size ) {
					$response['sizes'][ $size ]['url'] = str_replace(
						$this->home_url,
						$this->host,
						$response['sizes'][ $size ]['url']
					);
				}
			}

			return $response;
		}

		private function is_image_exist( int $attachment_id ): bool {
			return $attachment_id &&
			       file_exists( $this->basedir . '/' . get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		}
	};
}

