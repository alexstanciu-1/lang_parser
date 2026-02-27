<?php

namespace lang_parser\conversion\phpbase_cpp;

final class phpbase_cpp
{
	public static function convert_file(string $lang, string $target_lang, string $source_code_path, string $basename, string $gen_path)
	{
		# pick the parser
		\lang_parser\parsers\parser::run($lang, $source_code_path, $basename, $gen_path);
	}	
}
