<?php
/**
 * Base class for Bridgistic admin screens.
 *
 * Each page prepares data, then renders its view inside the shared shell
 * (admin/views/layout.php). Views receive `$data` and escape all output.
 *
 * @package Bridgistic
 */

declare( strict_types=1 );

namespace Bridgistic\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Page {

	protected string $slug;
	protected string $title;

	public function __construct( string $slug, string $title ) {
		$this->slug  = $slug;
		$this->title = $title;
	}

	/** View file name (without .php) under admin/views/. */
	abstract protected function view(): string;

	/**
	 * Data passed to the view.
	 *
	 * @return array<string,mixed>
	 */
	abstract protected function data(): array;

	public function render(): void {
		$data = $this->data();
		$view = __DIR__ . '/views/' . $this->view() . '.php';
		include __DIR__ . '/views/layout.php';
	}

	public function slug(): string {
		return $this->slug;
	}

	public function title(): string {
		return $this->title;
	}

	// ---- view helpers ----------------------------------------------------------

	/**
	 * Inline SVG icon (stroke = currentColor). Central set keeps views clean.
	 */
	public static function icon( string $name, int $size = 18 ): string {
		$paths = array(
			'bridge'    => 'M2 15h20M4 15V9m16 6V9M2 9c3-4 7-6 10-6s7 2 10 6M8 15v-3m4 3v-4m4 4v-3',
			'dashboard' => 'M3 3h8v8H3zM13 3h8v5h-8zM13 12h8v9h-8zM3 15h8v6H3z',
			'bolt'      => 'M13 2 4 14h6l-1 8 9-12h-6l1-8z',
			'key'       => 'M15 9a6 6 0 1 0-5.7 6L11 17h2v2h2v2h3v-3l-5.3-5.3A6 6 0 0 0 15 9zM15.5 8.5h.01',
			'shield'    => 'M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3zM9 12l2 2 4-4',
			'pulse'     => 'M2 12h4l2-7 4 14 3-9 1.5 2H22',
			'list'      => 'M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01',
			'camera'    => 'M3 8a2 2 0 0 1 2-2h2l2-3h6l2 3h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8zM12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
			'play'      => 'M6 4l14 8-14 8V4z',
			'download'  => 'M12 3v12m0 0 4-4m-4 4-4-4M4 17v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3',
			'lock'      => 'M6 11V8a6 6 0 1 1 12 0v3M5 11h14v10H5V11zm7 4v3',
			'gear'      => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm8-3.5a8 8 0 0 0-.2-1.6l2-1.5-2-3.4-2.3 1a8 8 0 0 0-2.8-1.7L14.5 2h-5l-.2 2.3a8 8 0 0 0-2.8 1.7l-2.3-1-2 3.4 2 1.5a8 8 0 0 0 0 3.2l-2 1.5 2 3.4 2.3-1a8 8 0 0 0 2.8 1.7l.2 2.3h5l.2-2.3a8 8 0 0 0 2.8-1.7l2.3 1 2-3.4-2-1.5c.1-.5.2-1 .2-1.6z',
			'check'     => 'M4 12.5 9.5 18 20 6.5',
			'warn'      => 'M12 3 1.5 21h21L12 3zm0 7v5m0 3h.01',
			'x'         => 'M5 5l14 14M19 5 5 19',
			'info'      => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zm0-14h.01M12 12v6',
			'copy'      => 'M9 9h11v11H9V9zM5 15H4V4h11v1',
			'refresh'   => 'M21 12a9 9 0 1 1-2.6-6.4M21 3v6h-6',
			'plug'      => 'M9 2v6M15 2v6M7 8h10v4a5 5 0 0 1-5 5v0a5 5 0 0 1-5-5V8zM12 17v5',
			'clock'     => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zm0-14v5l3 3',
			'sparkle'   => 'M12 2l2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6zM19 15l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z',
			'globe'     => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM2 12h20M12 2c3 3 4 6.5 4 10s-1 7-4 10c-3-3-4-6.5-4-10s1-7 4-10z',
			'code'      => 'M8 6 2 12l6 6M16 6l6 6-6 6',
			'desktop'   => 'M3 4h18v12H3V4zm5 16h8m-4-4v4',
			'arrow'     => 'M5 12h14m0 0-6-6m6 6-6 6',
			'eye'       => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
			'users'     => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm13 10v-2a4 4 0 0 0-3-3.9M15.5 3.3a4 4 0 0 1 0 7.5',
			'tag'       => 'M20.6 13.4 11 3H3v8l9.6 10.4a2 2 0 0 0 2.8 0l5.2-5.2a2 2 0 0 0 0-2.8zM7.5 7.5h.01',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="bridgistic-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%2$s"/></svg>',
			$size,
			esc_attr( $paths[ $name ] )
		);
	}

	/**
	 * Status badge. $status one of: pass|ok, warn, fail|error, info, muted.
	 */
	public static function badge( string $status, string $text, bool $pulse = false ): string {
		$map = array(
			'pass'  => 'pass',
			'ok'    => 'pass',
			'warn'  => 'warn',
			'fail'  => 'fail',
			'error' => 'fail',
			'info'  => 'info',
			'muted' => 'muted',
		);
		$class = $map[ $status ] ?? 'muted';
		return sprintf(
			'<span class="bridgistic-badge is-%1$s%2$s">%3$s</span>',
			esc_attr( $class ),
			$pulse ? ' is-pulse' : '',
			esc_html( $text )
		);
	}

	/** Human "x ago" for a UTC MySQL datetime, or an em dash. */
	public static function ago( ?string $mysql_utc ): string {
		if ( ! $mysql_utc ) {
			return '—';
		}
		$ts = strtotime( $mysql_utc . ' UTC' );
		if ( ! $ts ) {
			return '—';
		}
		/* translators: %s: human-readable time difference. */
		return sprintf( __( '%s ago', 'bridgistic' ), human_time_diff( $ts ) );
	}
}
