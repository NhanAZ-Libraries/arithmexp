<?php

declare(strict_types=1);

namespace muqsit\arithmexp;

use muqsit\arithmexp\expression\token\ExpressionToken;
use muqsit\arithmexp\expression\token\NumericLiteralExpressionToken;
use muqsit\arithmexp\expression\token\OpcodeExpressionToken;
use muqsit\arithmexp\pattern\Pattern;
use PHPUnit\Framework\TestCase;

final class InstanceOfPatternMatcherTest extends TestCase{
	public function test_matches_concrete_type_and_interface() : void{
		$token = new NumericLiteralExpressionToken(new Position(0, 1), 7);
		self::assertTrue(Pattern::instanceof(NumericLiteralExpressionToken::class)->matches($token));
		self::assertTrue(Pattern::instanceof(ExpressionToken::class)->matches($token));
		self::assertTrue(Pattern::instanceof(ExpressionToken::class)->matches([$token]));
		self::assertFalse(Pattern::instanceof(OpcodeExpressionToken::class)->matches($token));
	}
}
