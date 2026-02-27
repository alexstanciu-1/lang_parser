<?php

namespace lang_parser\parsers;

require_once __DIR__."/parser_prepare_defs.php";
require_once __DIR__."/parser_prepare_toks.php";
require_once __DIR__."/parser_utility.php";
require_once __DIR__."/parser_generator.php";
require_once __DIR__."/rex_pos.php";

final class parser
{
	use parser_prepare_defs, parser_prepare_toks, parser_generator, parser_utility;
	
	const max_multiplier = 2 ** 24; # 2 pow of 24
	
	const lang_default_extensions = ['php' => 'php', 'phpbase' => 'php'];
	
	protected static bool $was_init = false;
	protected static ?array $tokens_map;
	protected static ?array $tokens_delim;
	
	protected static array $defs_setup = [];
	
	protected static array $cfg = [];
	protected static array $tokens_defs = [];
	protected static array $defs = [];
	protected static bool $group_space_and_comment = false;
	protected static bool $extra_space_and_comment_allowed_everywhere = true;

	public static final function run(string $lang, string $path, string $file, string $gen_path)
	{
		# for test only !!!
		# ini_set('max_execution_time', 2);
		ini_set('memory_limit', '1024M');
		set_time_limit(10);

		echo "<pre>";
		
		static::static_init($lang);
		
		$content = file_get_contents($path . $file);
		list ($tokenizer_class) = static::load_resource($lang, 'tokenizer');
		$tokens = $tokenizer_class::get_tokens($content);
		
		# echo 'TOKENS COUNT: ', count($tokens), "\n";
		
		# static::print_tokens($tokens);
		
		# echo "\n\n\n---------------------------------------------------------------------------------------------------------------------\n\n\n";

		# we could also split WHITE-SPACE BY new-line and have better grouping of whitespace
		
		array_unshift($tokens, static::$cfg['T_FILE_START']);
		$tokens[] = static::$cfg['T_FILE_END'];
		
		list ($parser_regex_class, $parser_regex_path) = static::generate_parser($lang);
		
		# echo "\n--------------------------------------------------------------------------------------------------------------------------\n";
		# var_dump("DONE generate_parser");
		# echo "\n--------------------------------------------------------------------------------------------------------------------------\n";

		$pos = 0;
		list ($rx_tokens, $rx_tokens_source) = static::prepare_tokens($tokens, $pos);
		
		# echo "\n----------------------------------------------------------------------------------------\n";
		
		require_once $parser_regex_path;
		
		$set_0 = new \lang_parser\parsers\lang_phpbase\rex_pos(0, null);
		
		$t1 = microtime(true);
		if (false) { # one solution mode
			$last_m = $parser_regex_class::rex_file($set_0, $rx_tokens);
			$is_match = $last_m ? true : false;
			$last_set_list = [$last_m];
		}
		else {
			list($is_match, $last_set_list) = $parser_regex_class::rex_file([$set_0], $rx_tokens);
		}
		$t2 = microtime(true);
		
		
		# echo "\n----------------------------------------------------------------------------------------\n";
		
		echo "PARSED in: ".round(($t2 - $t1)*1000, 3)." ms\n";
		echo "TOKENS COUNT: ".count($rx_tokens)."\n";
		echo "PEAK MEM: ".round(memory_get_peak_usage()/1024/1024, 2)." MB\n", 
				"IS MATCH: " . ($is_match ? "YES" : "NO") , "\n";
		
		var_dump($parser_regex_class::$stats);
		
		if ($is_match) {

			echo "COUNT(\$last_set): ".count($last_set_list);
		
			foreach ($last_set_list as $pos => $last_set)
			{
				# 1. rewind
				$ordered = [];
				do
				{
					$tok = $rx_tokens[$last_set->pos - 1];
					$tok = (is_array($tok[1]) ? $tok[1][1] : $tok[1])." _ ".$tok[0];

					# echo $last_set->pos." | {$last_set->mark} | {$tok}\n";
					$ordered[] = $last_set;
				}
				while (($last_set = $last_set->prev));

				$ordered = array_reverse($ordered);

				echo "\n\n\nSet #".($pos+1), " | matches count: ".count($ordered), "\n";
				echo "\n----------------------------------------------------------------------------------------\n";
				static::print_results_html($ordered, $rx_tokens, $rx_tokens_source, count($ordered));
			}
		}
		else {
			var_dump("NOT MATCHED!");
		}
		
		echo "</pre>";
		
		$struct = $ordered;
		var_dump($ordered[1]->children[0]->parent === $ordered[1]);
		die;
	}

	protected static final function static_init(string $lang)
	{
		if (static::$was_init) {
			return;
		}
		
		list ($class_name) = static::load_resource($lang, 'config');
		static::$cfg = $class_name::config();
		# these two are fake so we can have the start/end of file
		static::$cfg['tokens_defs']['$file_start'] = ['_T_FILE_START_'];
		static::$cfg['tokens_defs']['$file_end'] = ['_T_FILE_END_'];
		static::$cfg['tokens_defs']['#space'] = [PHP_INT_MAX]; # this is fake
		
		static::$defs = static::$cfg['defs'];
		static::$tokens_defs = static::$cfg['tokens_defs'];
		static::$group_space_and_comment = static::$cfg['group_space_and_comment'] ?? false;
		static::$extra_space_and_comment_allowed_everywhere = static::$cfg['extra_space_and_comment_allowed_everywhere'] ?? true; # yes, true by default
		
		static::setup_defs();
		
		# static::print_prepared_defs();
		
		# echo "\n------------------------------------------------------------------------------------------------------------------\n";
		
		static::setup_defs_simplify();
		
		# echo "\n------------------------------------------------------------------------------------------------------------------\n";
		# static::print_prepared_defs();
		
		static::setup_defs_flag();
		
		# static::print_prepared_defs();
		
		# echo "\n=================================================================================================================\n";
		
		foreach (static::$tokens_defs as $k => $list) {
			foreach ($list as $v) {
				static::$tokens_map[$v] = $k;
			}
		}
		
		$keep_as_it_is = static::$cfg['keep_as_it_is'];
		for ($i = 0; $i < strlen($keep_as_it_is); $i++) {
			$chr = $keep_as_it_is[$i];
			if ($chr !== ' ') {
				static::$tokens_map[$chr] = $chr;
			}
		}
		
		static::$tokens_delim[static::$cfg['list_delim']] = true;
		static::$tokens_delim[static::$cfg['instr_delim']] = true;
		foreach (static::$cfg['group_delim'] as $dk => $dv) {
			static::$tokens_delim[$dk] = true;
			static::$tokens_delim[$dv] = true;
		}
		
		static::$was_init = true;
	}
	
	protected static function load_resource(string $lang, string $name)
	{
		$ext = static::lang_default_extensions[$lang] ?? null;
		if (!$ext) {
			throw new \Exception("Extension not defined for lang: {$lang}");
		}
		$expected_path = __DIR__."/lang_{$lang}/{$name}.{$ext}";
		if (!is_file($expected_path)) {
			throw new \Exception("The `{$name}` file for `{$lang}` was not found. Expecting file `{$expected_path}`");
		}
		require_once $expected_path;
		$class_name = "lang_parser\\parsers\\lang_{$lang}\\{$name}";
		if (!class_exists($class_name)) {
			throw new \Exception("No `{$name}` was found for: {$lang}. Expecting class `{$class_name}` in file `{$expected_path}`");
		}
		return [$class_name, $expected_path];
	}
}
