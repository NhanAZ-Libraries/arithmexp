<?php

declare(strict_types=1);

namespace muqsit\arithmexp;

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
}
