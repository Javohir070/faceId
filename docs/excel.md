<?php
/**
 *    SimpleXLSX php class v0.8.14
 *    MS Excel 2007 workbooks reader
 *
 * Copyright (c) 2012 - 2020 SimpleXLSX
 *
 * @category   SimpleXLSX
 * @package    SimpleXLSX
 * @copyright  Copyright (c) 2012 - 2020 SimpleXLSX (https://github.com/shuchkin/simplexlsx/)
 * @license    MIT
 * @version    0.8.14
 */

/** Examples
 *
 * Example 1:
 * if ( $xlsx = SimpleXLSX::parse('book.xlsx') ) {
 *   print_r( $xlsx->rows() );
 * } else {
 *   echo SimpleXLSX::parseError();
 * }
 *
 * Example 2: html table
 * if ( $xlsx = SimpleXLSX::parse('book.xlsx') ) {
 *   echo $xlsx->toHTML();
 * } else {
 *   echo SimpleXLSX::parseError();
 * }
 *
 * Example 3: rowsEx
 * $xlsx = SimpleXLSX::parse('book.xlsx');
 * print_r( $xlsx->rowsEx() );
 *
 * Example 4: select worksheet
 * $xlsx = SimpleXLSX::parse('book.xlsx');
 * print_r( $xlsx->rows(1) ); // second worksheet
 *
 * Example 5: IDs and worksheet names
 * $xlsx = SimpleXLSX::parse('book.xlsx');
 * print_r( $xlsx->sheetNames() ); // array( 0 => 'Sheet 1', 1 => 'Catalog' );
 *
 * Example 6: get sheet name by index
 * $xlsx = SimpleXLSX::parse('book.xlsx');
 * echo 'Sheet Name 2 = '.$xlsx->sheetName(1);
 *
 * Example 7: getCell (very slow)
 * echo $xlsx->getCell(1,'D12'); // reads D12 cell from second sheet
 *
 * Example 8: read data
 * if ( $xlsx = SimpleXLSX::parseData( file_get_contents('http://www.example.com/example.xlsx') ) ) {
 *   $dim = $xlsx->dimension(1);
 *   $num_cols = $dim[0];
 *   $num_rows = $dim[1];
 *   echo $xlsx->sheetName(1).':'.$num_cols.'x'.$num_rows;
 * } else {
 *   echo SimpleXLSX::parseError();
 * }
 *
 * Example 9: old style
 * $xlsx = new SimpleXLSX('book.xlsx');
 * if ( $xlsx->success() ) {
 *   print_r( $xlsx->rows() );
 * } else {
 *   echo 'xlsx error: '.$xlsx->error();
 * }
 */

/** @noinspection PhpUndefinedFieldInspection */
/** @noinspection PhpComposerExtensionStubsInspection */
/** @noinspection MultiAssignmentUsageInspection */

class SimpleXLSX {
	// Don't remove this string! Created by Sergey Shuchkin sergey.shuchkin@gmail.com
	public static $CF = array( // Cell formats
		0  => 'General',
		1  => '0',
		2  => '0.00',
		3  => '#,##0',
		4  => '#,##0.00',
		9  => '0%',
		10 => '0.00%',
		11 => '0.00E+00',
		12 => '# ?/?',
		13 => '# ??/??',
		14 => 'mm-dd-yy',
		15 => 'd-mmm-yy',
		16 => 'd-mmm',
		17 => 'mmm-yy',
		18 => 'h:mm AM/PM',
		19 => 'h:mm:ss AM/PM',
		20 => 'h:mm',
		21 => 'h:mm:ss',
		22 => 'm/d/yy h:mm',

		37 => '#,##0 ;(#,##0)',
		38 => '#,##0 ;[Red](#,##0)',
		39 => '#,##0.00;(#,##0.00)',
		40 => '#,##0.00;[Red](#,##0.00)',

		44 => '_("$"* #,##0.00_);_("$"* \(#,##0.00\);_("$"* "-"??_);_(@_)',
		45 => 'mm:ss',
		46 => '[h]:mm:ss',
		47 => 'mmss.0',
		48 => '##0.0E+0',
		49 => '@',

		27 => '[$-404]e/m/d',
		30 => 'm/d/yy',
		36 => '[$-404]e/m/d',
		50 => '[$-404]e/m/d',
		57 => '[$-404]e/m/d',

		59 => 't0',
		60 => 't0.00',
		61 => 't#,##0',
		62 => 't#,##0.00',
		67 => 't0%',
		68 => 't0.00%',
		69 => 't# ?/?',
		70 => 't# ??/??',
	);
	public $cellFormats = array();
	public $datetimeFormat = 'Y-m-d H:i:s';
	public $debug;

	/* @var SimpleXMLElement $workbook */
	private $workbook;
	/* @var SimpleXMLElement[] $sheets */
	private $sheets;
	private $sheetNames = array();
	// scheme
	private $styles;
	private $hyperlinks;
	/* @var array[] $package */
	private $package;
	private $datasec;
	private $sharedstrings;
	private $date1904 = 0;


	/*
		private $date_formats = array(
			0xe => "d/m/Y",
			0xf => "d-M-Y",
			0x10 => "d-M",
			0x11 => "M-Y",
			0x12 => "h:i a",
			0x13 => "h:i:s a",
			0x14 => "H:i",
			0x15 => "H:i:s",
			0x16 => "d/m/Y H:i",
			0x2d => "i:s",
			0x2e => "H:i:s",
			0x2f => "i:s.S"
		);
		private $number_formats = array(
			0x1 => "%1.0f",     // "0"
			0x2 => "%1.2f",     // "0.00",
			0x3 => "%1.0f",     //"#,##0",
			0x4 => "%1.2f",     //"#,##0.00",
			0x5 => "%1.0f",     //"$#,##0;($#,##0)",
			0x6 => '$%1.0f',    //"$#,##0;($#,##0)",
			0x7 => '$%1.2f',    //"$#,##0.00;($#,##0.00)",
			0x8 => '$%1.2f',    //"$#,##0.00;($#,##0.00)",
			0x9 => '%1.0f%%',   //"0%"
			0xa => '%1.2f%%',   //"0.00%"
			0xb => '%1.2f',     //"0.00E00",
			0x25 => '%1.0f',    //"#,##0;(#,##0)",
			0x26 => '%1.0f',    //"#,##0;(#,##0)",
			0x27 => '%1.2f',    //"#,##0.00;(#,##0.00)",
			0x28 => '%1.2f',    //"#,##0.00;(#,##0.00)",
			0x29 => '%1.0f',    //"#,##0;(#,##0)",
			0x2a => '$%1.0f',   //"$#,##0;($#,##0)",
			0x2b => '%1.2f',    //"#,##0.00;(#,##0.00)",
			0x2c => '$%1.2f',   //"$#,##0.00;($#,##0.00)",
			0x30 => '%1.0f');   //"##0.0E0";
		// }}}
	*/
	private $errno = 0;
	private $error = false;


	public function __construct( $filename = null, $is_data = null, $debug = null ) {
		if ( $debug !== null ) {
			$this->debug = $debug;
		}
		$this->package = array(
			'filename' => '',
			'mtime'    => 0,
			'size'     => 0,
			'comment'  => '',
			'entries'  => array()
		);
		if ( $filename && $this->_unzip( $filename, $is_data )) {
			$this->_parse();
		}
	}
	public static function parseFile( $filename, $debug = false ) {
		return self::parse( $filename, false, $debug );
	}
	public static function parseData( $data, $debug = false ) {
		return self::parse( $data, true, $debug );
	}
	public static function parse( $filename, $is_data = false, $debug = false ) {
		$xlsx = new self();
		$xlsx->debug = $debug;
		if ( $xlsx->_unzip($filename, $is_data )) {
			$xlsx->_parse();
		}
		if ( $xlsx->success() ) {
			return $xlsx;
		}
		self::parseError( $xlsx->error() );
		self::parseErrno( $xlsx->errno() );

		return false;
	}
	public static function parseError( $set = false ) {
		static $error = false;
		return $set ? $error = $set : $error;
	}
	public static function parseErrno( $set = false ) {
		static $errno = false;
		return $set ? $errno = $set : $errno;
	}

	private function _unzip( $filename, $is_data = false ) {

		// Clear current file
		$this->datasec = array();

		if ( $is_data ) {

			$this->package['filename'] = 'default.xlsx';
			$this->package['mtime']    = time();
			$this->package['size']     = $this->_strlen( $filename );

			$vZ = $filename;
		} else {

			if ( ! is_readable( $filename ) ) {
				$this->error( 1, 'File not found ' . $filename );

				return false;
			}

			// Package information
			$this->package['filename'] = $filename;
			$this->package['mtime']    = filemtime( $filename );
			$this->package['size']     = filesize( $filename );

			// Read file
			$vZ = file_get_contents( $filename );
		}
		// Cut end of central directory
		/*		$aE = explode("\x50\x4b\x05\x06", $vZ);

				if (count($aE) == 1) {
					$this->error('Unknown format');
					return false;
				}
		*/
		// Explode to each part
		$aE = explode( "\x50\x4b\x03\x04", $vZ );
		array_shift( $aE );

		$aEL = count($aE);
		if ( $aEL === 0 ) {
			$this->error( 2, 'Unknown archive format' );
			return false;
		}
		// Search central directory end record
		$last = $aE[ $aEL - 1 ];
		$last = explode( "\x50\x4b\x05\x06", $last );
		if ( count($last) !== 2 ) {
			$this->error( 2, 'Unknown archive format' );
			return false;
		}
		// Search central directory
		$last = explode( "\x50\x4b\x01\x02", $last[0] );
		if ( count($last) < 2 ) {
			$this->error( 2, 'Unknown archive format' );
			return false;
		}
		$aE[ $aEL - 1 ] = $last[0];

		// Loop through the entries
		foreach ( $aE as $vZ ) {
			$aI       = array();
			$aI['E']  = 0;
			$aI['EM'] = '';
			// Retrieving local file header information
//			$aP = unpack('v1VN/v1GPF/v1CM/v1FT/v1FD/V1CRC/V1CS/V1UCS/v1FNL', $vZ);
			$aP = unpack( 'v1VN/v1GPF/v1CM/v1FT/v1FD/V1CRC/V1CS/V1UCS/v1FNL/v1EFL', $vZ );

			// Check if data is encrypted
//			$bE = ($aP['GPF'] && 0x0001) ? TRUE : FALSE;
			$bE = false;
			$nF = $aP['FNL'];
			$mF = $aP['EFL'];

			// Special case : value block after the compressed data
			if ( $aP['GPF'] & 0x0008 ) {
				$aP1 = unpack( 'V1CRC/V1CS/V1UCS', $this->_substr( $vZ, - 12 ) );

				$aP['CRC'] = $aP1['CRC'];
				$aP['CS']  = $aP1['CS'];
				$aP['UCS'] = $aP1['UCS'];
				// 2013-08-10
				$vZ = $this->_substr( $vZ, 0, - 12 );
				if ( $this->_substr( $vZ, - 4 ) === "\x50\x4b\x07\x08" ) {
					$vZ = $this->_substr( $vZ, 0, - 4 );
				}
			}

			// Getting stored filename
			$aI['N'] = $this->_substr( $vZ, 26, $nF );

			if ( $this->_substr( $aI['N'], - 1 ) === '/' ) {
				// is a directory entry - will be skipped
				continue;
			}

			// Truncate full filename in path and filename
			$aI['P'] = dirname( $aI['N'] );
			$aI['P'] = $aI['P'] === '.' ? '' : $aI['P'];
			$aI['N'] = basename( $aI['N'] );

			$vZ = $this->_substr( $vZ, 26 + $nF + $mF );

			if ( $this->_strlen( $vZ ) !== (int) $aP['CS'] ) { // check only if availabled
				$aI['E']  = 1;
				$aI['EM'] = 'Compressed size is not equal with the value in header information.';
			} else if ( $bE ) {
				$aI['E']  = 5;
				$aI['EM'] = 'File is encrypted, which is not supported from this class.';
			} else {
				switch ( $aP['CM'] ) {
					case 0: // Stored
						// Here is nothing to do, the file ist flat.
						break;
					case 8: // Deflated
						$vZ = gzinflate( $vZ );
						break;
					case 12: // BZIP2
						if ( extension_loaded( 'bz2' ) ) {
							/** @noinspection PhpComposerExtensionStubsInspection */
							$vZ = bzdecompress( $vZ );
						} else {
							$aI['E']  = 7;
							$aI['EM'] = 'PHP BZIP2 extension not available.';
						}
						break;
					default:
						$aI['E']  = 6;
						$aI['EM'] = "De-/Compression method {$aP['CM']} is not supported.";
				}
				if ( ! $aI['E'] ) {
					if ( $vZ === false ) {
						$aI['E']  = 2;
						$aI['EM'] = 'Decompression of data failed.';
					} else if ( $this->_strlen( $vZ ) !== (int) $aP['UCS'] ) {
						$aI['E']  = 3;
						$aI['EM'] = 'Uncompressed size is not equal with the value in header information.';
					} else if ( crc32( $vZ ) !== $aP['CRC'] ) {
						$aI['E']  = 4;
						$aI['EM'] = 'CRC32 checksum is not equal with the value in header information.';
					}
				}
			}

			$aI['D'] = $vZ;

			// DOS to UNIX timestamp
			$aI['T'] = mktime( ( $aP['FT'] & 0xf800 ) >> 11,
				( $aP['FT'] & 0x07e0 ) >> 5,
				( $aP['FT'] & 0x001f ) << 1,
				( $aP['FD'] & 0x01e0 ) >> 5,
				$aP['FD'] & 0x001f,
				( ( $aP['FD'] & 0xfe00 ) >> 9 ) + 1980 );

			//$this->Entries[] = &new SimpleUnzipEntry($aI);
			$this->package['entries'][] = array(
				'data'      => $aI['D'],
				'error'     => $aI['E'],
				'error_msg' => $aI['EM'],
				'name'      => $aI['N'],
				'path'      => $aI['P'],
				'time'      => $aI['T']
			);

		} // end for each entries

		return true;
	}

	// sheets numeration: 1,2,3....

	public function error( $num = null, $str = null ) {
		if ( $num ) {
			$this->errno = $num;
			$this->error = $str;
			if ( $this->debug ) {
				trigger_error( __CLASS__ . ': ' . $this->error, E_USER_WARNING );
			}
		}

		return $this->error;
	}
	public function errno() {
		return $this->errno;
	}

	private function _parse() {
		// Document data holders
		$this->sharedstrings = array();
		$this->sheets        = array();
//		$this->styles = array();

		// Read relations and search for officeDocument
		if ( $relations = $this->getEntryXML( '_rels/.rels' ) ) {

			foreach ( $relations->Relationship as $rel ) {

				$rel_type = basename( trim( (string) $rel['Type'] ) );
				$rel_target = trim( (string) $rel['Target'] );

				if ( $rel_type === 'officeDocument' && $this->workbook = $this->getEntryXML( $rel_target ) ) {

					$index_rId = array(); // [0 => rId1]

					$index = 0;
					foreach ( $this->workbook->sheets->sheet as $s ) {
						/* @var SimpleXMLElement $s */
						$this->sheetNames[ $index ] = (string) $s['name'];
						$index_rId[ $index ] = (string) $s['id'];
						$index++;
					}
					if ( (int) $this->workbook->workbookPr['date1904'] === 1 ) {
						$this->date1904 = 1;
					}

//					print_r( $index_rId );


					if ( $workbookRelations = $this->getEntryXML( dirname( $rel_target ) . '/_rels/workbook.xml.rels' ) ) {

						// Loop relations for workbook and extract sheets...
						foreach ( $workbookRelations->Relationship as $workbookRelation ) {

							$wrel_type = basename( trim( (string) $workbookRelation['Type'] ) );
							$wrel_path = dirname( trim( (string) $rel['Target'] ) ) . '/' . trim( (string) $workbookRelation['Target'] );
							if ( ! $this->entryExists( $wrel_path ) ) {
								continue;
							}


							if ( $wrel_type === 'worksheet' ) { // Sheets

								if ( $sheet = $this->getEntryXML( $wrel_path ) ) {
									$index = array_search( (string) $workbookRelation['Id'], $index_rId, false );
									$this->sheets[ $index ] = $sheet;
								}

							} else if ( $wrel_type === 'sharedStrings' ) {

								if ( $sharedStrings = $this->getEntryXML( $wrel_path ) ) {
									foreach ( $sharedStrings->si as $val ) {
										if ( isset( $val->t ) ) {
											$this->sharedstrings[] = (string) $val->t;
										} elseif ( isset( $val->r ) ) {
											$this->sharedstrings[] = $this->_parseRichText( $val );
										}
									}
								}
							} else if ( $wrel_type === 'styles' ) {

								$this->styles = $this->getEntryXML( $wrel_path );

								$nf = array();
								if ( $this->styles->numFmts->numFmt !== null ) {
									foreach ( $this->styles->numFmts->numFmt as $v ) {
										$nf[ (int) $v['numFmtId'] ] = (string) $v['formatCode'];
									}
								}

								if ( $this->styles->cellXfs->xf !== null ) {
									foreach ( $this->styles->cellXfs->xf as $v ) {
										$v           = (array) $v->attributes();
										$v['format'] = '';

										if ( isset( $v['@attributes']['numFmtId'] ) ) {
											$v = $v['@attributes'];
											$fid = (int) $v['numFmtId'];
											// formats priority
											if ( isset( $nf[ $fid ] ) ) {
												$v['format'] = $nf[ $fid ];
											} else if ( isset( self::$CF[ $fid ] ) ) {
												$v['format'] = self::$CF[ $fid ];
											}
										}
										$this->cellFormats[] = $v;
									}
								}
							}
						}

						break;
					}
				}
			}
		}
		if ( count( $this->sheets ) ) {
			// Sort sheets
			ksort( $this->sheets );

			return true;
		}

		return false;
	}
	/*
	 * @param string $name Filename in archive
	 * @return SimpleXMLElement|bool
	*/
	public function getEntryXML( $name ) {
		if ( $entry_xml = $this->getEntryData( $name ) ) {
			$entry_xml = trim( $entry_xml );
			// dirty remove namespace prefixes and empty rows
			$entry_xml = preg_replace('/xmlns[^=]*="[^"]*"/i','', $entry_xml ); // remove namespaces
			$entry_xml = preg_replace('/[a-zA-Z0-9]+:([a-zA-Z0-9]+="[^"]+")/','$1$2', $entry_xml ); // remove namespaced attrs
			$entry_xml = preg_replace('/<[a-zA-Z0-9]+:([^>]+)>/', '<$1>', $entry_xml); // fix namespaced openned tags
			$entry_xml = preg_replace('/<\/[a-zA-Z0-9]+:([^>]+)>/', '</$1>', $entry_xml); // fix namespaced closed tags

//			if ( $this->skipEmptyRows && strpos($name, '/sheet') ) {
			if ( strpos($name, '/sheet') ) { // dirty skip empty rows
				$entry_xml = preg_replace( '/<row[^>]+>\s*(<c[^\/]+\/>\s*)+<\/row>/', '', $entry_xml,-1, $cnt ); // remove empty rows
				$entry_xml = preg_replace( '/<row[^\/>]*\/>/', '', $entry_xml, -1, $cnt2 );
				$entry_xml = preg_replace( '/<row[^>]*><\/row>/', '', $entry_xml, -1, $cnt3 );
				if ( $cnt || $cnt2 || $cnt3  ) {
					$entry_xml = preg_replace('/<dimension[^\/]+\/>/', '', $entry_xml);
				}
//				file_put_contents( basename( $name ), $entry_xml ); // @to do comment!!!
			}

			// XML External Entity (XXE) Prevention
			$_old         = libxml_disable_entity_loader();
			$entry_xmlobj = simplexml_load_string( $entry_xml );
//			echo '<pre>'.print_r( $entry_xmlobj, true).'</pre>';
			libxml_disable_entity_loader($_old);
			if ( $entry_xmlobj ) {
				return $entry_xmlobj;
			}
			$e = libxml_get_last_error();
			$this->error( 3, 'XML-entry ' . $name.' parser error '.$e->message.' line '.$e->line );
		} else {
			$this->error( 4, 'XML-entry not found ' . $name );
		}
		return false;
	}

	public function getEntryData( $name ) {
		$dir  = $this->_strtoupper( dirname( $name ) );
		$name = $this->_strtoupper( basename( $name ) );
		foreach ( $this->package['entries'] as $entry ) {
			if ( $this->_strtoupper( $entry['path'] ) === $dir && $this->_strtoupper( $entry['name'] ) === $name ) {
				return $entry['data'];
			}
		}
		$this->error( 5, 'Entry not found '.$name );

		return false;
	}

	public function entryExists( $name ) { // 0.6.6
		$dir  = $this->_strtoupper( dirname( $name ) );
		$name = $this->_strtoupper( basename( $name ) );
		foreach ( $this->package['entries'] as $entry ) {
			if ( $this->_strtoupper( $entry['path'] ) === $dir && $this->_strtoupper( $entry['name'] ) === $name ) {
				return true;
			}
		}

		return false;
	}

	private function _parseRichText( $is = null ) {
		$value = array();

		if ( isset( $is->t ) ) {
			$value[] = (string) $is->t;
		} else if ( isset($is->r ) ) {
			foreach ( $is->r as $run ) {
				$value[] = (string) $run->t;
			}
		}

		return implode( '', $value );
	}

	public function success() {
		return ! $this->error;
	}

	public function rows( $worksheetIndex = 0 ) {

		if ( ( $ws = $this->worksheet( $worksheetIndex ) ) === false ) {
			return false;
		}
		$dim = $this->dimension( $worksheetIndex );
		$numCols = $dim[0];
		$numRows = $dim[1];

		$emptyRow = array();
		for( $i = 0; $i < $numCols; $i++) {
			$emptyRow[] = '';
		}

		$rows = array();
		for( $i = 0; $i < $numRows; $i++) {
			$rows[] = $emptyRow;
		}

		$curR = 0;
		/* @var SimpleXMLElement $ws */
		foreach ( $ws->sheetData->row as $row ) {
			$curC = 0;
			foreach ( $row->c as $c ) {
				// detect skipped cols
				$idx = $this->getIndex( (string) $c['r'] );
				$x = $idx[0];
				$y = $idx[1];

				if ( $x > -1 ) {
					$curC = $x;
					$curR = $y;
				}

				$rows[ $curR ][ $curC ] = $this->value( $c );
				$curC++;
			}

			$curR ++;
		}

		return $rows;
	}

	public function rowsEx( $worksheetIndex = 0 ) {

		if ( ( $ws = $this->worksheet( $worksheetIndex ) ) === false ) {
			return false;
		}

		$rows = array();

		$dim = $this->dimension( $worksheetIndex );
		$numCols = $dim[0];
		$numRows = $dim[1];

		for ( $y = 0; $y < $numRows; $y++ ) {
			for ( $x = 0; $x < $numCols; $x++ ) {
				// 0.6.8
				$c = '';
				for ( $k = $x; $k >= 0; $k = (int) ( $k / 26 ) - 1 ) {
					$c = chr( $k % 26 + 65 ) . $c;
				}
				$rows[ $y ][ $x ] = array(
					'type'   => '',
					'name'   => $c . ( $y + 1 ),
					'value'  => '',
					'href'   => '',
					'f'      => '',
					'format' => '',
					'r' => $y
				);
			}
		}

		$curR = 0;
		/* @var SimpleXMLElement $ws */
		foreach ( $ws->sheetData->row as $row ) {

			$r_idx = (int) $row['r'];
			$curC = 0;

			foreach ( $row->c as $c ) {
				$r = (string) $c['r'];
				$t = (string) $c['t'];
				$s = (int) $c['s'];

				$idx = $this->getIndex( $r );
				$x = $idx[0];
				$y = $idx[1];

				if ( $x > -1 ) {
					$curC = $x;
					$curR = $y;
				}

				if ( $s > 0 && isset( $this->cellFormats[ $s ] ) ) {
					$format = $this->cellFormats[ $s ]['format'];
				} else {
					$format = '';
				}

				$rows[ $curR ][ $curC ] = array(
					'type'   => $t,
					'name'   => (string) $c['r'],
					'value'  => $this->value( $c ),
					'href'   => $this->href( $c ),
					'f'      => (string) $c->f,
					'format' => $format,
					'r' => $r_idx
				);
				$curC++;
			}
			$curR ++;
		}

		return $rows;

	}
	public function toHTML( $worksheetIndex = 0 ) {
		$s = '<table class=excel>';
		foreach( $this->rows( $worksheetIndex ) as $r ) {
			$s .= '<tr>';
			foreach ( $r as $c ) {
				$s .= '<td nowrap>'.( $c === '' ? '&nbsp' : htmlspecialchars( $c, ENT_QUOTES )).'</td>';
			}
			$s .= "</tr>\r\n";
		}
		$s .= '</table>';
		return $s;
	}

	public function worksheet( $worksheetIndex = 0 ) {



		if ( isset( $this->sheets[ $worksheetIndex ] ) ) {
			$ws = $this->sheets[ $worksheetIndex ];

			if ( isset( $ws->hyperlinks ) ) {
				$this->hyperlinks = array();
				foreach ( $ws->hyperlinks->hyperlink as $hyperlink ) {
					$this->hyperlinks[ (string) $hyperlink['ref'] ] = (string) $hyperlink['display'];
				}
			}

			return $ws;
		}
		$this->error( 6, 'Worksheet not found ' . $worksheetIndex );

		return false;
	}

	/**
	 * returns [numCols,numRows] of worksheet
	 *
	 * @param int $worksheetIndex
	 *
	 * @return array
	 */
	public function dimension( $worksheetIndex = 0 ) {

		if ( ( $ws = $this->worksheet( $worksheetIndex ) ) === false ) {
			return array(0,0);
		}
		/* @var SimpleXMLElement $ws */

		$ref = (string) $ws->dimension['ref'];

		if ( $this->_strpos( $ref, ':' ) !== false ) {
			$d = explode( ':', $ref );
			$idx = $this->getIndex( $d[1] );

			return array( $idx[0] + 1, $idx[1] + 1 );
		}
		if ( $ref !== '' ) { // 0.6.8
			$index = $this->getIndex( $ref );

			return array( $index[0] + 1, $index[1] + 1 );
		}

		// slow method
		$maxC = $maxR = 0;
		foreach ( $ws->sheetData->row as $row ) {
			foreach ( $row->c as $c ) {
				$idx = $this->getIndex( (string) $c['r'] );
				$x = $idx[0];
				$y = $idx[1];
				if ( $x > 0 ) {
					if ( $x > $maxC ) {
						$maxC = $x;
					}
					if ( $y > $maxR ) {
						$maxR = $y;
					}
				}
			}
		}

		return array( $maxC+1, $maxR+1 );
	}

	public function getIndex( $cell = 'A1' ) {

		if ( preg_match( '/([A-Z]+)(\d+)/', $cell, $m ) ) {
			$col = $m[1];
			$row = $m[2];

			$colLen = $this->_strlen( $col );
			$index  = 0;

			for ( $i = $colLen - 1; $i >= 0; $i -- ) {
				/** @noinspection PowerOperatorCanBeUsedInspection */
				$index += ( ord( $col[$i] ) - 64 ) * pow( 26, $colLen - $i - 1 );
			}

			return array( $index - 1, $row - 1 );
		}
//		$this->error( 'Invalid cell index ' . $cell );

		return array(-1,-1);
	}

	public function value( $cell ) {
		// Determine data type
		$dataType = (string) $cell['t'];

		if ( !$dataType ) { // number
			$s = (int) $cell['s'];
			if ( $s > 0 && isset( $this->cellFormats[ $s ] ) ) {
				$format = $this->cellFormats[ $s ]['format'];
				if ( strpos( $format, 'm') !== false ) {
					$dataType = 'd';
				}
			}
		}

		$value = '';

		switch ( $dataType ) {
			case 's':
				// Value is a shared string
				if ( (string) $cell->v !== '' ) {
					$value = $this->sharedstrings[ (int) $cell->v ];
				}

				break;

			case 'b':
				// Value is boolean
				$value = (string) $cell->v;
				if ( $value === '0' ) {
					$value = false;
				} else if ( $value === '1' ) {
					$value = true;
				} else {
					$value = (bool) $cell->v;
				}

				break;

			case 'inlineStr':
				// Value is rich text inline
				$value = $this->_parseRichText( $cell->is );

				break;

			case 'e':
				// Value is an error message
				if ( (string) $cell->v !== '' ) {
					$value = (string) $cell->v;
				}

				break;
			case 'd':
				// Value is a date and non-empty
				if ( ! empty($cell->v) ) {
					$value = $this->datetimeFormat ? gmdate( $this->datetimeFormat, $this->unixstamp( (float) $cell->v ) ) : (float) $cell->v;
				}
				break;


			default:
				// Value is a string
				$value = (string) $cell->v;

				// Check for numeric values
				if ( is_numeric( $value ) && $dataType !== 's' ) {
					/** @noinspection TypeUnsafeComparisonInspection */
					if ( $value == (int) $value ) {
						$value = (int) $value;
					} /** @noinspection TypeUnsafeComparisonInspection */ elseif ( $value == (float) $value ) {
						$value = (float) $value;
					}
				}
		}

		return $value;
	}

	public function unixstamp( $excelDateTime ) {

		$d = floor( $excelDateTime ); // days since 1900 or 1904
		$t = $excelDateTime - $d;

		if ( $this->date1904 ) {
			$d += 1462;
		}

		$t = ( abs( $d ) > 0 ) ? ( $d - 25569 ) * 86400 + round( $t * 86400 ) : round( $t * 86400 );

		return (int) $t;
	}

	/**
	 * Returns cell value
	 * VERY SLOW! Use ->rows() or ->rowsEx()
	 *
	 * @param int $worksheetIndex
	 * @param string|array $cell ref or coords, D12 or [3,12]
	 *
	 * @return mixed Returns NULL if not found
	 */
	public function getCell( $worksheetIndex = 0, $cell = 'A1' ) {

		if (($ws = $this->worksheet( $worksheetIndex)) === false) { return false; }

		$idx = is_array( $cell ) ? $cell : $this->getIndex( (string) $cell );
		$C = $idx[0];
		$R = $idx[1];

		$curR = 0;
		/* @var SimpleXMLElement $ws */
		foreach ( $ws->sheetData->row as $row ) {
			$curC = 0;
			foreach ( $row->c as $c ) {
				// detect skipped cols
				$idx = $this->getIndex( (string) $c['r'] );
				$x = $idx[0];
				$y = $idx[1];
				if ( $x > 0 ) {
					$curC = $x;
					$curR = $y;
				}
				if ( $curR === $R && $curC === $C ) {
					return $this->value( $c );
				}
				if ( $curR > $R ){
					return null;
				}
				$curC++;
			}

			$curR ++;
		}
		return null;
	}

	public function href( $cell ) {
		return isset( $this->hyperlinks[ (string) $cell['r'] ] ) ? $this->hyperlinks[ (string) $cell['r'] ] : '';
	}

	public function sheets() {
		return $this->sheets;
	}

	public function sheetsCount() {
		return count( $this->sheets );
	}

	public function sheetName( $worksheetIndex ) {
		if ( isset($this->sheetNames[ $worksheetIndex ])) {
			return $this->sheetNames[ $worksheetIndex ];
		}

		return false;
	}

	public function sheetNames() {

		return $this->sheetNames;
	}

	// thx Gonzo

	public function getStyles() {
		return $this->styles;
	}

	public function getPackage() {
		return $this->package;
	}
	public function setDateTimeFormat( $value ) {
		$this->datetimeFormat = is_string( $value) ? $value : false;
	}
	private function _strlen( $str ) {
		return (ini_get('mbstring.func_overload') & 2) ? mb_strlen($str , '8bit') : strlen($str);
	}
	private function _strpos( $haystack, $needle, $offset = 0 ) {
		return (ini_get('mbstring.func_overload') & 2) ? mb_strpos( $haystack, $needle, $offset , '8bit') : strpos($haystack, $needle, $offset);
	}
/*
	private function _strrpos( $haystack, $needle, $offset = 0 ) {
		return (ini_get('mbstring.func_overload') & 2) ? mb_strrpos( $haystack, $needle, $offset, '8bit') : strrpos($haystack, $needle, $offset);
	}*/
	private function _strtoupper( $str ) {
		return (ini_get('mbstring.func_overload') & 2) ? mb_strtoupper($str , '8bit') : strtoupper($str);
	}
	private function _substr( $str, $start, $length = null ) {
		return (ini_get('mbstring.func_overload') & 2) ? mb_substr( $str, $start, ($length === null) ? mb_strlen($str,'8bit') : $length, '8bit') : substr($str, $start, ($length === null) ? strlen($str) : $length );
	}

}



<?php
/**
 * Class SimpleXLSXGen
 * Export data to MS Excel. PHP XLSX generator
 * Author: sergey.shuchkin@gmail.com
 */

class SimpleXLSXGen {

	public $curSheet;
	protected $defaultFont;
	protected $defaultFontSize;
	protected $sheets;
	protected $template;
	protected $F, $F_KEYS; // fonts
	protected $XF, $XF_KEYS; // cellXfs
	protected $SI, $SI_KEYS; // shared strings
	const N_NORMAL = 0; // General
	const N_INT = 1; // 0
	const N_DEC = 2; // 0.00
	const N_PERCENT_INT = 9; // 0%
	const N_PRECENT_DEC = 10; // 0.00%
	const N_DATE = 14; // mm-dd-yy
	const N_TIME = 20; // h:mm
	const N_DATETIME = 22; // m/d/yy h:mm
	const F_NORMAL = 0;
	const F_HYPERLINK = 1;
	const F_BOLD = 2;
	const F_ITALIC = 4;
	const F_UNDERLINE = 8;
	const F_STRIKE = 16;
	const A_DEFAULT = 0;
	const A_LEFT = 1;
	const A_RIGHT = 2;
	const A_CENTER = 3;


	public function __construct() {
		$this->curSheet = -1;
		$this->defaultFont = 'Calibri';
		$this->sheets = [ ['name' => 'Sheet1', 'rows' => [], 'hyperlinks' => [] ] ];
		$this->SI = [];		// sharedStrings index
		$this->SI_KEYS = []; //  & keys
		$this->F = [ self::F_NORMAL ]; // fonts
		$this->F_KEYS = [0]; // & keys
		$this->XF  = [ [self::N_NORMAL, self::F_NORMAL, self::A_DEFAULT] ]; // styles
		$this->XF_KEYS = ['N0F0A0' => 0 ]; // & keys

		$this->template = [
			'_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>',
			'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
<TotalTime>0</TotalTime>
<Application>'.__CLASS__.'</Application></Properties>',
			'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dcterms:created xsi:type="dcterms:W3CDTF">{DATE}</dcterms:created>
<dc:language>en-US</dc:language>
<dcterms:modified xsi:type="dcterms:W3CDTF">{DATE}</dcterms:modified>
<cp:revision>1</cp:revision>
</cp:coreProperties>',
			'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
{SHEETS}',
			'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
	xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
><dimension ref="{REF}"/>{COLS}<sheetData>{ROWS}</sheetData>{HYPERLINKS}</worksheet>',
			'xl/worksheets/_rels/sheet1.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">{HYPERLINKS}</Relationships>',
			'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="{CNT}" uniqueCount="{CNT}">{STRINGS}</sst>',
			'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
{FONTS}
<fills count="1"><fill><patternFill patternType="none"/></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" /></cellStyleXfs>
{XF}
<cellStyles count="1">
	<cellStyle name="Normal" xfId="0" builtinId="0"/>
</cellStyles>
</styleSheet>',
			'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<fileVersion appName="'.__CLASS__.'"/><sheets>
{SHEETS}
</sheets></workbook>',
			'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Override PartName="/rels/.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/xl/_rels/workbook.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
{TYPES}
</Types>',
		];

		// <col min="1" max="1" width="22.1796875" bestFit="1" customWidth="1"/>
		// <row r="1" spans="1:2" x14ac:dyDescent="0.35"><c r="A1" t="s"><v>0</v></c><c r="B1"><v>100</v></c></row><row r="2" spans="1:2" x14ac:dyDescent="0.35"><c r="A2" t="s"><v>1</v></c><c r="B2"><v>200</v></c></row>
		// <si><t>Простой шаблон</t></si><si><t>Будем делать генератор</t></si>
	}
	public static function fromArray( array $rows, $sheetName = null ) {
		$xlsx = new static();
		return $xlsx->addSheet( $rows, $sheetName );
	}

	public function addSheet( array $rows, $name = null ) {

		$this->curSheet++;
		if ( $name === null ) { // autogenerated sheet names
			$name = 'Sheet'.($this->curSheet+1);
		} else {
			$names = [];
			foreach( $this->sheets as $sh ) {
				$names[ mb_strtoupper( $sh['name']) ] = 1;
			}
			for( $i = 0; $i < 100; $i++ ) {
				$new_name = ($i === 0) ? $name : $name .' ('.$i.')';
				$NEW_NAME = mb_strtoupper( $new_name );
				if ( !isset( $names[ $NEW_NAME ]) ) {
					$name = $new_name;
					break;
				}
			}
		}

		$this->sheets[$this->curSheet] = ['name' => $name, 'hyperlinks' => []];

		if ( is_array( $rows ) && isset( $rows[0] ) && is_array($rows[0]) ) {
			$this->sheets[$this->curSheet]['rows'] = $rows;
		} else {
			$this->sheets[$this->curSheet]['rows'] = [];
		}
		return $this;
	}

	public function __toString() {
		$fh = fopen( 'php://memory', 'wb' );
		if ( ! $fh ) {
			return '';
		}

		if ( ! $this->_write( $fh ) ) {
			fclose( $fh );
			return '';
		}
		$size = ftell( $fh );
		fseek( $fh, 0);

		return (string) fread( $fh, $size );
	}

	public function saveAs( $filename ) {
		$fh = fopen( $filename, 'wb' );
		if (!$fh) {
			return false;
		}
		if ( !$this->_write($fh) ) {
			fclose($fh);
			return false;
		}
		fclose($fh);

		return true;
	}

	public function download() {
		return $this->downloadAs( gmdate('YmdHi') . '.xlsx' );
	}

	public function downloadAs( $filename ) {
		$fh = fopen('php://memory','wb');
		if (!$fh) {
			return false;
		}

		if ( !$this->_write( $fh )) {
			fclose( $fh );
			return false;
		}

		$size = ftell($fh);

		header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T' , time() ));
		header('Content-Length: '.$size);

		while( ob_get_level() ) {
			ob_end_clean();
		}
		fseek($fh,0);
		fpassthru( $fh );

		fclose($fh);
		return true;
	}

	protected function _write( $fh ) {


		$dirSignatureE= "\x50\x4b\x05\x06"; // end of central dir signature
		$zipComments = 'Generated by '.__CLASS__.' PHP class, thanks sergey.shuchkin@gmail.com';

		if (!$fh) {
			return false;
		}

		$cdrec = '';	// central directory content
		$entries= 0;	// number of zipped files
		$cnt_sheets = count( $this->sheets );

		foreach ($this->template as $cfilename => $template ) {
			if ( $cfilename === 'xl/_rels/workbook.xml.rels' ) {
				$s = '';
				for ( $i = 0; $i < $cnt_sheets; $i++) {
					$s .= '<Relationship Id="rId'.($i+2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'.
						 ' Target="worksheets/sheet'.($i+1).".xml\"/>\n";
				}
				$s .= '<Relationship Id="rId'.($i+2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>';
				$template = str_replace('{SHEETS}', $s, $template);
				$this->_writeEntry($fh, $cdrec, $cfilename, $template);
				$entries++;
			} elseif ( $cfilename === 'xl/workbook.xml' ) {
				$s = '';
				foreach ( $this->sheets as $k => $v ) {
					$s .= '<sheet name="' . $this->esc( $v['name'] ) . '" sheetId="' . ( $k + 1) . '" state="visible" r:id="rId' . ( $k + 2) . '"/>';
				}
				$template = str_replace('{SHEETS}', $s, $template);
				$this->_writeEntry($fh, $cdrec, $cfilename, $template);
				$entries++;
			}
			elseif ( $cfilename === 'docProps/core.xml' ) {
				$template = str_replace('{DATE}', gmdate('Y-m-d\TH:i:s\Z'), $template);
				$this->_writeEntry($fh, $cdrec, $cfilename, $template);
				$entries++;
			} elseif ( $cfilename === 'xl/sharedStrings.xml' ) {
				if (!count($this->SI)) {
					$this->SI[] = 'No Data';
				}
				$si_cnt = count($this->SI);
				$si = '<si><t>'.implode("</t></si>\r\n<si><t>", $this->SI).'</t></si>';
				$template = str_replace(['{CNT}', '{STRINGS}'], [ $si_cnt, $si ], $template );
				$this->_writeEntry($fh, $cdrec, $cfilename, $template);
				$entries++;
			} elseif ( $cfilename === 'xl/worksheets/sheet1.xml' ) {
				foreach ( $this->sheets as $k => $v ) {
					$filename = 'xl/worksheets/sheet'.($k+1).'.xml';
					$xml = $this->_sheetToXML($k, $template);
					$this->_writeEntry($fh, $cdrec, $filename, $xml );
					$entries++;
				}
				$xml = null;
			} elseif ( $cfilename === 'xl/worksheets/_rels/sheet1.xml.rels' ) {
				foreach ( $this->sheets as $k => $v ) {
					if ( count($v['hyperlinks'])) {
						$RH = [];
						$filename = 'xl/worksheets/_rels/sheet' . ( $k + 1 ) . '.xml.rels';
						foreach ( $v['hyperlinks'] as $h ) {
							$RH[] = '<Relationship Id="' . $h['ID'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="' . $this->esc($h['H']) . '" TargetMode="External"/>';
						}
						$xml = str_replace( '{HYPERLINKS}', implode( "\r\n", $RH ), $template );
						$this->_writeEntry( $fh, $cdrec, $filename, $xml );
						$entries++;
					}
				}
				$xml = null;

			} elseif ( $cfilename === '[Content_Types].xml' ) {
				$TYPES = ['<Override PartName="/_rels/.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'];
				foreach ( $this->sheets as $k => $v) {
					$TYPES[] = '<Override PartName="/xl/worksheets/sheet'.($k+1).
							'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
					if (count( $v['hyperlinks'])) {
						$TYPES[] = '<Override PartName="/xl/worksheets/_rels/sheet'.($k+1).'.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
					}
				}
				$template = str_replace('{TYPES}', implode("\r\n", $TYPES), $template);
				$this->_writeEntry($fh, $cdrec, $cfilename, $template);
				$entries++;
			} elseif ( $cfilename === 'xl/styles.xml' ) {
				$FONTS = ['<fonts count="'.count($this->F).'">'];
				foreach ( $this->F as $f ) {
					$FONTS[] = '<font><name val="'.$this->defaultFont.'"/><family val="2"/>'
						. ( $this->defaultFontSize ? '<sz val="'.$this->defaultFontSize.'"/>' : '' )
						.( $f & self::F_BOLD ? '<b/>' : '')
						.( $f & self::F_ITALIC ? '<i/>' : '')
						.( $f & self::F_UNDERLINE ? '<u/>' : '')
						.( $f & self::F_STRIKE ? '<strike/>' : '')
						.( $f & self::F_HYPERLINK ? '<color rgb="FF0563C1"/><u/>' : '')
						.'</font>';
				}
				$FONTS[] = '</fonts>';
				$XF = ['<cellXfs count="'.count($this->XF).'">'];
				foreach( $this->XF as $xf ) {
					$align = ($xf[2] === self::A_LEFT ? ' applyAlignment="1"><alignment horizontal="left"/>' : '')
						.($xf[2] === self::A_RIGHT ? ' applyAlignment="1"><alignment horizontal="right"/>' : '')
						.($xf[2] === self::A_CENTER ? ' applyAlignment="1"><alignment horizontal="center"/>' : '');
					$XF[] = '<xf numFmtId="'.$xf[0].'" fontId="'.$xf[1].'" fillId="0" borderId="0" xfId="0"'
						.($xf[0] > 0 ? ' applyNumberFormat="1"' : '')
						.($align ? $align . '</xf>' : '/>');

				}
				$XF[] = '</cellXfs>';
				$template = str_replace(['{FONTS}','{XF}'], [implode("\r\n", $FONTS), implode("\r\n", $XF)], $template);
				$this->_writeEntry($fh, $cdrec, $cfilename, $template);
				$entries++;
			} else {
				$this->_writeEntry($fh, $cdrec, $cfilename, $template);
				$entries++;
			}
		}
		$before_cd = ftell($fh);
		fwrite($fh, $cdrec);

		// end of central dir
		fwrite($fh, $dirSignatureE);
		fwrite($fh, pack('v', 0)); // number of this disk
		fwrite($fh, pack('v', 0)); // number of the disk with the start of the central directory
		fwrite($fh, pack('v', $entries)); // total # of entries "on this disk"
		fwrite($fh, pack('v', $entries)); // total # of entries overall
		fwrite($fh, pack('V', mb_strlen($cdrec,'8bit')));     // size of central dir
		fwrite($fh, pack('V', $before_cd));         // offset to start of central dir
		fwrite($fh, pack('v', mb_strlen($zipComments,'8bit'))); // .zip file comment length
		fwrite($fh, $zipComments);

		return true;
	}

	protected function _writeEntry($fh, &$cdrec, $cfilename, $data) {
		$zipSignature = "\x50\x4b\x03\x04"; // local file header signature
		$dirSignature = "\x50\x4b\x01\x02"; // central dir header signature

		$e = [];
		$e['uncsize'] = mb_strlen($data, '8bit');

		// if data to compress is too small, just store it
		if($e['uncsize'] < 256){
			$e['comsize'] = $e['uncsize'];
			$e['vneeded'] = 10;
			$e['cmethod'] = 0;
			$zdata = $data;
		} else{ // otherwise, compress it
			$zdata = gzcompress($data);
			$zdata = substr(substr($zdata, 0, - 4 ), 2); // fix crc bug (thanks to Eric Mueller)
			$e['comsize'] = mb_strlen($zdata, '8bit');
			$e['vneeded'] = 10;
			$e['cmethod'] = 8;
		}

		$e['bitflag'] = 0;
		$e['crc_32']  = crc32($data);

		// Convert date and time to DOS Format, and set then
		$lastmod_timeS  = str_pad(decbin(date('s')>=32?date('s')-32:date('s')), 5, '0', STR_PAD_LEFT);
		$lastmod_timeM  = str_pad(decbin(date('i')), 6, '0', STR_PAD_LEFT);
		$lastmod_timeH  = str_pad(decbin(date('H')), 5, '0', STR_PAD_LEFT);
		$lastmod_dateD  = str_pad(decbin(date('d')), 5, '0', STR_PAD_LEFT);
		$lastmod_dateM  = str_pad(decbin(date('m')), 4, '0', STR_PAD_LEFT);
		$lastmod_dateY  = str_pad(decbin(date('Y')-1980), 7, '0', STR_PAD_LEFT);

		# echo "ModTime: $lastmod_timeS-$lastmod_timeM-$lastmod_timeH (".date("s H H").")\n";
		# echo "ModDate: $lastmod_dateD-$lastmod_dateM-$lastmod_dateY (".date("d m Y").")\n";
		$e['modtime'] = bindec("$lastmod_timeH$lastmod_timeM$lastmod_timeS");
		$e['moddate'] = bindec("$lastmod_dateY$lastmod_dateM$lastmod_dateD");

		$e['offset'] = ftell($fh);

		fwrite($fh, $zipSignature);
		fwrite($fh, pack('s', $e['vneeded'])); // version_needed
		fwrite($fh, pack('s', $e['bitflag'])); // general_bit_flag
		fwrite($fh, pack('s', $e['cmethod'])); // compression_method
		fwrite($fh, pack('s', $e['modtime'])); // lastmod_time
		fwrite($fh, pack('s', $e['moddate'])); // lastmod_date
		fwrite($fh, pack('V', $e['crc_32']));  // crc-32
		fwrite($fh, pack('I', $e['comsize'])); // compressed_size
		fwrite($fh, pack('I', $e['uncsize'])); // uncompressed_size
		fwrite($fh, pack('s', mb_strlen($cfilename, '8bit')));   // file_name_length
		fwrite($fh, pack('s', 0));  // extra_field_length
		fwrite($fh, $cfilename);    // file_name
		// ignoring extra_field
		fwrite($fh, $zdata);

		// Append it to central dir
		$e['external_attributes']  = (substr($cfilename, -1) === '/'&&!$zdata)?16:32; // Directory or file name
		$e['comments']             = '';

		$cdrec .= $dirSignature;
		$cdrec .= "\x0\x0";                  // version made by
		$cdrec .= pack('v', $e['vneeded']); // version needed to extract
		$cdrec .= "\x0\x0";                  // general bit flag
		$cdrec .= pack('v', $e['cmethod']); // compression method
		$cdrec .= pack('v', $e['modtime']); // lastmod time
		$cdrec .= pack('v', $e['moddate']); // lastmod date
		$cdrec .= pack('V', $e['crc_32']);  // crc32
		$cdrec .= pack('V', $e['comsize']); // compressed filesize
		$cdrec .= pack('V', $e['uncsize']); // uncompressed filesize
		$cdrec .= pack('v', mb_strlen($cfilename,'8bit')); // file name length
		$cdrec .= pack('v', 0);                // extra field length
		$cdrec .= pack('v', mb_strlen($e['comments'],'8bit')); // file comment length
		$cdrec .= pack('v', 0); // disk number start
		$cdrec .= pack('v', 0); // internal file attributes
		$cdrec .= pack('V', $e['external_attributes']); // internal file attributes
		$cdrec .= pack('V', $e['offset']); // relative offset of local header
		$cdrec .= $cfilename;
		$cdrec .= $e['comments'];
	}

	protected function _sheetToXML($idx, $template) {
		// locale floats fr_FR 1.234,56 -> 1234.56
		$_loc = setlocale(LC_NUMERIC, 0);
		setlocale(LC_NUMERIC,'C');
		$COLS = [];
		$ROWS = [];
		if ( count($this->sheets[$idx]['rows']) ) {
			$COLS[] = '<cols>';
			$CUR_ROW = 0;
			$COL = [];
			foreach( $this->sheets[$idx]['rows'] as $r ) {
				$CUR_ROW++;
				$row = '<row r="'.$CUR_ROW.'">';
				$CUR_COL = 0;
				foreach( $r as $v ) {
					$CUR_COL++;
					if ( !isset($COL[ $CUR_COL ])) {
						$COL[ $CUR_COL ] = 0;
					}
					if ( $v === null || $v === '' ) {
						continue;
					}

					$cname = $this->num2name($CUR_COL) . $CUR_ROW;

					$ct = $cv = null;
					$N = $F = $A = 0;

					if ( is_string($v) ) {

						if ( $v[0] === "\0" ) { // RAW value as string
							$v = substr($v,1);
							$vl = mb_strlen( $v );
						} else {
							if ( strpos( $v, '<' ) !== false ) { // tags?
								if ( strpos( $v, '<b>' ) !== false ) {
									$F += self::F_BOLD;
								}
								if ( strpos( $v, '<i>' ) !== false ) {
									$F += self::F_ITALIC;
								}
								if ( strpos( $v, '<u>' ) !== false ) {
									$F += self::F_UNDERLINE;
								}
								if ( strpos( $v, '<s>' ) !== false ) {
									$F += self::F_STRIKE;
								}
								if ( strpos( $v, '<left>' ) !== false ) {
									$A += self::A_LEFT;
								}
								if ( strpos( $v, '<center>' ) !== false ) {
									$A += self::A_CENTER;
								}
								if ( strpos( $v, '<right>' ) !== false ) {
									$A += self::A_RIGHT;
								}
								if ( preg_match( '/<a href="(https?:\/\/[^"]+)">(.*?)<\/a>/i', $v, $m ) ) {
									$h = explode( '#', $m[1] );
									$this->sheets[ $idx ]['hyperlinks'][] = ['ID' => 'rId' . ( count( $this->sheets[ $idx ]['hyperlinks'] ) + 1 ), 'R' => $cname, 'H' => $h[0], 'L' => isset( $h[1] ) ? $h[1] : ''];
									$F = self::F_HYPERLINK; // Hyperlink
								}
								if ( preg_match( '/<a href="(mailto?:[^"]+)">(.*?)<\/a>/i', $v, $m ) ) {
									$this->sheets[ $idx ]['hyperlinks'][] = ['ID' => 'rId' . ( count( $this->sheets[ $idx ]['hyperlinks'] ) + 1 ), 'R' => $cname, 'H' => $m[1], 'L' => ''];
									$F = self::F_HYPERLINK; // mailto hyperlink
								}
								$v = strip_tags( $v );
							} // tags
							$vl = mb_strlen( $v );
							if ( $v === '0' || preg_match( '/^[-+]?[1-9]\d{0,14}$/', $v ) ) { // Integer as General
								$cv = ltrim( $v, '+' );
								if ( $vl > 10 ) {
									$N = self::N_INT; // [1] 0
								}
							} elseif ( preg_match( '/^[-+]?(0|[1-9]\d*)\.(\d+)$/', $v, $m ) ) {
								$cv = ltrim( $v, '+' );
								if ( strlen( $m[2] ) < 3 ) {
									$N = self::N_DEC;
								}
							} elseif ( preg_match( '/^([-+]?\d+)%$/', $v, $m ) ) {
								$cv = round( $m[1] / 100, 2 );
								$N = self::N_PERCENT_INT; // [9] 0%
							} elseif ( preg_match( '/^([-+]?\d+\.\d+)%$/', $v, $m ) ) {
								$cv = round( $m[1] / 100, 4 );
								$N = self::N_PRECENT_DEC; // [10] 0.00%
							} elseif ( preg_match( '/^(\d\d\d\d)-(\d\d)-(\d\d)$/', $v, $m ) ) {
								$cv = $this->date2excel( $m[1], $m[2], $m[3] );
								$N = self::N_DATE; // [14] mm-dd-yy
							} elseif ( preg_match( '/^(\d\d)\/(\d\d)\/(\d\d\d\d)$/', $v, $m ) ) {
								$cv = $this->date2excel( $m[3], $m[2], $m[1] );
								$N = self::N_DATE; // [14] mm-dd-yy
							} elseif ( preg_match( '/^(\d\d):(\d\d):(\d\d)$/', $v, $m ) ) {
								$cv = $this->date2excel( 0, 0, 0, $m[1], $m[2], $m[3] );
								$N = self::N_TIME; // time
							} elseif ( preg_match( '/^(\d\d\d\d)-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)$/', $v, $m ) ) {
								$cv = $this->date2excel( $m[1], $m[2], $m[3], $m[4], $m[5], $m[6] );
								$N = self::N_DATETIME; // [22] m/d/yy h:mm
							} elseif ( preg_match( '/^(\d\d)\/(\d\d)\/(\d\d\d\d) (\d\d):(\d\d):(\d\d)$/', $v, $m ) ) {
								$cv = $this->date2excel( $m[3], $m[2], $m[1], $m[4], $m[5], $m[6] );
								$N = self::N_DATETIME; // [22] m/d/yy h:mm
							} elseif ( preg_match( '/^[0-9+-.]+$/', $v ) ) { // Long ?
								$A = self::A_RIGHT;
							} elseif ( preg_match( '/^https?:\/\/\S+$/i', $v ) ) {
								$h = explode( '#', $v );
								$this->sheets[ $idx ]['hyperlinks'][] = ['ID' => 'rId' . ( count( $this->sheets[ $idx ]['hyperlinks'] ) + 1 ), 'R' => $cname, 'H' => $h[0], 'L' => isset( $h[1] ) ? $h[1] : ''];
								$F = self::F_HYPERLINK; // Hyperlink
							} elseif ( preg_match( "/^[a-zA-Z0-9_\.\-]+@([a-zA-Z0-9][a-zA-Z0-9\-]*\.)+[a-zA-Z]{2,}$/", $v ) ) {
								$this->sheets[ $idx ]['hyperlinks'][] = ['ID' => 'rId' . ( count( $this->sheets[ $idx ]['hyperlinks'] ) + 1 ), 'R' => $cname, 'H' => 'mailto:' . $v, 'L' => ''];
								$F = self::F_HYPERLINK; // Hyperlink
							}
						}
						if ( !$cv) {

							$v = $this->esc( $v );

							if ( mb_strlen( $v ) > 160 ) {
								$ct = 'inlineStr';
								$cv = $v;
							} else {
								$ct = 's'; // shared string
								$cv = false;
								$skey = '~' . $v;
								if ( isset( $this->SI_KEYS[ $skey ] ) ) {
									$cv = $this->SI_KEYS[ $skey ];
								}
								if ( $cv === false ) {
									$this->SI[] = $v;
									$cv = count( $this->SI ) - 1;
									$this->SI_KEYS[ $skey ] = $cv;
								}
							}
						}
					} elseif ( is_int( $v ) ) {
						$vl = mb_strlen( (string) $v );
						$cv = $v;
					} elseif ( is_float( $v ) ) {
						$vl = mb_strlen( (string) $v );
						$cv = $v;
					} elseif ( $v instanceof DateTime ) {
						$vl = 16;
						$cv = $this->date2excel( $v->format('Y'), $v->format('m'), $v->format('d'), $v->format('H'), $v->format('i'), $v->format('s') );
						$N = self::N_DATETIME; // [22] m/d/yy h:mm
					} else {
						continue;
					}

					$COL[ $CUR_COL ] = max( $vl, $COL[ $CUR_COL ] );

					$cs = 0;
					if ( $N + $F + $A > 0 ) {

						if ( isset($this->F_KEYS[ $F ] ) ) {
							$cf = $this->F_KEYS[ $F ];
						} else {
							$cf = count($this->F);
							$this->F_KEYS[$F] = $cf;
							$this->F[] = $F;
						}
						$NFA = 'N' . $N . 'F' . $cf . 'A' . $A;
						if ( isset( $this->XF_KEYS[ $NFA ] ) ) {
							$cs = $this->XF_KEYS[ $NFA ];
						}
						if ( $cs === 0 ) {
							$cs = count( $this->XF );
							$this->XF_KEYS[ $NFA ] = $cs;
							$this->XF[] = [$N, $cf, $A];
						}
					}

					$row .= '<c r="' . $cname . '"'.($ct ? ' t="'.$ct.'"' : '').($cs ? ' s="'.$cs.'"' : '').'>'
					        .($ct === 'inlineStr' ? '<is><t>'.$cv.'</t></is>' : '<v>' . $cv . '</v>')."</c>\r\n";
				}
				$ROWS[] = $row . "</row>\r\n";
			}
			foreach ( $COL as $k => $max ) {
				$COLS[] = '<col min="'.$k.'" max="'.$k.'" width="'.min( $max+1, 60).'" />';
			}
			$COLS[] = '</cols>';
			$REF = 'A1:'.$this->num2name(count($COLS)) . $CUR_ROW;
		} else {
			$ROWS[] = '<row r="1"><c r="A1" t="s"><v>0</v></c></row>';
			$REF = 'A1:A1';
		}
		$HYPERLINKS = [];
		if ( count( $this->sheets[$idx]['hyperlinks']) ) {
			$HYPERLINKS[] = '<hyperlinks>';
			foreach ( $this->sheets[$idx]['hyperlinks'] as $h ) {
				$HYPERLINKS[] = '<hyperlink ref="' . $h['R'] . '" r:id="' . $h['ID'] . '" location="' . $this->esc( $h['L'] ) . '" display="' . $this->esc( $h['H'] . ( $h['L'] ? ' - ' . $h['L'] : '' ) ) . '" />';
			}
			$HYPERLINKS[] = '</hyperlinks>';
		}
		//restore locale
		setlocale(LC_NUMERIC, $_loc);

		return str_replace(['{REF}','{COLS}','{ROWS}','{HYPERLINKS}'],
			[ $REF, implode("\r\n", $COLS), implode("\r\n",$ROWS), implode("\r\n", $HYPERLINKS) ],
			$template );
	}

	public function num2name($num) {
		$numeric = ($num - 1) % 26;
		$letter  = chr( 65 + $numeric );
		$num2    = (int) ( ($num-1) / 26 );
		if ( $num2 > 0 ) {
			return $this->num2name( $num2 ) . $letter;
		}
		return $letter;
	}

	public function date2excel($year, $month, $day, $hours=0, $minutes=0, $seconds=0) {
	    $excelTime = (($hours * 3600) + ($minutes * 60) + $seconds) / 86400;

	    if ( $year === 0 ) {
	        return $excelTime;
        }

		// self::CALENDAR_WINDOWS_1900
		$excel1900isLeapYear = True;
		if (((int)$year === 1900) && ($month <= 2)) { $excel1900isLeapYear = False; }
		$myExcelBaseDate = 2415020;

		//    Julian base date Adjustment
		if ($month > 2) {
			$month -= 3;
		} else {
			$month += 9;
			--$year;
		}
		//    Calculate the Julian Date, then subtract the Excel base date (JD 2415020 = 31-Dec-1899 Giving Excel Date of 0)
		$century = substr($year,0,2);
		$decade = substr($year,2,2);
		$excelDate = floor((146097 * $century) / 4) + floor((1461 * $decade) / 4) + floor((153 * $month + 2) / 5) + $day + 1721119 - $myExcelBaseDate + $excel1900isLeapYear;

		return (float) $excelDate + $excelTime;
	}
	public function setDefaultFont( $name ) {
		$this->defaultFont = $name;
		return $this;
	}
	public function setDefaultFontSize( $size ) {
		$this->defaultFontSize = $size;
		return $this;
	}
	public function esc( $str ) {
		// XML UTF-8: #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
		// but we use fast version
		return str_replace( ['&', '<', '>', "\x00","\x03","\x0B"], ['&amp;', '&lt;', '&gt;', '', '', ''], $str );
	}
}
