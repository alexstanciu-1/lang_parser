<?php

namespace lang_parser\parsers\lang_phpbase;

final class tokenizer
{
	public static function get_tokens(string $content)
	{
		return token_get_all($content);
	}
}