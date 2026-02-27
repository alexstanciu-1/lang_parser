<?php

namespace lang_parser\tokenizers;

final class phpbase
{
	/**
	 * The standard for a token must be:
	 *		[
	 *			0 => int|string , either a int definition or the content itself if string
	 *			1 => string , the content of the token
	 *			2 => line number (starting with 1) (optional - without it you will get ambigous complie messages)
	 *			3 => position in the line (starting with 1) (optional - without it you will get ambigous complie messages)
	 *		]
	 * 
	 * @param string $content
	 * @return array
	 */
	public static function get_tokens(string $content)
	{
		$tokens = token_get_all($content);
		
		# standardize tokens : int|string , string , line, char
		$c_line = 0;
		$char_offset = 0;
		$std_toks = [];
		foreach ($tokens as $tok) {
			if (is_string($tok)) {
				$std_toks[] = [$tok, $tok, $c_line, $char_offset + 1];
				$char_offset += strlen($tok);
			}
			else { # array
				list ($int, $char, $line) = $tok;
				$new_lines = substr_count($char, "\n");
				$std_toks[] = [$int, $char, $line, $char_offset + 1];
				if ($new_lines > 0) {
					$c_line += $new_lines;
					# reset the char position
					$char_offset = strlen($char) - strrpos($char, "\n") - 1;
				}
				else {
					$c_line = $line;
					$char_offset += strlen($char);
				}
			}
		}
		
		return $std_toks;
	}
}
