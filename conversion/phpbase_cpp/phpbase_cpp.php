<?php

namespace lang_parser\conversion\phpbase_cpp;

final class phpbase_cpp
{
	public static function convert_file(string $lang, string $target_lang, string $source_code_path, string $basename, string $gen_path)
	{
		# pick the parser
		list ($root_node) = \lang_parser\parsers\parser::run($lang, $source_code_path, $basename, $gen_path);
		
		# then convert
		static::test_print_node($root_node);
	}
	
	public static function test_print_node(object $node, string $tabs = '')
	{
		echo $tabs, $node->mark ?: 'node', "\n";
		foreach ($node->children ?? [] as $child) {
			static::test_print_node($child, $tabs."\t");
		}
	}
}
