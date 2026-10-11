<?php

declare(strict_types=1);

namespace muqsit\arithmexp\expression\optimizer;

use InvalidArgumentException;
use muqsit\arithmexp\expression\ConstantExpression;
use muqsit\arithmexp\expression\Expression;
use muqsit\arithmexp\expression\RawExpression;
use muqsit\arithmexp\expression\token\ExpressionToken;
use muqsit\arithmexp\expression\token\NumericLiteralExpressionToken;
use muqsit\arithmexp\expression\token\OpcodeExpressionToken;
use muqsit\arithmexp\expression\token\VariableExpressionToken;
use muqsit\arithmexp\ParseException;
use muqsit\arithmexp\Parser;
use muqsit\arithmexp\Position;
use muqsit\arithmexp\token\BinaryOperatorToken;
use muqsit\arithmexp\token\OpcodeToken;
use muqsit\arithmexp\token\UnaryOperatorToken;
use muqsit\arithmexp\Util;
use function array_filter;
use function count;
use function in_array;
use function is_int;

final class OperatorStrengthReductionExpressionOptimizer implements ExpressionOptimizer{

	public function __construct(){}

	/**
	 * @param list<OpcodeToken::OP_*> $codes
	 */
	private function matchesOpcodeTree(ExpressionTokenTree $entry, int $size, array $codes) : bool{
		return count($entry->items) === $size
			&& $entry->items[$size - 1] instanceof OpcodeExpressionToken
			&& in_array($entry->items[$size - 1]->code, $codes, true);
	}

	/**
	 * @param ExpressionToken|ExpressionTokenTree $entry
	 * @return list<ExpressionToken>
	 */
	private function flattened(ExpressionToken|ExpressionTokenTree $entry) : array{
		return $entry instanceof ExpressionTokenTree ? $entry->flatten() : [$entry];
	}

	public function run(Parser $parser, Expression $expression) : Expression{
		$tree = ExpressionTokenTree::fromPostfix($parser, $expression->getPostfixExpressionTokens());
		$filter = static fn(ExpressionToken $token) : bool => !$token->isDeterministic();

		$changes = 0;
		foreach($tree->traversePostOrder() as $entry){
			if(!$this->matchesOpcodeTree($entry, 3, [OpcodeToken::OP_BINARY_ADD, OpcodeToken::OP_BINARY_DIV, OpcodeToken::OP_BINARY_MOD, OpcodeToken::OP_BINARY_SUB])){
				continue;
			}
			$operator = $entry->items[2];
			if(!($operator instanceof OpcodeExpressionToken)){
				continue;
			}
			$left = $this->flattened($entry->items[0]);
			$right = $this->flattened($entry->items[1]);
			if(count(array_filter([...$left, ...$right], $filter)) === 0){
				continue;
			}

			$replacement = $this->processBinaryExpression($parser, $expression, $operator, $left, $right);
			if($replacement !== null){
				$entry->items = $replacement;
				++$changes;
			}
		}

		foreach($tree->traversePostOrder() as $entry){
			if(!$this->matchesOpcodeTree($entry, 2, [OpcodeToken::OP_UNARY_NOT, OpcodeToken::OP_UNARY_NVE, OpcodeToken::OP_UNARY_PVE])){
				continue;
			}
			$operator = $entry->items[1];
			if(!($operator instanceof OpcodeExpressionToken)){
				continue;
			}
			$operand = $this->flattened($entry->items[0]);
			if(count(array_filter($operand, $filter)) === 0){
				continue;
			}

			$replacement = $this->processUnaryExpression($parser, $operator, $operand);
			if($replacement !== null){
				$entry->items = $replacement;
				++$changes;
			}
		}

		$postfix_expression_tokens = $tree->flatten();
		return match(true){
			count($postfix_expression_tokens) === 1 && $postfix_expression_tokens[0] instanceof NumericLiteralExpressionToken => new ConstantExpression($expression->getExpression(), $postfix_expression_tokens[0]->value),
			default => $changes > 0 ? new RawExpression($expression->getExpression(), $postfix_expression_tokens) : $expression
		};
	}

	/**
	 * @param list<ExpressionToken> $tokens
	 * @param int $value
	 * @return bool
	 */
	private function valueEquals(array $tokens, int $value) : bool{
		if(count($tokens) !== 1 || !($tokens[0] instanceof NumericLiteralExpressionToken)){
			return false;
		}
		$actual = $tokens[0]->value;
		return is_int($actual) ? $value === $actual : (float) $value === $actual;
	}

	/**
	 * @param list<ExpressionToken> $x
	 * @param list<ExpressionToken> $y
	 * @return bool
	 */
	private function tokensEqualByReturnValue(array $x, array $y) : bool{
		if(count($x) !== count($y)){
			return false;
		}

		for($i = 0, $max = count($x); $i < $max; ++$i){
			if((
				!($x[$i] instanceof VariableExpressionToken /* variables are deterministic during evaluation */) &&
				!$x[$i]->isDeterministic()
			) || !$x[$i]->equals($y[$i])){
				return false;
			}
		}

		return true;
	}

	/**
	 * @param Parser $parser
	 * @param Position $position
	 * @param OpcodeToken::OP_* $code
	 * @return OpcodeExpressionToken
	 */
	private function buildOpcodeToken(Parser $parser, Position $position, int $code) : OpcodeExpressionToken{
		$manager = $parser->operator_manager;
		$symbol = OpcodeToken::opcodeToString($code);
		return match($code){
			OpcodeToken::OP_BINARY_ADD, OpcodeToken::OP_BINARY_DIV, OpcodeToken::OP_BINARY_EXP, OpcodeToken::OP_BINARY_MOD, OpcodeToken::OP_BINARY_MUL, OpcodeToken::OP_BINARY_SUB => new OpcodeExpressionToken($position, $code, new BinaryOperatorToken($position, $manager->binary_registry->get($symbol)->getSymbol())),
			OpcodeToken::OP_UNARY_NOT, OpcodeToken::OP_UNARY_NVE, OpcodeToken::OP_UNARY_PVE => new OpcodeExpressionToken($position, $code, new UnaryOperatorToken($position, $manager->unary_registry->get($symbol)->getSymbol())),
			default => throw new InvalidArgumentException("Unsupported operator code {$code}")
		};
	}

	/**
	 * @param Parser $parser
	 * @param OpcodeExpressionToken $operator_token
	 * @param list<ExpressionToken> $operand
	 * @return list<ExpressionToken>|null
	 */
	private function processUnaryExpression(Parser $parser, OpcodeExpressionToken $operator_token, array $operand) : ?array{
		return match($operator_token->code){
			OpcodeToken::OP_UNARY_NVE => [
				new NumericLiteralExpressionToken($operator_token->getPos(), -1),
				...$operand,
				$this->buildOpcodeToken($parser, Util::positionContainingExpressionTokens([...$operand, $operator_token]), OpcodeToken::OP_BINARY_MUL)
			],
			default => null
		};
	}

	/**
	 * @param Parser $parser
	 * @param Expression $expression
	 * @param OpcodeExpressionToken $operator_token
	 * @param list<ExpressionToken> $left
	 * @param list<ExpressionToken> $right
	 * @return list<ExpressionToken>|null
	 * @throws ParseException
	 */
	private function processBinaryExpression(Parser $parser, Expression $expression, OpcodeExpressionToken $operator_token, array $left, array $right) : ?array{
		return match($operator_token->code){
			OpcodeToken::OP_BINARY_DIV => match(true){
				$this->valueEquals($right, 0) => throw ParseException::unresolvableExpressionDivisionByZero($expression->getExpression(), Util::positionContainingExpressionTokens($right)),
				default => null
			},
			OpcodeToken::OP_BINARY_MOD => match(true){
				$this->valueEquals($right, 0) => throw ParseException::unresolvableExpressionModuloByZero($expression->getExpression(), Util::positionContainingExpressionTokens($right)),
				default => $this->processModulo($parser, $expression, $operator_token, $left, $right)
			},
			OpcodeToken::OP_BINARY_SUB => match(true){
				$this->valueEquals($left, 0) => [
					new NumericLiteralEXpressionToken(Util::positionContainingExpressionTokens($right), -1),
					...$right,
					$this->buildOpcodeToken($parser, Util::positionContainingExpressionTokens($right), OpcodeToken::OP_BINARY_MUL)
				],
				default => null
			},
			default => null
		};
	}

	/**
	 * @param Parser $parser
	 * @param Expression $expression
	 * @param OpcodeExpressionToken $operator_token
	 * @param list<ExpressionToken> $left
	 * @param list<ExpressionToken> $right
	 * @return list<ExpressionToken>|null
	 */
	private function processModulo(Parser $parser, Expression $expression, OpcodeExpressionToken $operator_token, array $left, array $right) : ?array{
		// reduce (x % y) % y to (x % y)
		$left_tree = ExpressionTokenTree::fromPostfix($parser, $left)->items[0] ?? null;
		if($left_tree instanceof ExpressionTokenTree && $this->matchesOpcodeTree($left_tree, 3, [OpcodeToken::OP_BINARY_MOD])){
			$left_rvalue = $this->flattened($left_tree->items[1]);
			if($this->tokensEqualByReturnValue($left_rvalue, $right)){
				return $left;
			}
		}

		return null;
	}
}
