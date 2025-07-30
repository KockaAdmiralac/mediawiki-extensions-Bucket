<?php

namespace MediaWiki\Extension\Bucket;

use MediaWiki\Api\ApiMain;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use OOUI;
use stdClass;

class BucketPageHelper {
	/**
	 * @param WebRequest $existing_request
	 * @param string $bucket
	 * @param string $select
	 * @param string $where
	 * @param int $limit
	 * @param int $offset
	 * @return stdClass
	 */
	public static function runQuery( $existing_request, $bucket, $select, $where, $limit, $offset ) {
		$params = new DerivativeRequest(
			$existing_request,
			[
				'action' => 'bucket',
				'bucket' => $bucket,
				'select' => $select,
				'where' => $where,
				'limit' => $limit,
				'offset' => $offset
			]
		);
		$api = new ApiMain( $params );
		$api->execute();
		return $api->getResult()->getResultData();
	}

	private static function formatValue( mixed $value, string $dataType, bool $repeated ): string {
		if ( $repeated ) {
			if ( !is_array( $value ) ) {
				$json = json_decode( $value );
			} else {
				$json = $value;
			}
			$returns = [];
			foreach ( $json as $val ) {
				$formatted_val = self::formatValue( $val, $dataType, false );
				if ( $formatted_val !== '' ) {
					$returns[] = '<li class="bucket-list">' . $formatted_val;
				}
			}
			return implode( '', $returns );
		}
		if ( $dataType === 'PAGE' && strlen( $value ) > 0 ) {
			return '[[:' . wfEscapeWikiText( $value ) . ']]';
		}
		if ( $dataType === 'TEXT' ) {
			return wfEscapeWikiText( $value );
		}
		if ( $dataType === 'BOOLEAN' ) {
			if ( $value ) {
				return 'True';
			} else {
				return 'False';
			}
		}
		return $value;
	}

	/**
	 * @param array $schema
	 * @param array|null $fields
	 * @param stdClass $result
	 * @return string
	 */
	public static function getResultTable( $schema, $fields, $result ) {
		if ( isset( $fields ) && count( $fields ) > 0 ) {
			$output[] = '<table class="wikitable"><tr>';
			$keys = [];
			foreach ( array_keys( $schema ) as $key ) {
				if ( in_array( $key, $fields ) ) {
					$keys[] = $key;
					$output[] = "<th>$key</th>";
				}
			}
			foreach ( $result as $row ) {
				$output[] = '<tr>';
				foreach ( $keys as $key ) {
					if ( isset( $row[$key] ) ) {
						$output[] = '<td>' . self::formatValue(
							$row[$key], $schema[$key]['type'], $schema[$key]['repeated'] ) . '</td>';
					} else {
						$output[] = "<td>''Null''</td>";
					}
				}
				$output[] = '</tr>';
			}
			$output[] = '</table>';
			return implode( '', $output );
		}
		return '';
	}

	/**
	 * @param Title $title
	 * @param int $limit
	 * @param int $offset
	 * @param array $query
	 * @param bool $hasNext
	 * @return OOUI\ButtonGroupWidget
	 */
	public static function getPageLinks( $title, $limit, $offset, $query, $hasNext = true ) {
		$links = [];

		$previousOffset = max( 0, $offset - $limit );
		$links[] = new OOUI\ButtonWidget( [
			'href' => $title->getLocalURL( [ 'limit' => $limit, 'offset' => max( 0, $previousOffset ) ] + $query ),
			'title' => wfMessage( 'bucket-previous-results', $limit ),
			'label' => wfMessage( 'bucket-previous' ) . " $limit",
			'disabled' => ( $offset === 0 )
		] );

		foreach ( [ 20, 50, 100, 250, 500 ] as $num ) {
			$query = [ 'limit' => $num, 'offset' => $offset ] + $query;
			$tooltip = "Show $num results per page.";
			$links[] = new OOUI\ButtonWidget( [
				'href' => $title->getLocalURL( $query ),
				'title' => $tooltip,
				'label' => $num,
				'active' => ( $num === $limit )
			] );
		}

		$links[] = new OOUI\ButtonWidget( [
			'href' => $title->getLocalURL( [ 'limit' => $limit, 'offset' => $offset + $limit ] + $query ),
			'title' => wfMessage( 'bucket-next-results', $limit ),
			'label' => wfMessage( 'bucket-next' ) . " $limit",
			'disabled' => !$hasNext
		] );

		return new OOUI\ButtonGroupWidget( [ 'items' => $links ] );
	}

	/**
	 * Escapes input and wraps in a standard error format.
	 * @return string
	 */
	public static function printError( string $msg ) {
		return '<strong class="error bucket-error">' . wfEscapeWikiText( $msg ) . '</strong>';
	}
}
