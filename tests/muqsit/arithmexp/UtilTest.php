<?php

declare(strict_types=1);

namespace muqsit\arithmexp;

use InvalidArgumentException;
use muqsit\arithmexp\expression\token\NumericLiteralExpressionToken;
use PHPUnit\Framework\TestCase;

final class UtilTest extends TestCase{

	public function testNestedTraversalVisitsChildrenBeforeParentsAndPreservesReferences() : void{
		$tree = [[1, [2, 3]], [4, [5]]];
		$visited = [];
		foreach(Util::traverseNestedArray($tree) as &$entry){
			$visited[] = $entry;
			if($entry === [5]){
				$entry[] = 6;
			}
		}
		unset($entry);

		self::assertSame([[5], [2, 3], [4, [5, 6]], [1, [2, 3]], [[1, [2, 3]], [4, [5, 6]]]], $visited);
		self::assertSame([[1, [2, 3]], [4, [5, 6]]], $tree);
	}

	public function testNestedTraversalRejectsNonListChild() : void{
		$tree = [[0 => 1, 2 => 3]];
		$this->expectException(InvalidArgumentException::class);
		iterator_to_array(Util::traverseNestedArray($tree), false);
	}

	public function testExpressionTokenArrayToTreeUsesLengthRelativeToOffset() : void{
		$tokens = [
			new NumericLiteralExpressionToken(new Position(0, 1), 1),
			new NumericLiteralExpressionToken(new Position(1, 2), 2),
			new NumericLiteralExpressionToken(new Position(2, 3), 3),
			new NumericLiteralExpressionToken(new Position(3, 4), 4)
		];
		$parser = Parser::createUnoptimized();

		self::assertSame([$tokens[1], $tokens[2]], Util::expressionTokenArrayToTree($parser, $tokens, 1, 2));
		self::assertSame([$tokens[2], $tokens[3]], Util::expressionTokenArrayToTree($parser, $tokens, 2));
		self::assertSame([], Util::expressionTokenArrayToTree($parser, $tokens, 2, 0));
	}

	public function testExpressionTokenArrayToTreeKeepsIncompletePostfixFragment() : void{
		$parser = Parser::createUnoptimized();
		$tokens = $parser->parse('sqrt(9)')->getPostfixExpressionTokens();
		self::assertSame([$tokens[1]], Util::expressionTokenArrayToTree($parser, $tokens, 1));
	}

	public function testExpressionTokenArrayToTreePreservesNestedGroups() : void{
		$parser = Parser::createUnoptimized();
		$tokens = $parser->parse('max(1, min(2, 3))')->getPostfixExpressionTokens();
		self::assertSame([[$tokens[0], [$tokens[1], $tokens[2], $tokens[3]], $tokens[4]]], Util::expressionTokenArrayToTree($parser, $tokens));
	}
}
