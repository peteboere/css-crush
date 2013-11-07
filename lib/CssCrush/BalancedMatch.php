<?php
/**
 *
 *  Balanced bracket matching on the main stream.
 *
 */
namespace CssCrush;

class BalancedMatch
{
    public function __construct(Stream $stream, $offset, $brackets = '{}')
    {
        $this->stream = $stream;
        $this->offset = $offset;
        $this->match = null;
        $this->length = 0;

        list($opener, $closer) = str_split($brackets, 1);

        if (strpos($stream->raw, $opener, $this->offset) === false) {

            return;
        }

        if (substr_count($stream->raw, $opener) !== substr_count($stream->raw, $closer)) {
            $sample = substr($stream->raw, $this->offset, 25);
            CssCrush::$config->logger->warning("[[CssCrush]] - Unmatched token near '$sample'.");

            return;
        }

        $patt = ($opener === '{') ? Regex::$patt->block : Regex::$patt->parens;

        if (preg_match($patt, $stream->raw, $m, PREG_OFFSET_CAPTURE, $this->offset)) {

            $this->match = $m;
            $this->matchLength = strlen($m[0][0]);
            $this->matchStart = $m[0][1];
            $this->matchEnd = $this->matchStart + $this->matchLength;
            $this->length = $this->matchEnd - $this->offset;
        }
        else {
            CssCrush::$config->logger->warning("[[CssCrush]] - Could not match '$opener'. Exiting.");
        }
    }

    public function inside()
    {
        return $this->match[2][0];
    }

    public function whole()
    {
        return substr($this->stream->raw, $this->offset, $this->length);
    }

    public function replace($replacement)
    {
        $this->stream->splice($replacement, $this->offset, $this->length);
    }

    public function unWrap()
    {
        $this->stream->splice($this->inside(), $this->offset, $this->length);
    }

    public function nextIndexOf($needle)
    {
        return strpos($this->stream->raw, $needle, $this->offset);
    }
}
