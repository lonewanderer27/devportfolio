<?php
/**
 * Page names from a PDF's outline (bookmarks) (DPB-030).
 *
 * Ghostscript, Imagick and the host have no outline reader, so this is a small read-only PDF
 * parser: it finds the objects by scanning for `N G obj`, reads the catalog, the page tree and
 * the outline, and maps each bookmark to a page. It never executes anything, never follows a
 * file or URL, and gives up (an empty result) on anything it does not understand: encrypted
 * files, huge files, a time or entry cap, malformed structure. A failure here never fails a
 * conversion.
 *
 * Result shape: a list of ranges `[ [ first_page, title ], ... ]` sorted by page (1-based). A
 * range names its page and every page after it until the next range. Where several bookmarks
 * start on one page, the last one in document order wins (the deepest). {@see self::expand()}
 * turns ranges into one string per page.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/** A PDF name object (`/Type`). */
final class CPTDP_Pdf_Name {
	/** @var string */
	public $v;

	/** @param string $v Name without the slash. */
	public function __construct( string $v ) {
		$this->v = $v;
	}
}

/** A PDF string object. */
final class CPTDP_Pdf_Str {
	/** @var string */
	public $v;

	/** @param string $v Raw bytes. */
	public function __construct( string $v ) {
		$this->v = $v;
	}
}

/** A PDF indirect reference. */
final class CPTDP_Pdf_Ref {
	/** @var int */
	public $n;

	/** @param int $n Object number. */
	public function __construct( int $n ) {
		$this->n = $n;
	}
}

/**
 * Outline reader.
 */
class CPTDP_Page_Outline {

	const MAX_BYTES   = 52428800; // 50 MB.
	const MAX_ENTRIES = 2000;
	const MAX_PAGES   = 5000;
	const MAX_TITLE   = 200;

	/** @var string */
	private $data = '';

	/** @var int[] Object number => offset after "obj". */
	private $offsets = array();

	/** @var array<int,mixed> */
	private $cache = array();

	/** @var array<int,array{0:int,1:int}>|null Object number => [ object stream number, index ]. */
	private $in_stream = null;

	/** @var array<int,string> Decoded object streams. */
	private $stream_data = array();

	/** @var float */
	private $deadline = 0.0;

	/**
	 * Names of the outline of a PDF, as ranges.
	 *
	 * @param string $path       PDF path.
	 * @param int    $page_count Page count found by the converter; a PDF whose page tree disagrees gets no names.
	 * @return array<int,array{0:int,1:string}>
	 */
	public static function ranges( string $path, int $page_count ): array {
		if ( $page_count < 1 || ! is_readable( $path ) ) {
			return array();
		}

		/**
		 * Largest PDF (bytes) whose outline is read.
		 *
		 * @param int $bytes Default 50 MB.
		 */
		$max = (int) apply_filters( 'cptdp_outline_max_bytes', self::MAX_BYTES );
		$size = (int) filesize( $path );

		if ( $size < 8 || $size > $max ) {
			return array();
		}

		/**
		 * Seconds the outline reader may take.
		 *
		 * @param int $seconds Default 15.
		 */
		$seconds = (int) apply_filters( 'cptdp_outline_timeout', 15 );
		$reader  = new self();

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$reader->data     = (string) file_get_contents( $path );
			$reader->deadline = microtime( true ) + max( 1, $seconds );

			return $reader->read( $page_count );
		} catch ( Throwable $e ) {
			return array();
		}
	}

	/**
	 * One name per page from ranges ('' where there is none).
	 *
	 * @param array<int,array{0:int,1:string}> $ranges Ranges.
	 * @param int                              $count  Page count.
	 * @return string[]
	 */
	public static function expand( array $ranges, int $count ): array {
		if ( $count < 1 || ! $ranges ) {
			return array();
		}

		$names = array_fill( 0, $count, '' );
		$last  = count( $ranges ) - 1;

		foreach ( $ranges as $i => $range ) {
			$first = max( 1, (int) $range[0] );
			$end   = $i < $last ? (int) $ranges[ $i + 1 ][0] - 1 : $count;

			for ( $p = $first; $p <= min( $end, $count ); $p++ ) {
				$names[ $p - 1 ] = (string) $range[1];
			}
		}

		return $names;
	}

	/**
	 * Clean ranges read back from storage (or sent by the browser).
	 *
	 * @param mixed $ranges Raw value.
	 * @param int   $count  Page count.
	 * @return array<int,array{0:int,1:string}>
	 */
	public static function sanitize_ranges( $ranges, int $count ): array {
		if ( ! is_array( $ranges ) || $count < 1 ) {
			return array();
		}

		$clean = array();

		foreach ( array_slice( $ranges, 0, self::MAX_ENTRIES ) as $range ) {
			if ( ! is_array( $range ) || ! isset( $range[0], $range[1] ) || ! is_scalar( $range[0] ) || ! is_scalar( $range[1] ) ) {
				continue;
			}

			$page  = (int) $range[0];
			$title = self::clean_title( (string) $range[1] );

			if ( $page < 1 || $page > $count || '' === $title ) {
				continue;
			}

			$clean[] = array( $page, $title );
		}

		usort(
			$clean,
			static function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);

		// One range per page: the last one given wins.
		$by_page = array();

		foreach ( $clean as $range ) {
			$by_page[ $range[0] ] = $range;
		}

		return array_values( $by_page );
	}

	/**
	 * Plain, single-line, bounded title.
	 *
	 * @param string $title Raw title (UTF-8).
	 */
	public static function clean_title( string $title ): string {
		$title = wp_strip_all_tags( $title );
		$title = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $title );
		$title = trim( (string) preg_replace( '/\s+/u', ' ', $title ) );

		return mb_substr( $title, 0, self::MAX_TITLE );
	}

	/**
	 * Read the outline of the loaded PDF.
	 *
	 * @param int $page_count Expected page count.
	 * @return array<int,array{0:int,1:string}>
	 */
	private function read( int $page_count ): array {
		if ( 0 !== strpos( $this->data, '%PDF-' ) ) {
			return array();
		}

		// Encrypted files: strings in the outline are encrypted, so there is nothing to read.
		if ( preg_match( '/\/Encrypt\s*(?:\d+\s+\d+\s+R|<<)/', $this->data ) ) {
			return array();
		}

		if ( preg_match_all( '/(?<![0-9])(\d{1,9})[ \t\r\n]+\d{1,5}[ \t\r\n]+obj(?![a-zA-Z])/', $this->data, $found, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $found[1] as $i => $number ) {
				// Later definitions replace earlier ones (incremental updates).
				$this->offsets[ (int) $number[0] ] = $found[0][ $i ][1] + strlen( $found[0][ $i ][0] );
			}
		}

		if ( ! $this->offsets || ! preg_match_all( '/\/Root\s+(\d+)\s+\d+\s+R/', $this->data, $roots ) ) {
			return array();
		}

		$catalog = $this->obj( (int) end( $roots[1] ) );

		if ( ! is_array( $catalog ) || empty( $catalog['Outlines'] ) ) {
			return array();
		}

		$pages = $this->page_map( $catalog );

		if ( count( $pages ) !== $page_count ) {
			return array();
		}

		$outlines = $this->resolve( $catalog['Outlines'] );

		if ( ! is_array( $outlines ) || empty( $outlines['First'] ) ) {
			return array();
		}

		$named   = null;
		$entries = array();
		$stack   = array( $outlines['First'] );
		$seen    = array();

		// Document order: an item, then its children, then its next sibling.
		while ( $stack && count( $entries ) < self::MAX_ENTRIES ) {
			$this->tick();
			$ref = array_pop( $stack );

			if ( ! $ref instanceof CPTDP_Pdf_Ref || isset( $seen[ $ref->n ] ) ) {
				continue;
			}

			$seen[ $ref->n ] = true;
			$item            = $this->obj( $ref->n );

			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( ! empty( $item['Next'] ) ) {
				$stack[] = $item['Next'];
			}

			if ( ! empty( $item['First'] ) ) {
				$stack[] = $item['First'];
			}

			$title = isset( $item['Title'] ) ? $this->decode_text( $this->resolve( $item['Title'] ) ) : '';
			$dest  = $this->item_dest( $item );

			if ( null !== $dest && is_string( $dest ) && null === $named ) {
				$named = $this->named_destinations( $catalog );
			}

			$page = $this->dest_page( $dest, $pages, $named ?? array() );

			if ( '' !== $title && null !== $page ) {
				$entries[] = array( $page, $title );
			}
		}

		return self::sanitize_ranges( $entries, $page_count );
	}

	/**
	 * Abort when the time budget is spent.
	 *
	 * @throws RuntimeException When out of time.
	 */
	private function tick(): void {
		if ( microtime( true ) > $this->deadline ) {
			throw new RuntimeException( 'outline timeout' );
		}
	}

	/**
	 * The destination of an outline item: an array, a name (string) or null.
	 *
	 * @param array<string,mixed> $item Outline item.
	 * @return array<int,mixed>|string|null
	 */
	private function item_dest( array $item ) {
		$dest = isset( $item['Dest'] ) ? $this->resolve( $item['Dest'] ) : null;

		if ( null === $dest && isset( $item['A'] ) ) {
			$action = $this->resolve( $item['A'] );

			if ( is_array( $action ) && isset( $action['S'] ) && $action['S'] instanceof CPTDP_Pdf_Name && 'GoTo' === $action['S']->v && isset( $action['D'] ) ) {
				$dest = $this->resolve( $action['D'] );
			}
		}

		if ( $dest instanceof CPTDP_Pdf_Name ) {
			return $dest->v;
		}

		if ( $dest instanceof CPTDP_Pdf_Str ) {
			return $dest->v;
		}

		return is_array( $dest ) ? $dest : null;
	}

	/**
	 * 1-based page of a destination.
	 *
	 * @param mixed             $dest  Array, name or null.
	 * @param array<int,int>    $pages Page object number => 1-based page.
	 * @param array<string,mixed> $named Named destinations.
	 */
	private function dest_page( $dest, array $pages, array $named ): ?int {
		if ( is_string( $dest ) ) {
			$dest = $named[ $dest ] ?? null;

			if ( is_array( $dest ) && isset( $dest['D'] ) ) {
				$dest = $this->resolve( $dest['D'] );
			}
		}

		if ( ! is_array( $dest ) || ! isset( $dest[0] ) ) {
			return null;
		}

		$first = $dest[0];

		if ( $first instanceof CPTDP_Pdf_Ref ) {
			return $pages[ $first->n ] ?? null;
		}

		return null;
	}

	/**
	 * Named destinations: the catalog's /Dests dictionary and the /Names /Dests name tree.
	 *
	 * @param array<string,mixed> $catalog Catalog.
	 * @return array<string,mixed>
	 */
	private function named_destinations( array $catalog ): array {
		$named = array();

		if ( isset( $catalog['Dests'] ) ) {
			$dests = $this->resolve( $catalog['Dests'] );

			if ( is_array( $dests ) ) {
				foreach ( $dests as $key => $value ) {
					$named[ (string) $key ] = $this->resolve( $value );
				}
			}
		}

		$names = isset( $catalog['Names'] ) ? $this->resolve( $catalog['Names'] ) : null;
		$tree  = is_array( $names ) && isset( $names['Dests'] ) ? $names['Dests'] : null;
		$stack = null !== $tree ? array( $tree ) : array();
		$nodes = 0;

		while ( $stack && $nodes++ < 2000 ) {
			$this->tick();
			$node = $this->resolve( array_pop( $stack ) );

			if ( ! is_array( $node ) ) {
				continue;
			}

			if ( isset( $node['Names'] ) && is_array( $node['Names'] ) ) {
				$list = $node['Names'];

				for ( $i = 0; $i + 1 < count( $list ); $i += 2 ) {
					$key = $this->resolve( $list[ $i ] );

					if ( $key instanceof CPTDP_Pdf_Str ) {
						$named[ $key->v ] = $this->resolve( $list[ $i + 1 ] );
					}
				}
			}

			if ( isset( $node['Kids'] ) && is_array( $node['Kids'] ) ) {
				foreach ( $node['Kids'] as $kid ) {
					$stack[] = $kid;
				}
			}
		}

		return $named;
	}

	/**
	 * Page object number => 1-based page, from the page tree.
	 *
	 * @param array<string,mixed> $catalog Catalog.
	 * @return array<int,int>
	 */
	private function page_map( array $catalog ): array {
		$map   = array();
		$stack = isset( $catalog['Pages'] ) ? array( $catalog['Pages'] ) : array();
		$seen  = array();

		// Depth-first, kids in order: push them reversed.
		while ( $stack && count( $map ) < self::MAX_PAGES ) {
			$this->tick();
			$ref = array_pop( $stack );

			if ( ! $ref instanceof CPTDP_Pdf_Ref || isset( $seen[ $ref->n ] ) ) {
				continue;
			}

			$seen[ $ref->n ] = true;
			$node            = $this->obj( $ref->n );

			if ( ! is_array( $node ) ) {
				continue;
			}

			$kids = isset( $node['Kids'] ) ? $this->resolve( $node['Kids'] ) : null;

			if ( is_array( $kids ) ) {
				foreach ( array_reverse( $kids ) as $kid ) {
					$stack[] = $kid;
				}
			} else {
				$map[ $ref->n ] = count( $map ) + 1;
			}
		}

		return $map;
	}

	/**
	 * Follow a reference.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private function resolve( $value ) {
		$guard = 0;

		while ( $value instanceof CPTDP_Pdf_Ref && $guard++ < 10 ) {
			$value = $this->obj( $value->n );
		}

		return $value;
	}

	/**
	 * An indirect object's value.
	 *
	 * @param int $n Object number.
	 * @return mixed
	 */
	private function obj( int $n ) {
		if ( array_key_exists( $n, $this->cache ) ) {
			return $this->cache[ $n ];
		}

		$this->cache[ $n ] = null;

		if ( isset( $this->offsets[ $n ] ) ) {
			$pos                = $this->offsets[ $n ];
			$this->cache[ $n ] = $this->parse( $this->data, $pos );

			return $this->cache[ $n ];
		}

		$this->index_object_streams();

		if ( isset( $this->in_stream[ $n ] ) ) {
			list( $stream, $position ) = $this->in_stream[ $n ];
			$body                      = $this->stream_data[ $stream ] ?? '';
			$pos                       = $position;
			$this->cache[ $n ]         = '' !== $body ? $this->parse( $body, $pos ) : null;
		}

		return $this->cache[ $n ];
	}

	/**
	 * Index the objects stored in object streams (once).
	 */
	private function index_object_streams(): void {
		if ( null !== $this->in_stream ) {
			return;
		}

		$this->in_stream = array();

		foreach ( $this->offsets as $number => $offset ) {
			$this->tick();

			// Cheap test before parsing: the dictionary of an object stream says /ObjStm early.
			if ( false === strpos( substr( $this->data, $offset, 400 ), '/ObjStm' ) ) {
				continue;
			}

			$pos  = $offset;
			$dict = $this->parse( $this->data, $pos, true );

			if ( ! is_array( $dict ) || ! isset( $dict['Type'] ) || ! $dict['Type'] instanceof CPTDP_Pdf_Name || 'ObjStm' !== $dict['Type']->v ) {
				continue;
			}

			$body = $this->stream_of( $dict, $pos );
			$n    = isset( $dict['N'] ) ? (int) $dict['N'] : 0;
			$first = isset( $dict['First'] ) ? (int) $dict['First'] : 0;

			if ( null === $body || $n < 1 || $n > 100000 ) {
				continue;
			}

			$this->stream_data[ $number ] = substr( $body, $first );
			$header                       = preg_split( '/\s+/', trim( substr( $body, 0, $first ) ) );

			for ( $i = 0; $i < $n && isset( $header[ $i * 2 + 1 ] ); $i++ ) {
				$object = (int) $header[ $i * 2 ];

				// Objects that are also defined at top level win (they are newer or the same).
				if ( ! isset( $this->offsets[ $object ] ) ) {
					$this->in_stream[ $object ] = array( $number, (int) $header[ $i * 2 + 1 ] );
				}
			}
		}
	}

	/**
	 * Decoded bytes of the stream that follows a dictionary.
	 *
	 * @param array<string,mixed> $dict Stream dictionary.
	 * @param int                 $pos  Position after the dictionary.
	 */
	private function stream_of( array $dict, int $pos ): ?string {
		if ( ! preg_match( '/\G\s*stream\r?\n/', $this->data, $m, 0, $pos ) ) {
			return null;
		}

		$start  = $pos + strlen( $m[0] );
		$length = isset( $dict['Length'] ) ? $this->resolve( $dict['Length'] ) : null;

		if ( is_int( $length ) && $length >= 0 ) {
			$raw = substr( $this->data, $start, $length );
		} else {
			$end = strpos( $this->data, 'endstream', $start );
			$raw = false === $end ? '' : rtrim( substr( $this->data, $start, $end - $start ), "\r\n" );
		}

		$filter = isset( $dict['Filter'] ) ? $this->resolve( $dict['Filter'] ) : null;

		if ( is_array( $filter ) ) {
			$filter = 1 === count( $filter ) ? $this->resolve( $filter[0] ) : false;
		}

		if ( null === $filter ) {
			return $raw;
		}

		if ( ! $filter instanceof CPTDP_Pdf_Name || 'FlateDecode' !== $filter->v ) {
			return null;
		}

		$parms = isset( $dict['DecodeParms'] ) ? $this->resolve( $dict['DecodeParms'] ) : null;

		if ( is_array( $parms ) && isset( $parms['Predictor'] ) && (int) $parms['Predictor'] > 1 ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$out = @gzuncompress( $raw, 20 * 1024 * 1024 );

		return false === $out ? null : $out;
	}

	/**
	 * Parse one PDF value starting at `$pos` (advanced past it).
	 *
	 * @param string $s      Source bytes.
	 * @param int    $pos    Position.
	 * @param bool   $keep   Unused marker for callers that need the position after a dictionary.
	 * @param int    $depth  Nesting depth.
	 * @return mixed
	 */
	private function parse( string $s, int &$pos, bool $keep = false, int $depth = 0 ) {
		unset( $keep );

		if ( $depth > 40 ) {
			throw new RuntimeException( 'too deep' );
		}

		$len = strlen( $s );

		// Skip whitespace and comments.
		while ( $pos < $len ) {
			$c = $s[ $pos ];

			if ( "\0" === $c || ' ' === $c || "\t" === $c || "\r" === $c || "\n" === $c || "\f" === $c ) {
				++$pos;
			} elseif ( '%' === $c ) {
				$eol = strcspn( $s, "\r\n", $pos );
				$pos += $eol;
			} else {
				break;
			}
		}

		if ( $pos >= $len ) {
			return null;
		}

		$c = $s[ $pos ];

		if ( '<' === $c && isset( $s[ $pos + 1 ] ) && '<' === $s[ $pos + 1 ] ) {
			$pos += 2;
			$dict = array();

			while ( $pos < $len ) {
				$this->skip_space( $s, $pos );

				if ( '>' === ( $s[ $pos ] ?? '' ) && '>' === ( $s[ $pos + 1 ] ?? '' ) ) {
					$pos += 2;
					break;
				}

				$key = $this->parse( $s, $pos, false, $depth + 1 );

				if ( ! $key instanceof CPTDP_Pdf_Name ) {
					throw new RuntimeException( 'bad dictionary' );
				}

				$dict[ $key->v ] = $this->parse( $s, $pos, false, $depth + 1 );
			}

			return $dict;
		}

		if ( '[' === $c ) {
			++$pos;
			$list = array();

			while ( $pos < $len ) {
				$this->skip_space( $s, $pos );

				if ( ']' === ( $s[ $pos ] ?? '' ) ) {
					++$pos;
					break;
				}

				$list[] = $this->parse( $s, $pos, false, $depth + 1 );

				if ( count( $list ) > 100000 ) {
					throw new RuntimeException( 'too long' );
				}
			}

			return $list;
		}

		if ( '/' === $c ) {
			++$pos;
			$n    = strcspn( $s, " \t\r\n\f\0()<>[]{}/%", $pos );
			$name = substr( $s, $pos, $n );
			$pos += $n;

			if ( false !== strpos( $name, '#' ) ) {
				$name = (string) preg_replace_callback(
					'/#([0-9A-Fa-f]{2})/',
					static function ( $m ) {
						return chr( hexdec( $m[1] ) );
					},
					$name
				);
			}

			return new CPTDP_Pdf_Name( $name );
		}

		if ( '(' === $c ) {
			return $this->parse_literal_string( $s, $pos );
		}

		if ( '<' === $c ) {
			$end = strpos( $s, '>', $pos );

			if ( false === $end ) {
				throw new RuntimeException( 'bad hex string' );
			}

			$hex  = (string) preg_replace( '/[^0-9A-Fa-f]/', '', substr( $s, $pos + 1, $end - $pos - 1 ) );
			$pos  = $end + 1;
			$hex .= strlen( $hex ) % 2 ? '0' : '';

			return new CPTDP_Pdf_Str( (string) hex2bin( $hex ) );
		}

		// Numbers, references and keywords.
		if ( preg_match( '/\G(\d+)[ \t\r\n]+(\d+)[ \t\r\n]+R(?![a-zA-Z])/', $s, $m, 0, $pos ) ) {
			$pos += strlen( $m[0] );

			return new CPTDP_Pdf_Ref( (int) $m[1] );
		}

		if ( preg_match( '/\G[+-]?(?:\d+\.?\d*|\.\d+)/', $s, $m, 0, $pos ) ) {
			$pos += strlen( $m[0] );

			return false === strpos( $m[0], '.' ) ? (int) $m[0] : (float) $m[0];
		}

		if ( preg_match( '/\G(true|false|null)(?![a-zA-Z])/', $s, $m, 0, $pos ) ) {
			$pos += strlen( $m[0] );

			return 'true' === $m[1] ? true : ( 'false' === $m[1] ? false : null );
		}

		// Anything else (a keyword such as `endobj` or `stream`): consume it and return null.
		$n    = max( 1, strcspn( $s, " \t\r\n\f\0()<>[]{}/%", $pos ) );
		$pos += $n;

		return null;
	}

	/**
	 * Skip whitespace and comments.
	 *
	 * @param string $s   Source.
	 * @param int    $pos Position (advanced).
	 */
	private function skip_space( string $s, int &$pos ): void {
		$len = strlen( $s );

		while ( $pos < $len ) {
			$c = $s[ $pos ];

			if ( "\0" === $c || ' ' === $c || "\t" === $c || "\r" === $c || "\n" === $c || "\f" === $c ) {
				++$pos;
			} elseif ( '%' === $c ) {
				$pos += strcspn( $s, "\r\n", $pos );
			} else {
				break;
			}
		}

		if ( $pos >= $len ) {
			throw new RuntimeException( 'unexpected end' );
		}
	}

	/**
	 * A literal string `( ... )` with escapes and balanced parentheses.
	 *
	 * @param string $s   Source.
	 * @param int    $pos Position of the opening parenthesis (advanced).
	 */
	private function parse_literal_string( string $s, int &$pos ): CPTDP_Pdf_Str {
		$len   = strlen( $s );
		$depth = 0;
		$out   = '';
		$limit = min( $len, $pos + 65536 );

		for ( ; $pos < $limit; $pos++ ) {
			$c = $s[ $pos ];

			if ( '\\' === $c ) {
				++$pos;
				$e = $s[ $pos ] ?? '';

				if ( 'n' === $e ) {
					$out .= "\n";
				} elseif ( 'r' === $e ) {
					$out .= "\r";
				} elseif ( 't' === $e ) {
					$out .= "\t";
				} elseif ( 'b' === $e ) {
					$out .= "\x08";
				} elseif ( 'f' === $e ) {
					$out .= "\x0C";
				} elseif ( ctype_digit( $e ) && $e <= '7' ) {
					$oct = $e;

					while ( strlen( $oct ) < 3 && isset( $s[ $pos + 1 ] ) && ctype_digit( $s[ $pos + 1 ] ) && $s[ $pos + 1 ] <= '7' ) {
						$oct .= $s[ ++$pos ];
					}

					$out .= chr( octdec( $oct ) & 255 );
				} elseif ( "\r" === $e ) {
					if ( "\n" === ( $s[ $pos + 1 ] ?? '' ) ) {
						++$pos;
					}
				} elseif ( "\n" !== $e ) {
					$out .= $e;
				}

				continue;
			}

			if ( '(' === $c ) {
				if ( $depth++ > 0 ) {
					$out .= $c;
				}
			} elseif ( ')' === $c ) {
				if ( --$depth === 0 ) {
					++$pos;

					return new CPTDP_Pdf_Str( $out );
				}

				$out .= $c;
			} else {
				$out .= $c;
			}
		}

		throw new RuntimeException( 'unterminated string' );
	}

	/**
	 * A text string (outline title) as UTF-8.
	 *
	 * @param mixed $value Parsed value.
	 */
	private function decode_text( $value ): string {
		if ( ! $value instanceof CPTDP_Pdf_Str ) {
			return '';
		}

		$bytes = $value->v;

		if ( 0 === strpos( $bytes, "\xFE\xFF" ) ) {
			return (string) mb_convert_encoding( substr( $bytes, 2 ), 'UTF-8', 'UTF-16BE' );
		}

		if ( 0 === strpos( $bytes, "\xFF\xFE" ) ) {
			return (string) mb_convert_encoding( substr( $bytes, 2 ), 'UTF-8', 'UTF-16LE' );
		}

		if ( 0 === strpos( $bytes, "\xEF\xBB\xBF" ) ) {
			return substr( $bytes, 3 );
		}

		// PDFDocEncoding is close enough to Windows-1252 for headings.
		return (string) mb_convert_encoding( $bytes, 'UTF-8', 'Windows-1252' );
	}
}
