<?php

declare(strict_types=1);

namespace muqsit\arithmexp\pattern\matcher;

use muqsit\arithmexp\expression\token\ExpressionToken;

final class InstanceOfPatternMatcher implements PatternMatcher{

	/**
	 * @param class-string<ExpressionToken> $class
	 */
	public function __construct(
		readonly private string $class
	){}

	public function matches(ExpressionToken|array $entry) : bool{
		if($entry instanceof ExpressionToken){
			return $this->matchesType($entry);
		}
		foreach($entry as $token){
			if(!$this->matchesType($token)){
				return false;
			}
		}
		return true;
	}

	private function matchesType(ExpressionToken $token) : bool{
		return get_class($token) === $this->class
			|| in_array($this->class, class_parents($token) ?: [], true)
			|| in_array($this->class, class_implements($token) ?: [], true);
	}
}
