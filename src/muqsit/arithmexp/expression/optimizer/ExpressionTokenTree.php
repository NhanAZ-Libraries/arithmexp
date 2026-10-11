<?php

declare(strict_types=1);

namespace muqsit\arithmexp\expression\optimizer;

use Generator;
use InvalidArgumentException;
use muqsit\arithmexp\expression\token\ExpressionToken;
use muqsit\arithmexp\Parser;
use muqsit\arithmexp\Util;
use function array_reverse;
use function array_slice;
use function array_splice;
use function count;

/** @internal */
final class ExpressionTokenTree{

	/** @param list<ExpressionToken|self> $items */
	public function __construct(public array $items){}

	/**
	 * @param list<ExpressionToken> $tokens
	 */
	public static function fromPostfix(Parser $parser, array $tokens, bool $requireOperands = true) : self{
		$root = new self([]);
		foreach($tokens as $operand){
			$root->items[] = $operand;
			$call = Util::asFunctionCallExpressionToken($parser, $operand);
			if($call === null){
				continue;
			}
			if($call->argument_count < 0 || ($requireOperands && $call->argument_count >= count($root->items))){
				throw new InvalidArgumentException("Postfix call {$call->name} has {$call->argument_count} arguments but insufficient operands");
			}

			$replace = $call->argument_count + 1;
			$arguments = array_slice($root->items, -$replace, $replace);
			array_splice($root->items, -$replace, $replace, count($arguments) === 1 ? $arguments : [new self($arguments)]);
		}
		return $root;
	}

	/** @return Generator<self> */
	public function traversePostOrder() : Generator{
		$queue = [$this];
		for($index = 0; $index < count($queue); ++$index){
			foreach($queue[$index]->items as $item){
				if($item instanceof self){
					$queue[] = $item;
				}
			}
		}
		for($index = count($queue) - 1; $index >= 0; --$index){
			yield $queue[$index];
		}
	}

	/** @return list<ExpressionToken> */
	public function flatten() : array{
		$result = [];
		$pending = array_reverse($this->items);
		while(count($pending) > 0){
			$item = array_pop($pending);
			if($item instanceof self){
				foreach(array_reverse($item->items) as $child){
					$pending[] = $child;
				}
			}else{
				$result[] = $item;
			}
		}
		return $result;
	}

	/** @return list<ExpressionToken|array<array-key, mixed>> */
	public function toLegacyArray() : array{
		$result = [];
		foreach($this->items as $item){
			$result[] = $item instanceof self ? $item->toLegacyArray() : $item;
		}
		return $result;
	}
}
