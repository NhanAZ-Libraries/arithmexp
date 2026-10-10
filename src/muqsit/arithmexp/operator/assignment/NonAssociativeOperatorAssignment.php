<?php

declare(strict_types=1);

namespace muqsit\arithmexp\operator\assignment;

use Generator;
use muqsit\arithmexp\operator\OperatorList;
use muqsit\arithmexp\ParseException;
use muqsit\arithmexp\token\BinaryOperatorToken;
use function count;
use function is_array;

final class NonAssociativeOperatorAssignment implements OperatorAssignment{
	private static ?self $instance = null;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function getType() : int{
		return self::TYPE_NON_ASSOCIATIVE;
	}

	private function getPairOperator(OperatorList $list, mixed $entry) : ?BinaryOperatorToken{
		if(!is_array($entry) || count($entry) !== 3 || !($entry[1] instanceof BinaryOperatorToken)){
			return null;
		}
		if(!isset($list->binary[$entry[1]->operator])){
			return null;
		}
		return $entry[1];
	}

	public function traverse(OperatorList $list, string $expression, array &$tokens) : Generator{
		$state = new OperatorAssignmentTraverserState($tokens);
		$index = -1;
		$count = count($tokens);
		while(++$index < $count){
			$value = $tokens[$index];
			if($value instanceof BinaryOperatorToken && isset($list->binary[$value->operator])){
				$precedence = $list->binary[$value->operator]->getPrecedence();
				if($index > 0){
					$lvalue = $tokens[$index - 1];
					$lvalue_operator = $this->getPairOperator($list, $lvalue);
					if($lvalue_operator !== null && $list->binary[$lvalue_operator->operator]->getPrecedence() === $precedence){
						throw ParseException::undefinedOperatorAssociativity($expression, $value->getPos());
					}
				}
				if($index + 1 < $count){
					$rvalue = $tokens[$index + 1];
					$rvalue_operator = $this->getPairOperator($list, $rvalue);
					if($rvalue_operator !== null && $list->binary[$rvalue_operator->operator]->getPrecedence() === $precedence){
						throw ParseException::undefinedOperatorAssociativity($expression, $rvalue_operator->getPos());
					}
				}
				$state->index = $index;
				$state->value = $value;
				yield $state;
				if($state->changed){
					$index = -1;
					$count = count($tokens);
					$state->changed = false;
				}
			}
		}
	}
}
