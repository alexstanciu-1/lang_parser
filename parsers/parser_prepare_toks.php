<?php

namespace lang_parser\parsers;

trait parser_prepare_toks
{
	protected static final function prepare_tokens(array $tokens, int $pos)
	{
		$collected = [];
		$collected_data = [];
		$collected_pos = 0;
		
		$prev_tok_key = null;
		while (($tok = ($tokens[$pos] ?? null))) {
			
			$tok_key = is_string($tok) ? $tok : $tok[0];
			$delim = is_string($tok) ? (static::$tokens_delim[$tok] ?? null) : null;
			
			if ($delim !== null) {
				
				$collected[] = $tok;
				$collected_data[] = [$tok];
				$collected_pos++;
			}
			else {
				$tok_is = static::$tokens_map[$tok_key] ?? null;
				if ($tok_is === null) {
					var_dump('$tok_key', $tok_key);
					throw new \Exception('Parse error. Undefined token: $tok_key=`'.$tok_key.'` | `' .json_encode($tok).'` @position: ' . $pos);
				}
				# space and comment
				else if ((($tok_is === '\s') || ($tok_is === '\c')) && static::$group_space_and_comment) {
					
					# space with/without comment is a space ... comment is ignored
					# comment without space is comment
					
					# add it and continue, always collect one step ahead
					if (($prev_collected = $collected[$collected_pos - 1])
						 && ($prev_tok_key = (is_string($prev_collected) ? $prev_collected : $prev_collected[0]))
						 && (($prev_tok_key === '\s') || ($prev_tok_key === '\c')))
					{
						# append it to the prev position if it's still whitespace
						$collected_data[$collected_pos - 1][] = $tok;
						if ($tok_is === '\s') {
							# ensure it's a space if prev was a comment `\c`
							$collected[$collected_pos - 1] = $tok_is;
						}
					}
					else {
						# add it normally
						$collected[$collected_pos] = $tok_is;
						$collected_data[$collected_pos] = [$tok];
						$collected_pos++;
					}
				}
				else {
					$collected[] = $tok_is;
					$collected_data[] = [$tok];
					$collected_pos++;
				}
			}
			$pos++;
			$prev_tok_key = $tok_key;
		}
		
		$rx_tokens = $collected;
		$rx_tokens_source = $collected_data;
		
		if (static::$extra_space_and_comment_allowed_everywhere) {
			# optimization
			$new_rx = [];
			$new_rx_src = [];
			$count_spaces = 0;
			$count_normal = 0;
			$last_space = null;
			foreach ($rx_tokens as $pos => $t) {
				if (($t === '\s') || ($t === '\c')) {
					$count_spaces++;
					if (isset($last_space)) {
						throw new \Exception('We made a mistake on the previus step. There should be no consecutive spaces/comments.');
					}
					$last_space = $rx_tokens_source[$pos];
				}
				else {
					$tok_src = $rx_tokens_source[$pos];
					if (isset($last_space)) {
						$tok_src['s'] = $last_space;
						$last_space = null;
					}
					$count_normal++;
					$new_rx[] = $t;
					$new_rx_src[] = $tok_src;
				}
			}
			$rx_tokens = $new_rx;
			$rx_tokens_source = $new_rx_src;
		}
		else {
			throw new \Exception('The code is not tested with `$extra_space_and_comment_allowed_everywhere` disabled');
		}
		
		return [$rx_tokens, $rx_tokens_source];
	}
	
	public static final function print_prepared_tokens(array $tokens, int $pos = 0, int $len = null)
	{
		$max = $pos + ($len ?? count($tokens));
		
		$field_len = 0;
		for ($i = $pos; $i < $max; $i++) {
			$tok = $tokens[$i] ?? null;
			$m = is_string($tok[0]) ? strlen($tok[0]) : strlen($tok[1] ?? '');
			if ($field_len < $m) {
				$field_len = $m;
			}
		}
		
		for ($pos; $pos < $max; $pos++) {
			$tok = $tokens[$pos] ?? null;
			echo str_pad($tok[0], $field_len, " ", STR_PAD_LEFT), "\t", str_pad((string)$pos, 5, " ", STR_PAD_LEFT), "\n";
		}
	}
	
	public static final function print_tokens(array $tokens, int $pos = 0, int $len = null)
	{
		$max = $pos + ($len ?? count($tokens));
		
		$field_len = 0;
		for ($i = $pos; $i < $max; $i++) {
			$tok = $tokens[$i] ?? null;
			$m = is_string($tok) ? strlen($tok) : strlen($tok[1] ?? '');
			if ($field_len < $m) {
				$field_len = $m;
			}
		}
		
		for ($pos; $pos < $max; $pos++) {
			$tok = $tokens[$pos] ?? null;
			if (is_string($tok)) {
				echo str_pad($tok, $field_len, " ", STR_PAD_LEFT), "\t", str_pad((string)$pos, 5, " ", STR_PAD_LEFT), "\n";
			}
			else {
				echo str_pad(substr(json_encode($tok[1]), 1, -1), $field_len, " ", STR_PAD_LEFT), "\t", str_pad((string)$pos, 5, " ", STR_PAD_LEFT), "  ", token_name($tok[0]), "\n";
			}
		}
	}
}
