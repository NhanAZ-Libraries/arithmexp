<?php

declare(strict_types=1);

namespace muqsit\arithmexp;

use muqsit\arithmexp\token\TokenType;
use PHPUnit\Framework\TestCase;

final class TokenTypeTest extends TestCase{

	public function testOpcodeHasDistinctIdentifierAndStableInstance() : void{
		$opcode = TokenType::OPCODE();
		self::assertSame(TokenType::OPCODE, $opcode->identifier);
		self::assertSame('Opcode', $opcode->name);
		self::assertSame($opcode, TokenType::OPCODE());
		self::assertNotSame(TokenType::NUMERIC_LITERAL()->identifier, $opcode->identifier);
	}
}
