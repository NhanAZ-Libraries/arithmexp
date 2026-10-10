<?php

declare(strict_types=1);

namespace muqsit\arithmexp\token\builder;

use Generator;
use muqsit\arithmexp\expression\token\ExpressionToken;
use muqsit\arithmexp\Parser;
use muqsit\arithmexp\token\Token;
use muqsit\arithmexp\Util;
use RuntimeException;
use function array_splice;

final class ExpressionTokenBuilderState{

	public int $current_index;

	/** @var list<Token|ExpressionToken|list<Token|ExpressionToken>> */
	public array $current_group;

	/**
	 * @param Parser $parser
	 * @param string $expression
	 * @param list<Token|ExpressionToken|list<Token|ExpressionToken>> $tokens
	 */
	public function __construct(
		readonly public Parser $parser,
		readonly public string $expression,
		public array $tokens
	){}

	public function replaceCurrent(ExpressionToken $token) : void{
		array_splice($this->current_group, $this->current_index, 1, [$token]);
	}

	/**
	 * @return Generator<ExpressionToken>
	 */
	public function toExpressionTokens() : Generator{
		$tokens = $this->tokens;
		Util::flattenArray($tokens);
		foreach($tokens as $token){
			if(!($token instanceof ExpressionToken)){
				throw new RuntimeException("Expected " . ExpressionToken::class . ", got " . get_debug_type($token));
			}
			yield $token;
		}
	}
}
