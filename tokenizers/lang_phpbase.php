<?php

namespace lang_parser\tokenizers;

final class lang_phpbase implements tok_interface
{
	public static function tokenize(string $input)
	{
		return [token_get_all($input)];
	}
}
