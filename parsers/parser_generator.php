<?php

namespace lang_parser\parsers;

trait parser_generator
{
	protected static final function generate_parser(string $lang)
	{
		# @todo at the moment this works ... `get_all_matches` mode (greedy and non-greedy)
		#		we should switch to a get-one-match mode (for entire) + greedy mode
		#		in the end only one option should be valid (op precedence should be considered too)
		
		$methods = [];
		foreach (static::$defs_setup as $name => $regex_node)
		{
			$output = [];
			$output[] = "\npublic static function rex_".substr($name, 1)."(array \$solutions, array \$tokens, array \$tokens_str, int \$depth = 0)\n{\n";
			$output[] = "\tif (\$depth > 500) throw new \\Exception('tooo deep!');\n";

			$output[] = "\tself::\$stats['enter']['{$name}']++;\n";
			
			$output[] = "\tforeach(\$solutions as &\$ms) \$ms = new rex_pos(\$ms->pos, \$ms, \">\\\$".substr($name, 1)."\"); unset(\$ms);\n\n";

			$allow_space_or_comment_before = static::$extra_space_and_comment_allowed_everywhere;
			
			foreach ($regex_node->list ?? [] as $node) {
				static::regex_generate_code($name, $node, $output, $allow_space_or_comment_before);
			}
			
			$output[] = "\tforeach(\$solutions as &\$ms) \$ms = new rex_pos(\$ms->pos, \$ms, \"<\\\$".substr($name, 1)."\"); unset(\$ms);\n\n";
			
			$output[] = "\tself::\$stats['found']['{$name}']++;\n";
			
			$output[] = "\treturn [!empty(\$solutions), \$solutions];\n";
			$output[] = "}\n";
					
			$methods[$name] = implode("", $output);
		}
		
		foreach ($methods as &$m) {
			# one more tab inside the class to look good
			$m = "\t".str_replace("\n", "\n\t", $m);
		}
		unset($m);
		
		$ns = __NAMESPACE__ . '\\lang_' . $lang ;
		$class_name = "regex";
		$file = "<?php\n\n"."namespace {$ns};\n\n".
				'final class rex_pos
{
	public ?rex_pos $parent = null;
	public array $children = [];
	public function __construct(
			public ?int $pos,
			public ?rex_pos $prev,
			public string $mark = ""
	) {}
}'."\n\n".
				"final class {$class_name}\n{\n" . 
				"\n\tpublic static \$stats = [];\n".
				implode("", $methods) . "\n}\n";
		
		$f_path = __DIR__."/lang_{$lang}/{$class_name}.gen.php";
		if (file_get_contents($f_path) !== $file) {
			file_put_contents($f_path, $file);
			touch($f_path);
			clearstatcache($f_path);
		}
		
		return [ $ns ? $ns."\\".$class_name : $class_name , $f_path ];
	}
	
	protected static final function get_min_max_from_multiplier(int|string $multiplier)
	{
		if ($multiplier === '?') {
			return [0, 1]; # [$min, $max]
		}
		else if ($multiplier === '+') {
			return [1, static::max_multiplier]; # [$min, $max]
		}
		else if ($multiplier === '*') {
			return [0, static::max_multiplier]; # [$min, $max]
		}
		else if ($multiplier === '?') {
			return [0, 1]; # [$min, $max]
		}
		else if (is_int($multiplier)) {
			if (($multiplier < 1) || ($multiplier > static::max_multiplier)) {
				throw new \Exception('Bad node setup. Multiplier must be a positive int larger than `0` and smaller than than '.static::max_multiplier);
			}
			return [$multiplier, $multiplier]; # [$min, $max]
		}
		else {
			throw new \Exception('Bad node setup. Multiplier: '.$multiplier);
		}
	}
	
	protected static final function regex_generate_code(string $regex_name, object $node, array &$output, bool &$allow_space_or_comment_before, 
															bool $wrap_done = false, bool $inside_loop = false, int $depth = 0, string $tabs = "\t")
	{
		# WE TAKE A NODE AND WE GENERATE THE CODE FOR IT
		$debug = false;
		$debug_echo = false;
		
		$match = $node->{0} ?? null;
		$multiplier = isset($match) ? ($node->{1} ?? 1) : ($node->req_count ?? 1);
		list($min, $max) = static::get_min_max_from_multiplier($multiplier);
		
		$node_wrap = (!$wrap_done) && (($max > 1) || ($min === 0) || (($node->option ?? false) && (count($node->list ?? []) > 1))); # $node->is_call
		
		# loops && options/either
		$print_name = static::get_node_print_name($node);
		
		if ($debug_echo) {
			$output[] = $tabs."echo \"".addslashes($tabs)."\".'START ".addslashes($print_name)."'.\" | solutions: \".count(".($inside_loop ? '$mset_loop' : '$solutions').").\"\\n\";\n";
		}
		
		if ($node->tag) {
			$output[] = $inside_loop ? 
							"\tforeach(\$mset_loop as &\$ms) \$ms = new rex_pos(\$ms->pos, \$ms, \">{$node->tag}\"); unset(\$ms);\n\n" :
							"\tforeach(\$solutions as &\$ms) \$ms = new rex_pos(\$ms->pos, \$ms, \">{$node->tag}\"); unset(\$ms);\n\n";
		}
		
		if ((!$wrap_done) && (!($node->option ?? false))) {
			$output[] = $inside_loop ? 
							$tabs.'$loop_set = '.(($min === 0) ? '$mset_loop' : '[]').";\n" :
							$tabs.'$new_sol = '.(($min === 0) ? '$solutions' : '[]').";\n";
		}

		if ($node_wrap) {
			# before
			$output[] = $tabs."# START WRAP\n";
			# using function `use`, makes it a bit faster 
			$output[] = $tabs."list(\$is_match, \$rule_set) = (function (array \$solutions, array \$tokens, array \$tokens_str, array &\$new_sol = null) use (\$depth) {\n";
			
			$tabs .= "\t";
			static::regex_generate_code($regex_name, $node, $output, $allow_space_or_comment_before, true, false, $depth, $tabs);
			$output[] = $tabs."return [!empty(\$new_sol), \$new_sol];\n";
			
			$tabs = substr($tabs, 1);
			$output[] = $inside_loop ? 
							$tabs."})(\$mset_loop, \$tokens, \$tokens_str);\n" :
							$tabs."})(\$solutions, \$tokens, \$tokens_str);\n";
			
			$var_new_sol = $inside_loop ? '$loop_set' : '$new_sol';
			$output[] = $tabs."if (\$is_match) foreach (\$rule_set as \$r_set) {$var_new_sol}[] = \$r_set; \n";
			# after
			$output[] = $tabs."# END WRAP\n";
		}
		else 
		{
			$has_loop = ($max > 1);
			
			if ($has_loop) {
				$output[] = $tabs."# LOOP START\n";
				$output[] = $tabs."\$mset_loop = \$solutions; \$i = 1;\n";
				$output[] = $tabs."do {\n";
				$tabs .= "\t";
				$output[] = $tabs."\$loop_set = [];\n";
			}
			
			$var_solutions = $inside_loop || $has_loop ? '$mset_loop' : '$solutions';
			$var_new_sol = $inside_loop || $has_loop ? '$loop_set' : '$new_sol';
			
			if ($node->is_call) {
				
				$output[] = $tabs."list(\$is_match, \$rule_set) = self::rex_".substr($match, 1)."({$var_solutions}, \$tokens, \$tokens_str, \$depth + 1);\n";
				$output[] = $tabs."if (\$is_match) {".
							" foreach (\$rule_set as \$c_set) ".
								"{$var_new_sol}[] = \$c_set; } # AAAA\n";
				
			}
			else if ($match) {

				$out_consume_spaces = '';
				if (static::$extra_space_and_comment_allowed_everywhere) {
					# handle spaces !
					if ($node->no_space_before) {
						# @TODO !!!! NO SPACE BEFORE !!!
					}
					# else ... nothing to do
					$allow_space_or_comment_before = static::$extra_space_and_comment_allowed_everywhere;
				}
				else {
					throw new \Exception('The code is not tested with `$extra_space_and_comment_allowed_everywhere` disabled');
					/* KEEP THIS CODE IN CASE WE IMPLEMENT ANOTHER `SPACE MANAGE MODE`!!!! it `eats` space
					# consume spaces and comments
					$out_consume_spaces = $tabs."\twhile ((\$tokens[\$pos] === '\s') || (\$tokens[\$pos] === '\c')) ".
													"\$ms = new rex_pos(++\$pos, \$ms, 'space');"." # consume whitespace & comments\n";
					*/
				}
				
				$cmp_match = (($match[0] === '\\') && (strlen($match) === 2) && ($match[1] !== 's') && ($match[1] !== 'c')) ? $match[1] : $match;
				$var_tokens = '$tokens';
				if (static::$match_alternatives[$cmp_match] ?? false) {
					$var_tokens = '$tokens_str';
				}
				$export_match = var_export($cmp_match, true);
				
				if ($debug_echo) {
					$output[] = $tabs."\t\techo \"".addslashes($tabs)."\".'looking at: '.{$export_match}.\" | pos: \".\\simple_cpp\\parsers\\parser_generator::print_pos_in_arr({$var_solutions}).\" \\n\";\n";
				}
				$output[] = $tabs.'foreach ('.$var_solutions.' as $ms) {'."\n".
						$tabs."\t".'$pos = $ms->pos;'."\n".
						$out_consume_spaces.
						$tabs."\t".'if ('.$export_match.' === '.$var_tokens.'[$pos]) {'."\n".
						($debug_echo ? $tabs."\t\techo \"".addslashes($tabs)."\".'matched: '.\$tokens[\$pos].\"\\n\";\n" : '').
						$tabs."\t\t{$var_new_sol}".'[] = new rex_pos($pos + 1, $ms);'."\n".
						$tabs."\t}\n".
						$tabs."}\n"
						;
				if ($debug_echo) {
					$output[] = $tabs."echo \"".addslashes($tabs)."solutions [after foreach]: \".count({$var_new_sol}).\"\\n\";\n";
				}
			}
			else if (!empty($node->list)) {
				foreach ($node->list as $sub_node) {
					static::regex_generate_code($regex_name, $sub_node, $output, $allow_space_or_comment_before, false, $inside_loop || $has_loop, $depth + 1, $tabs);
				}
			}
			else {
				throw new \Exception('Empty node is not allowed.');
			}
			
			if ($has_loop) {
				
				$output[] = $tabs."foreach (\$loop_set as \$l_set) \$new_sol[] = \$l_set;\n";
				$tabs = substr($tabs, 1);
				if ($debug_echo) {
					$output[] = $tabs."echo \"".addslashes($tabs)."solutions [\\\$loop_set]: \".count(\$loop_set).\"\\n\";\n";
				}
				$output[] = $tabs."} while ((\$mset_loop = \$loop_set) && (++\$i <= {$max}));\n";
				
				if ($debug_echo) {
					$output[] = $tabs."# LOOP END\n";
					$output[] = $tabs."echo \"LOOP END\\n\";\n";
				}
			}
		}

		if ($node->tag) {
			$output[] = $inside_loop ? 
							"\tforeach(\$mset_loop as &\$ms) \$ms = new rex_pos(\$ms->pos, \$ms, \"<{$node->tag}\"); unset(\$ms);\n\n" :
							"\tforeach(\$solutions as &\$ms) \$ms = new rex_pos(\$ms->pos, \$ms, \"<{$node->tag}\"); unset(\$ms);\n\n";
		}

		if ((!$wrap_done) && (!($node->option ?? false))) {
			if ($debug_echo) {
				$output[] = $inside_loop ? 
							$tabs."echo \"".addslashes($tabs)."solutions: \".count(\$mset_loop).' | ".addslashes($print_name)."'.\"\\n\";\n" :
							$output[] = $tabs."echo \"".addslashes($tabs)."solutions: \".count(\$solutions).' | ".addslashes($print_name)."'.\"\\n\";\n";
			}
			$output[] = $inside_loop ? 
							$tabs.'if (empty($loop_set)) break; else $mset_loop = $loop_set;'."\n" :
							$tabs.'if (empty($new_sol)) return [false, null]; else $solutions = $new_sol;'."\n";
		}
	}

	protected static function get_node_print_name(object $node)
	{
		$match = $node->{0} ?? null;
		if (isset($match)) {
			return $match.((isset($node->{1}) && ($node->{1} !== 1)) ? $node->{1} : '');
		}
		else {
			$ret = ($node->group ? "(" : "");
			if (!empty($node->list)) {
				$parts = [];
				foreach ($node->list as $n) {
					$parts[] = static::get_node_print_name($n);
				}
				if ($node->either) {
					$ret .= implode("|", $parts);
				}
				else {
					$ret .= implode(" ", $parts);
				}
			}
			$ret .= ($node->group ? ")" : "");
			if (isset($node->req_count) && ($node->req_count !== 1)) {
				$ret .= $node->req_count;
			}
			return $ret;
		}
	}
	
	public static function print_set(array $set)
	{
		$s = [];
		foreach ($set as $i) {
			$s[] = $i->pos;
		}
		echo "SET: ".implode(", ", $s)."\n";
	}
	
	public static function print_pos_in_arr(array $solutions) {
		$pos = [];
		foreach ($solutions as $s) {
			$pos[] = $s->pos;
		}
		return implode(", ", $pos);
	}
	
	public static function link_nodes(array $ordered, int $count, int &$pos = 0, $parent = null)
	{
		while ($pos < $count) {
			$item = $ordered[$pos];
			
			# increment here
			$pos++;
			
			if (isset($parent)) {
				$item->parent = $parent;
				$parent->children[] = $item;
			}
			
			if ($item->mark[0] === '>') { # enter
				static::link_nodes($ordered, $count, $pos, $item);
			}
			else if ($item->mark[0] === '<') { # exit
				return;
			}
			else {
				
			}
		}
	}
}
