<?php

namespace lang_parser\parsers;

trait parser_utility
{
	private static function print_results_html(array $ordered, array $tokens, array $tokens_source, int $count, int &$pos = 0, $parent = null, string $tabs = "", int $depth = 0)
	{
		if ($depth === 0) {
			?>
<style>
	
</style><script type="text/javascript">

window.addEventListener('mouseover', function ($event) {
	if ($event.target.classList.contains('match-group')) {
		$event.target.style.backgroundColor = 'yellow';
	}
	if ($event.target.classList.contains('match-e')) {
		$event.target.style.textDecoration = "underline";
		$event.target.closest('.match-group').style.backgroundColor = 'yellow';
	}
});
window.addEventListener('mouseout', function ($event) {
	if ($event.target.classList.contains('match-group')) {
		$event.target.style.backgroundColor = '';
	}
	if ($event.target.classList.contains('match-e')) {
		$event.target.style.textDecoration = "none";
		$event.target.closest('.match-group').style.backgroundColor = '';
	}
});

</script><?php
		}
		
		while ($pos < $count) {
			$item = $ordered[$pos];
			$regex_tok = $tokens[$item->pos - 1];
			$tok_str = null;
			if ($pos > 0) {
				$entry = $tokens_source[$item->pos - 1] ?? null;
				if (!empty($entry)) {
					
					$whitespace = $entry['s'] ?? null;
					if (isset($whitespace)) {
						unset($entry['s']);
					}

					$tok = $entry[0];
					if ($tok) {
						$tok_str = '';
						foreach ($whitespace as $sp) {
							$tok_str .= is_array($sp) ? $sp[1] : $sp;
						}
						if (($tok === '_T_FILE_START_') || ($tok === '_T_FILE_END_')) {
							$tok_str .= '';
						}
						else {
							$tok_str .= is_array($tok) ? $tok[1] : $tok;
						}
						
						$t_count = count($entry);
						for ($i = 1; $i < $t_count; $i++) {
							$sp = $entry[$i];
							$tok_str .= is_array($sp) ? $sp[1] : $sp;
						}
					}
				}
			}
			else {
				# this is the file start flag
				# $tok = 'n/a';
			}
			# increment here
			$pos++;
			
			if ($item->mark[0] === '>') { # enter
				echo "<span title='{$item->mark}' class='match-group'>";
				static::print_results_html($ordered, $tokens, $tokens_source, $count, $pos, $item, $tabs."\t", $depth + 1);
			}
			else if ($item->mark[0] === '<') { # exit
				echo "</span>";
				return;
			}
			else {
				echo "<span title='{$regex_tok} | {$parent->mark}' class='match-e'>";
				echo htmlentities($tok_str);
				echo "</span>";
			}
		}
	}
}
