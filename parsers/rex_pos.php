<?php

namespace lang_parser\parsers;

final class rex_pos
{
	public function __construct(
			public ?int $pos,
			public ?rex_pos $prev,
			public string $mark = ""
	) {}
}
