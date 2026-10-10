<?php

declare(strict_types=1);

namespace muqsit\arithmexp\expression\optimizer;

use muqsit\arithmexp\expression\Expression;
use muqsit\arithmexp\expression\RawExpression;
use muqsit\arithmexp\expression\token\ExpressionToken;
use muqsit\arithmexp\expression\token\FunctionCallExpressionToken;
use muqsit\arithmexp\expression\token\NumericLiteralExpressionToken;
use muqsit\arithmexp\expression\token\VariableExpressionToken;
use muqsit\arithmexp\function\FunctionFlags;
use muqsit\arithmexp\Parser;
use muqsit\arithmexp\Util;
use function array_key_last;
use function array_splice;
use function count;
use function usort;

final class ReorderExpressionOptimizer implements ExpressionOptimizer{

	public function __construct(){
	}

	public function run(Parser $parser, Expression $expression) : Expression{
		$postfix_expression_tokens = $expression->getPostfixExpressionTokens();
		$tree = ExpressionTokenTree::fromPostfix($parser, $postfix_expression_tokens);
		$changed = false;
		foreach($tree->traversePostOrder() as $entry){
			$index = array_key_last($entry->items);
			if($index === null || !($entry->items[$index] instanceof ExpressionToken)){
				continue;
			}

			$token = Util::asFunctionCallExpressionToken($parser, $entry->items[$index]);
			if($token === null){
				continue;
			}

			if(($token->flags & FunctionFlags::COMMUTATIVE) === 0){
				continue;
			}

			$updated = $entry->items;
			for($i = 0; $i < count($updated); ++$i){
				$child = $updated[$i];
				if(!($child instanceof ExpressionTokenTree) || count($child->items) <= 2){
					continue;
				}
				$last = $child->items[count($child->items) - 1];
				if(!($last instanceof ExpressionToken)){
					continue;
				}
				$other_token = Util::asFunctionCallExpressionToken($parser, $last);
				if($other_token === null || ($other_token->flags & FunctionFlags::COMMUTATIVE) === 0 || $other_token->function !== $token->function){
					continue;
				}
				array_splice($updated, $i, 1, $child->items);
				--$i;
			}
			usort($updated, fn(ExpressionToken|ExpressionTokenTree $a, ExpressionToken|ExpressionTokenTree $b) : int => match(true){
				$a instanceof ExpressionTokenTree => $b instanceof ExpressionTokenTree ? 0 : -1,
				$b instanceof ExpressionTokenTree => 1,
				default => $this->compare(Util::asFunctionCallExpressionToken($parser, $a) ?? $a, Util::asFunctionCallExpressionToken($parser, $b) ?? $b)
			});
			if($updated !== $entry->items){
				$entry->items = $updated;
				$changed = true;
			}
		}

		if(!$changed){
			return $expression;
		}

		return new RawExpression($expression->getExpression(), $tree->flatten());
	}

	/**
	 * @param ExpressionToken $a
	 * @param ExpressionToken $b
	 * @return int<-1, 1>
	 */
	private function compare(ExpressionToken $a, ExpressionToken $b) : int{
		if($a instanceof FunctionCallExpressionToken && $b instanceof FunctionCallExpressionToken){
			return 0;
		}
		if($a instanceof FunctionCallExpressionToken){
			return 1;
		}
		if($b instanceof FunctionCallExpressionToken){
			return -1;
		}

		if($a instanceof VariableExpressionToken && $b instanceof VariableExpressionToken){
			return $a->label <=> $b->label;
		}
		if($a instanceof VariableExpressionToken){
			return -1;
		}
		if($b instanceof VariableExpressionToken){
			return 1;
		}

		if($a instanceof NumericLiteralExpressionToken && $b instanceof NumericLiteralExpressionToken){
			return $a->value <=> $b->value;
		}
		return 0;
	}
}
