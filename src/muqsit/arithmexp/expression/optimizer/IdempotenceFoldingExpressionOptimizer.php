<?php

declare(strict_types=1);

namespace muqsit\arithmexp\expression\optimizer;

use muqsit\arithmexp\expression\Expression;
use muqsit\arithmexp\expression\RawExpression;
use muqsit\arithmexp\expression\token\ExpressionToken;
use muqsit\arithmexp\function\FunctionFlags;
use muqsit\arithmexp\Parser;
use muqsit\arithmexp\Util;
use function array_splice;
use function count;

final class IdempotenceFoldingExpressionOptimizer implements ExpressionOptimizer{

	public function __construct(){
	}

	public function run(Parser $parser, Expression $expression) : Expression{
		$postfix_expression_token_tree = ExpressionTokenTree::fromPostfix($parser, $expression->getPostfixExpressionTokens());
		$changes = 0;
		do{
			$found = false;
			foreach($postfix_expression_token_tree->traversePostOrder() as $entry){
				if(!isset($entry->items[$index = count($entry->items) - 1]) || !($entry->items[$index] instanceof ExpressionToken)){
					continue;
				}

				$token = Util::asFunctionCallExpressionToken($parser, $entry->items[$index]);
				if($token === null){
					continue;
				}
				if(($token->flags & FunctionFlags::IDEMPOTENT) === 0){
					continue;
				}
				if($token->argument_count === 0){
					continue;
				}

				$is_commutative = ($token->flags & FunctionFlags::COMMUTATIVE) > 0;
				if(!$is_commutative){
					if($token->argument_count !== 1){
						continue;
					}

					$args = $entry->items[$index - 1];
					if(!($args instanceof ExpressionTokenTree)){
						continue;
					}

					$last = $args->items[count($args->items) - 1];
					if(!($last instanceof ExpressionToken)){
						continue;
					}
					$other_token = Util::asFunctionCallExpressionToken($parser, $last);
					if($other_token === null || !$other_token->equals($token)){
						continue;
					}

					$entry->items = $args->items;
					$found = true;
					++$changes;
				}else{
					for($i = $token->argument_count - 1; $i >= 0; --$i){
						$args = $entry->items[$i];
						if(!($args instanceof ExpressionTokenTree)){
							continue;
						}

						$last = $args->items[count($args->items) - 1];
						if(!($last instanceof ExpressionToken)){
							continue;
						}
						$other_token = Util::asFunctionCallExpressionToken($parser, $last);
						if($other_token === null){
							continue;
						}

						if(
							// TODO: Have ExpressionToken::equals() return inequality report instead, so inequality between specific attributes can be filtered
							// below, the attribute "argument_count" is being filtered
							$other_token->name !== $token->name ||
							$other_token->function !== $token->function ||
							$other_token->flags !== $token->flags
						){
							continue;
						}

						array_splice($entry->items, $i, 1, array_slice($args->items, 0, -1));
						$i += count($args->items) - 1;
						$found = true;
						++$changes;
					}

					$token->argument_count = count($entry->items) - 1;
				}
			}
		}while($found);

		if($changes === 0){
			return $expression;
		}

		return new RawExpression($expression->getExpression(), $postfix_expression_token_tree->flatten());
	}
}
