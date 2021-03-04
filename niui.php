<?php
/**
 * Plugin Name: Naran Image URL Interpolator
 * Description: Your local development website helper. All your attachehd local images URLs are substituted with your real server URLS.
 * Author:      Changwoo
 * Author URI:  mailto://cs.chwnam@gmail.com
 * Version:     1.0.0
 */

if ( 'local' === wp_get_environment_type() &&
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
					$sources[ $key ]['url'] = str_replace(
						$this->home_url,
						'https://blog.cheil.com',
						$sources[ $key ]['url']
					);
				}
			}

			return $sources;
		}

		public function image_content( string $content ): string {
			$home_url = preg_quote( $this->home_url, '/' );
			$regex    = "/\s+src=(\"|'){$home_url}(.+?)(\"|')\s+/";
			$content  = preg_replace( $regex, " src={$this->host}\$2 ", $content );

			return $content;
		}

		private function is_image_exist( int $attachment_id ): bool {
			return $attachment_id &&
			       file_exists( $this->basedir . '/' . get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		}
	};
}

