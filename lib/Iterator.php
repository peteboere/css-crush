<?php
/**
 *
 * Iterators used during parsing
 *
 */

class csscrush_block_iterator implements Iterator, Countable {

	protected $matches;
	protected $options;
	protected $index = 0;

	function count () { return count( $this->matches ); }
	function next () { $this->index++; }
	function rewind () { $this->index = 0; }
	function valid () { return isset( $this->matches[ $this->index ] ); }
	function key () { return $this->index; }
	function current () { return $this->matches[ $this->index ]; }
}


class csscrush_atrule_iterator extends csscrush_block_iterator {


	public function __construct ( $options ) {

		$this->options = (object) $options;

		$stream = $this->options->input;
		$search = $this->options->search;

		$patt = '!' . $search . '\s?([^\{]*)\{!';
		preg_match_all( $patt, $stream, $this->matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER  );
		
		if ( $this->options->direction === 'reverse' ) {
			$this->matches = array_reverse( $this->matches );
		}
	}


	function current () {
		csscrush::log( $this->matches[ $this->index ] );
		
		$match = $this->matches[ $this->index ];
		
		$out = new stdclass();
		
		$out->before = '';
		$out->after = '';
		$out->content = '';
		$out->arguments = '';

		return $out;
	}

}


class csscrush_ruletoken_iterator extends csscrush_block_iterator {
}


