<?php

declare(strict_types=1);

namespace muqsit\arithmexp\expression;

use Generator;

final class ConstantExpression implements Expression{

	public function __construct(
		readonly private string $expression,
		readonly private int|float|bool $value
	){}

	public function getExpression() : string{
		return $this->expression;
	}

	/** @return list<never> */
	public function getPostfixExpressionTokens() : array{
		return [];
	}

	/** @return Generator<string> */
	public function findVariables() : Generator{
		yield from [];
	}

	public function evaluate(array $variable_values = []) : int|float|bool{
		return $this->value;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function __debugInfo() : array{
		return ["expression" => $this->expression, "postfix" => "", "value" => $this->value];
	}
}
