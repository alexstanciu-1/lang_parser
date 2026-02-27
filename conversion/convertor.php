<?php

namespace lang_parser\conversion;

require_once __DIR__ . "/../parsers/parser.php";

final class convertor
{
	/**
	 * @var string[]
	 */
	protected array $paths;
	protected string $main_path;
	
	/**
	 * @param string[] $paths
	 */
	protected function __construct(array $paths)
	{
		$this->paths = [];
		foreach ($paths as $path) {
			$this->paths[] = rtrim($path, DS).DS;
		}
		$this->main_path = reset($this->paths);
	}
	
	protected function compile()
	{
		echo "start compile @".json_encode($this->paths, JSON_UNESCAPED_SLASHES)."\n";
		
		# find any php, except index.php
		# convert it to C++
		# update make file if need be
		# complie & link C++
		
		# objective #1 ... 1 class, some pointers
		
		$grouped = [];
		
		# @TODO - also include cpp & h files and put them in there
		
		# 1. find the files
		{
			foreach ($this->paths as $path) {
				$g = [];
				$len = strlen($path);
				$iterator = new \RecursiveIteratorIterator((new \RecursiveDirectoryIterator($path)));
				foreach(new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH) as $file_info) {
					$i = substr($file_info[0], $len);
					$g[$i] = $i;
				}
				$grouped[$path] = $g;
			}
		}
		
		# 2. generate C++ code for each
		{
			foreach ($grouped as $path => $files) {

				$gen_path = $path.".gen_cpp_code/";

				if (!is_dir($gen_path)) {
					qmkdir($gen_path);
				}
				
				$is_main_path = ($path === $this->main_path);

				foreach ($files as $file) {
					if ($is_main_path && ($file === 'index.php')) {
						continue;
					}
					$this->compile_file($path, $file, $gen_path);
				}
			}
			
			# @TODO - remove generated files that remain !!!
		}
		
		echo "\n\n";
		var_dump('main_path: ' . $this->main_path, $grouped);
	}
	
	protected function compile_file(string $path, string $file, string $gen_path)
	{
		# for php
		require_once __DIR__ . "/../parsers/generator.php";
		$parsed = parsers\generator::run($path, $file, $gen_path);
	}
	
	/**
	 * @param string[]|string $paths
	 */
	public static function run(array|string $dir)
	{
		# require_once __DIR__ . "/../parsers/generator.php";
		$parsed = static::run_tests('phpbase', 'cpp', __DIR__."/../parsers/lang_phpbase/test_data/");
		
		# echo "simple_cpp for: {$dir}\n";
		(new static(is_string($dir) ? [$dir] : $dir))->compile();
	}
	
	public static function get_class_dir()
	{
		return dirname((new \ReflectionClass(get_called_class()))->getFileName())."/";
	}
	
	public static function run_tests(string $lang, string $target_lang, string $source_code_path)
	{
		echo "<pre>\n";
		
		$gen_path = $source_code_path.".gen_cpp_code/";

		if (!is_dir($source_code_path)) {
			throw new \Exception('No tests defined. Missing/not dir: ' . $source_code_path);
		}
		$items = scandir($source_code_path);
		foreach ($items as $basename) {
			if (($basename === '.') || ($basename === '..')) {
				continue;
			}
			$fp = $source_code_path.$basename;
			if (!is_file($fp)) {
				continue;
			}
			# echo $fp, "\n";
			$convertor = "lang_parser\\conversion\\{$lang}_{$target_lang}\\{$lang}_{$target_lang}";
			require_once __DIR__ . "/{$lang}_{$target_lang}/{$lang}_{$target_lang}.php";
			
			$convertor::convert_file($lang, $target_lang, $source_code_path, $basename, $gen_path);
		}
	}
}
