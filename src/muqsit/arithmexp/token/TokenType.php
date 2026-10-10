<?php

declare(strict_types=1);

namespace muqsit\arithmexp\token;

final class TokenType{

	public const BINARY_OPERATOR = 0;
	public const BOOLEAN_LITERAL = 1;
	public const FUNCTION_CALL = 2;
	public const FUNCTION_CALL_ARGUMENT_SEPARATOR = 3;
	public const IDENTIFIER = 4;
	public const NUMERIC_LITERAL = 5;
	public const OPCODE = 6;
	public const PARENTHESIS = 7;
	public const UNARY_OPERATOR = 8;

	/** @var array<int, self> */
	private static array $instances = [];

	private static function instanceFor(int $identifier, string $name) : self{
		return self::$instances[$identifier] ??= new self($identifier, $name);
	}

	public static function BINARY_OPERATOR() : self{
		return self::instanceFor(self::BINARY_OPERATOR, "Binary Operator");
	}

	public static function BOOLEAN_LITERAL() : self{
		return self::instanceFor(self::BOOLEAN_LITERAL, "Boolean Literal");
	}

	public static function FUNCTION_CALL() : self{
		return self::instanceFor(self::FUNCTION_CALL, "Function Call");
	}

	public static function FUNCTION_CALL_ARGUMENT_SEPARATOR() : self{
		return self::instanceFor(self::FUNCTION_CALL_ARGUMENT_SEPARATOR, "Function Call Argument Separator");
	}

	public static function IDENTIFIER() : self{
		return self::instanceFor(self::IDENTIFIER, "Identifier");
	}

	public static function NUMERIC_LITERAL() : self{
		return self::instanceFor(self::NUMERIC_LITERAL, "Numeric Literal");
	}

	public static function OPCODE() : self{
		return self::instanceFor(self::OPCODE, "Opcode");
	}

	public static function PARENTHESIS() : self{
		return self::instanceFor(self::PARENTHESIS, "Parenthesis");
	}

	public static function UNARY_OPERATOR() : self{
		return self::instanceFor(self::UNARY_OPERATOR, "Unary Operator");
	}

	private function __construct(
		readonly public int $identifier,
		readonly public string $name
	){}

	/**
	 * @param TokenType::* $identifier
	 * @return bool
	 */
	public function is(int $identifier) : bool{
		return $this->identifier === $identifier;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function __debugInfo() : array{
		return [
			"identifier" => $this->identifier,
			"name" => $this->name
		];
	}
}
