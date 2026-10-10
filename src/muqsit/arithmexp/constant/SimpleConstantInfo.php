<?php

declare(strict_types=1);

namespace muqsit\arithmexp\constant;

use muqsit\arithmexp\expression\token\BooleanLiteralExpressionToken;
use muqsit\arithmexp\expression\token\NumericLiteralExpressionToken;
use muqsit\arithmexp\Parser;
use muqsit\arithmexp\token\builder\ExpressionTokenBuilderState;
use muqsit\arithmexp\token\IdentifierToken;
use function is_bool;

final class SimpleConstantInfo implements ConstantInfo{

	public function __construct(
		readonly public int|float|bool $value
	){}

	public function writeExpressionTokens(Parser $parser, string $expression, IdentifierToken $token, ExpressionTokenBuilderState $state) : void{
		if(is_bool($this->value)){
			$state->replaceCurrent(new BooleanLiteralExpressionToken($token->getPos(), $this->value));
			return;
		}
		$state->replaceCurrent(new NumericLiteralExpressionToken($token->getPos(), $this->value));
	}
}
