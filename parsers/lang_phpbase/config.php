<?php

namespace lang_parser\parsers\lang_phpbase;

class config
{
	public static function config()
	{
		return [
			'T_FILE_START' => '_T_FILE_START_',
			'T_FILE_END' => '_T_FILE_END_',

			'list_delim' => ',',
			'instr_delim' => ';',
			'group_delim' => ['(' => ')', '[' => ']', '{' => '}', ],

			'keep_as_it_is' => ':',

			'tokens_defs' => [
					# space & comment
					'\s'			=> [T_WHITESPACE],
					'\c'			=> [T_COMMENT, T_DOC_COMMENT],

					# reserved words
					'const'			=> [T_CONST],
					'namespace'		=> [T_NAMESPACE],
					'final'			=> [T_FINAL],
					'function'		=> [T_FUNCTION],
					'abstract'		=> [T_ABSTRACT],
					'class'			=> [T_CLASS],
					'trait'			=> [T_TRAIT],
					'interface'		=> [T_INTERFACE],
					'implements'	=> [T_IMPLEMENTS],
					'extends'		=> [T_EXTENDS],
					'return'		=> [T_RETURN],
				
					'for'			=> [T_FOR],
					'foreach'		=> [T_FOREACH],

					'public'		=> [T_PUBLIC],
					'private'		=> [T_PRIVATE],
					'protected'		=> [T_PROTECTED],
					'static'		=> [T_STATIC],

					# access
					'$arr'			=> [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR],
					'$col'			=> [T_PAAMAYIM_NEKUDOTAYIM],

					# misc
					'$d_arr'		=> [T_DOUBLE_ARROW],

					# direct replacements
					'$name'			=> [T_STRING],
					'$name_path'	=> [T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE],
					'$var'			=> [T_VARIABLE],
					'$value'		=> [T_DNUMBER, T_LNUMBER, T_CONSTANT_ENCAPSED_STRING],
					'$op'			=> ['+', '-', '/', '*', '~', '^', '&', '&&', '|', '||', '**', T_NEW, '<', '>'],
					'$op_side'		=> [T_INC, T_DEC],
					'$append'		=> ['[]'],
					'$assign'		=> ['=', '+=', '-=', '/=', '&=', '|='],
					'$php_open'		=> [T_OPEN_TAG],
					'$php_inline'	=> [T_INLINE_HTML],
				
				],

			'group_space_and_comment' => true, # ['$space' => true, '$comment' => true, ],
			'extra_space_and_comment_allowed_everywhere' => true, # except when elements are separated by `,`
			
			# @TODO - not implemented atm 
			'aliases' => [
				# ++ $call ++ $access !
				'$complex_val' => '($var|$value|\($expr\)|$array|$access)',
			],
			
			# reg_ex special chars : . * + ? ^ $ \ | () [] {}
			'defs' => [
				
				# a space has no meaning ... it's just to separate things in a visible way
				# use `\s` to require a mandatory space
				# use `#s` for a space or comment
				# use `~` to require no gaps (space/comment) before the element it is placed in front of

				# note: NON EXPLICIT SPACES ARE OPTIONAL, MANDATORY ONE TO BE SET AS REGEX
				# THE ORDER MATTERS !!!!
				# '$file'			=> '~$file_start~$php_open\s $namespace? (public|protected|private)* ($expr;)* $file_end', # we use `,` to require no gaps (space/comment) between elements
				
				/*
				auto myMap = []{
					std::map<int, string> m;
					m["test"] = 222;
					return m;
				}();
				*/
				
				
				'$file'			=> '~$file_start~$php_open
					
					(( $expr; | $call; | $array; | $value; | $var; | return $expr?; | $class ))*

					$file_end', # we use `,` to require no gaps (space/comment) between elements
				
				#	(public (static public)* (final)? )?
				# '$namespace'	=> 'namespace \s ($name|$name_path) ;',
				
				# @TODO - '($name|$name_path)' ... without `(` and `)` will not work !!!
				# '$identifier'	=> '($name|$name_path)', # move this down
				
				# aliases ... move them (hard-coded)
				# '#sc'			=> '(\s|\c)',
				
				# $assign* $assign_and_op? $op* | but at least one !!!
				
				# auto-detect recurssion, must have substance
				# '$assign'		=> '($var|$value) ($assign_op ($var|$value|$expr))+',
				
				'$arg_def'		=> '($name|$name_path)? $var ($assign ($expr|$name|$value))?',
				
				'$arg'			=> '($var|$value|$name|$array|$access|$call|$expr)',
				
				'$call'			=> '($var|$name) \( ($arg (\, $arg)*)? \)',
				
				
				'$access'		=> '($var $arr $name | $var[($var|$value|$expr)?]) (($arr $name | [($var|$value|$expr)?]))*',
				# '$access'		=> '($expr->$name|$expr[$expr?]) ((->$name|[$expr?]))*',

				# Note: we don't want $expr to equal $var or $value, we can have some aliases to make things shorter
				#			$expr must have something extra: at least round brackets OR $assign|$op 
				'$expr'			=> '((($var|$value|\($expr\)|$call|$access) (($assign|$op) ($var|$value|\($expr\)|$call|$access|$array))+)
										|(\($expr\))
										|(($var|\($expr\)|$call|$access) $op_side)
										|( $op_side ($var|\($expr\)|$call|$access) )
									)',
				# '$array'		=> '[((($var|$value|$call|$expr) $d_arr)? ($var|$value|$expr|$array|$call)) (\, (($var|$value|$call|$expr) $d_arr)? ($var|$value|$expr|$array|$call))*]', # ($value $d_arr)? 
				'$array'		=> '[(((($var|$value|$call|$expr) $d_arr)? ($var|$value|$array|$call|$expr)) (\, (($var|$value|$call|$expr) $d_arr)? ($var|$value|$array|$call|$expr))* (\,)? )?]', # ($value $d_arr)? 
				# '$array'		=> '[((<item>(($var|$value) $d_arr:sillyname)? ($var|$value|$array)) (<item>\, (($var|$value) $d_arr:sillyname)? ($var|$value|$array))* (\,)? )?]', # ($value $d_arr)? 
				# '$array'		=> '[(($value $d_arr)? ($value|$array) (\, ($value $d_arr)? ($value|$array))* (\,)? )?]', # ($value $d_arr)? 
				# we took out $call for now, and ??()
				# '$expr'			=> '??() ($var|$call|$value|$expr|$identifier) (($op|$assign) $var|$call|$value|$expr|$identifier)* ',

				/*
				for ($i = 0; $i < 100; $i++) {
					$x[] = $i;
				}
				 */
				
					# protected string $var = '';
				'$class'		=> 
									'final? abstract? (class|trait|interface) $name (extends $identifier)? (implements $identifier)?
									{
										($property|$class_const|$class_func)*
									}
									',
				'$property'		=> '(public|protected|private)+ static? (($name|$name_path)(\|($name|$name_path))*)? $var  ($assign ($expr|$name|$value))? ;', # 
				'$class_const'	=> '(public|protected|private)+ const   (($name|$name_path)(\|($name|$name_path))*)? $name ($assign ($expr|$name|$value))? ;', # 
				
				'$class_func'	=> '
									(public|protected|private)? static? final? abstract? function $name \( ( $arg_def (\, $arg_def )*)? \) {
										
										(( $expr; | $call; | $array; | $value; | $var; | $for | return ($var|$value|$array|$expr)?; ))*
									}
					',
				
				'$for'			=> 'for \( ($expr|$var|$call)? ; ($expr|$var|$call)? ; ($expr|$var|$call)? \) {
										(( $expr; | $call; | $array; | $value; | $var; | $for | return ($var|$value|$array|$expr)?; ))*
									}'
				
				/*
				
				'$class'		=> 
									'final\s? abstract\s? (class|trait|interface) \s $name (\s extends $identifier)? (\s implements \s $identifier)?
									{
										($const|$property|$method)*
									}
								',
				'$property'		=> '(public|protected|private)? static? ($type|$types)? $var = $expr;',
				'$method'		=> '(public|protected|private)? static? $function',
				'$function'		=> '	function\s+$name\( $arg* \) (\:$type|$types)?
										(;|\{ $instruction* \})
								',
				'$foreach'		=> 'foreach \($expr as ($var =>)? $var \) (;|{ $instruction* })',
				
				'$call'			=> '$identifier\( $arg* \)',
				'$types'		=> '$identifier(\|$identifier)*',
				'$type'			=> '$identifier',
				'$arg'			=> '($identifier(\|$identifier)*)? $var (= $expr)?',
				'$decl'			=> 'const $identifier $assign $expr ;',
				'$tern_op'		=> '$expr \? $expr \: $expr',
				'$tern_op_null' => '$expr \?\? $expr',

				'$array'		=> '( array|\[ )( ( $expr \=\> )? ($expr | $array) ( \, ( $expr \=\> )? ($expr | $array))* \,? )',

				'$code_blocks'	=> '??{} $instruction* ',
				
				# @TODO expressions can be between () - we need to put that info somewhere
				# @TODO we need to handle `{}` ... used outside explicit situations
				'$instruction'	=> 'return? $expr ;|break|break 2',
				
				*/
			],
		];
	}
}
