<?php

declare(strict_types=1);

namespace muqsit\arithmexp\pattern\matcher;

use muqsit\arithmexp\expression\token\ExpressionToken;

final class AnyPatternMatcher implements PatternMatcher{
	private static ?self $instance = null;

	public static function instance() : self{
		return self::$instance ??= new self();
	}

	private function __construct(){
	}

	public function matches(ExpressionToken|array $entry) : bool{
		return true;
	}
}
