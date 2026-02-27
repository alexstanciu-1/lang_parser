<?php

namespace lang_parser\parsers;

trait parser_prepare_defs
{
	protected static final function setup_defs()
	{
		$exp = "/".
				'(?:(?<=\\()\\<\\w+\\>)|'. # (<item>
				'(?:\~?[\#\$]?[a-zA-Z\\_]+(?:\\:\\w+)?)|'.
				'(?:\\b\\d+\\b)|'. # numbers
				# '(?:\?\?(?:(?:\{\})|(?:\[\])|(?:\(\))))|'. # ??{} or ??[] or ??() - it can be wrapped in 
				'(?:\\\\.(?:\\:\\w+)?)|'. # escapes
				'(?:[\=\+\;\\*\\(\\)\\?\\|\\>\\<\\{\\}\\[\\]\\-])|'. # various chars
				'(?:\s+)|' . # space
				# '(?:\~)|' . # `~` require no-space
				'(.+)' . # capture bad ones
			"$/uis";
		
		static::$defs_setup = [];
		
		foreach (static::$defs as $k => $v) {
			if (is_int($v)) {
				continue;
			}
			# each one has a matching tree
			$node = $tree = (object)['list' => [], 'root' => true, 'req_count' => 1];
			
			$v = preg_replace("/\\s+/uis", ' ', $v); # multi space to one space
			
			$m = null;
			$rc = preg_match_all($exp, trim($v), $m, PREG_SET_ORDER);
			if (!$rc) {
				var_dump(error_get_last());
				throw new \Exception('Error. ');
			}
			
			$m_count = count($m);
			for ($m_pos = 0; $m_pos < $m_count; $m_pos++) {
				$match = $m[$m_pos];
				if (isset($match[1]) && (strlen($match[1]) > 0)) {
					var_dump('$match[1]', $match[1], $m);
					throw new \Exception('Not expected.');
				}
				$c = $match[0];
				
				$label_m = null;
				$label_str = null;
				if (preg_match("/\\:\\w+\$/", $c, $label_m)) {
					$c = substr($c, 0, -strlen($label_m[0]));
					$label_str = substr($label_m[0], 1);
				}
				
				$no_space_before = false;
				if ($c[0] === '~') {
					$c = substr($c, 1);
					$no_space_before = true;
				}
				
				if (($c[0] === '<') && (substr($c, -1, 1) === '>')) {
					# this tag is set on the parent node
					$node->tag = substr($c, 1, -1);
				}
				else if ($c === ' ') {
					# deprecated: non-explicit spaces are optional
					# deprecated: $node->list[] = (object)[' ', '*'];
					# spaces have no meaning
					continue;
				}
				else if ($c === '(') {
					if ($no_space_before) {
						throw new \Exception('`~` not allowed before `(`');
					}
					$node->list[] = $new = (object)['list' => [], 'parent' => $node];
					$new->group = true;
					$node = $new;
				}
				else if ($c === ')') {
					if ($no_space_before) {
						throw new \Exception('`~` not allowed before `)`');
					}
					if (!isset($node->parent)) {
						throw new \Exception('meeh');
					}

					if ($node->option ?? false) {
						# go back 2 levels
						$node = $node->parent;
					}

					$next_m = $m[$m_pos + 1][0] ?? null;
					if (($next_m === '?') || ($next_m === '*') || ($next_m === '+')) {
						$node->req_count = $next_m;
						# we took it
						$m_pos++;
					}
					else {
						$node->req_count = 1;
					}
					$node = $node->parent;
				}
				else if ($c === '|') {
					if ($no_space_before) {
						throw new \Exception('`~` not allowed before `|`');
					}
					# switch to either mode
					if (!($node->option ?? false)) {
						# make the switch
						$new = (object)['list' => $node->list, 'parent' => $node, 'option' => true, 'req_count' => 1];
						foreach ($node->list ?? [] as $tmp_n) {
							$tmp_n->parent = $new;
						}
						$node->list = [$new];
						$node->either = true;
					}
					else {
						$node = $node->parent;
					}
					if (!$node->group) {
						throw new \Exception('Options separated by `|` must be grouped between round brackets `()`. Expression: '.$k);
					}
					$next = (object)['list' => [], 'parent' => $node, 'option' => true, 'req_count' => 1];
					$node->list[] = $next;
					$node = $next;
				}
				else {
					
					if ($c[0] === '\\') { # escaped sequence
						if (strlen($c) !== 2) {
							throw new \Exception('Should not be ... escaped sequences should be one token');
						}
					}
					$next_m = $m[$m_pos + 1][0] ?? null;
					if (($next_m === '?') || ($next_m === '*') || ($next_m === '+')) {
						$node->list[] = $new = (object)[$c, $next_m, 'parent' => $node];
						$m_pos++;
					}
					else {
						$node->list[] = $new = (object)[$c, 1, 'parent' => $node];
					}
					if (isset($label_str)) {
						$new->label = $label_str;
					}
					if ($no_space_before) {
						$new->no_space_before = true;
					}
				}
			}
			
			# setup next
			static::$defs_setup[$k] = $tree;
		}
	}
	
	public static function setup_defs_flag(object $node = null)
	{
		if ($node === null) {
			foreach (static::$defs_setup as $tree) {
				static::setup_defs_flag($tree);
			}
		}
		else {
			$real_nodes_count = 0;
			
			$multiplier = ($node->{1} ?? ($node->req_count ?? 1));
			list($min /*, $max*/) = static::get_min_max_from_multiplier($multiplier);
			
			$match = $node->{0} ?? false;
			$node->is_call = (isset($match) && ((($match[0] === '#') || ($match[0] === '$')) && ((static::$defs_setup[$match] ?? null) !== null)));
			
			# something like: (public|private*|protected)+ WILL endlessly recurse
			if ($node->option && ($min < 1)) {
				
				unset($node->parent, $node->list);
				throw new \Exception("An option node must have at least one solution.\n You can not have something like: (public|private*|protected)+. Notice `private*`. \nNode: ".var_export($node, true));
			}
			
			if (empty($node->list)) {
				return [isset($node->{0}) ? 1 : 0];
			}
			
			# handle child nodes from here
			$first = reset($node->list);
			$first->is_first = true;
			$last = end($node->list);
			$last->is_last = true;
			
			foreach ($node->list ?? [] as $itm) {
				list ($n_count) = static::setup_defs_flag($itm);
				$real_nodes_count += $n_count;
			}
			
			$node->sub_count = $real_nodes_count;
			
			return [$real_nodes_count];
		}
	}
	
	protected static function print_prepared_defs(object $node = null, int $depth = 0, string $tabs = "", string $jump_tabs = null)
	{
		if ($node === null) {
			$jump_tabs = str_repeat("\t", 10);
		}
		
		foreach ($node ? $node->list : self::$defs_setup as $k => $v) {
			$match = $v->{0} ?? null;
			$multiplier = $v->{1} ?? ($v->req_count);
			$type = (isset($match) ? "match:{$match}," : '').($v->either ? 'either' : ($v->option ? 'option' : ($v->group ? 'group' : ($v->root ? 'root' : 'n/a'))));
			
			$scalars_inf = (object)[];
			foreach ($v as $kvv => $vvv) {
				if (is_scalar($vvv)) {
					$scalars_inf->{$kvv} = $vvv;
				}
			}

			echo "{$tabs} [{$k}] #".spl_object_id($v)." x{$multiplier} ({$type})"."{$jump_tabs}".json_encode($scalars_inf)."\n";
			
			if (is_object($v)) {
				static::print_prepared_defs($v, $depth + 1, $tabs."\t", substr($jump_tabs, 1));
			}
		}
	}
	
	/**
	 * Here we try to minimize the nodes layout, so we do at little nesting as possible
	 * 
	 * @param object $node
	 * @param object $parent
	 * @param int $pos
	 * @param bool $inside_either
	 * @param bool $inside_loop
	 * @return int
	 * @throws \Exception
	 */
	public static function setup_defs_simplify(object $node = null, object $parent = null, int $pos = null)
	{
		if ($node === null) {
			foreach (static::$defs_setup as $k => $tree) {
				static::setup_defs_simplify($tree);
			}
			return 0;
		}
		else {
			$multiplier = isset($node->{0}) ? ($node->{1} ?? 1) : ($node->req_count ?? 1);
			
			$loop_elements = null;
			$loop_offset = 0;
			# if it's not either or root and does not multiply in any way we can pull it back in the parent
			if (($multiplier === 1) && $parent && (!isset($node->{0})) && (!isset($node->tag)) && (!empty($node->list)) && (!$node->either) && (!$node->option))
			{
				# if it can be done without a LOOP and it does not need a WRAP, 
				#	expand it in the parent, at location, then loop the nodes added
				array_splice($parent->list, $pos, 1, $node->list);
				foreach ($node->list as $nl) {
					$nl->parent = $parent;
					if ($parent->either ?? false) {
						$nl->option = true;
					}
				}
				$loop_elements = $node->list;
				$loop_offset = $pos;
				$node = $parent;
			}
			else if (($multiplier === 1) && $parent && isset($node->{0}) && (!isset($node->tag)) && (count($parent->list) === 1) && (reset($parent->list) === $node)) {
				# we replace the parent with this node
				$node->parent->{0} = $node->{0};
				$node->parent->{1} = $node->parent->req_count; # we keep the multiplier
				unset($node->parent->req_count, $node->parent->list, $node->parent->group);
			}
			else {
				# in this case a LOOP will be required or it's a node that we want to keep as it is
				$loop_elements = $node->list;
			}
			
			if (!empty($loop_elements)) {
				foreach ($loop_elements as $nli_pos => $le) {
					static::setup_defs_simplify($le, $node, $nli_pos + $loop_offset);
				}
			}
		}
	}
}
