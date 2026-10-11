<?php

declare(strict_types=1);

namespace muqsit\arithmexp;

use Closure;
use DivisionByZeroError;
use InvalidArgumentException;
use muqsit\arithmexp\expression\ConstantExpression;
use muqsit\arithmexp\expression\RawExpression;
use muqsit\arithmexp\expression\optimizer\ExpressionTokenTree;
use muqsit\arithmexp\expression\token\ExpressionToken;
use muqsit\arithmexp\expression\token\FunctionCallExpressionToken;
use muqsit\arithmexp\expression\token\NumericLiteralExpressionToken;
use muqsit\arithmexp\function\SimpleFunctionInfo;
use muqsit\arithmexp\operator\assignment\RightOperatorAssignment;
use muqsit\arithmexp\operator\binary\SimpleBinaryOperator;
use muqsit\arithmexp\operator\OperatorPrecedence;
use PHPUnit\Framework\TestCase;
use Throwable;
use function array_map;

final class OptimizerTest extends TestCase{

	private Parser $parser;
	private Parser $unoptimized_parser;

	protected function setUp() : void{
		$this->parser = Parser::createDefault();
		$this->unoptimized_parser = Parser::createUnoptimized();
	}

	public function testConstantFoldingOptimization() : void{
		$expression = $this->parser->parse("37.28 * cos(37) + sin(85) * 22 / cos(73) + sin(91) + 84.47 * cos(68) + sin(29)");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertEquals(37.28 * cos(37) + sin(85) * 22 / cos(73) + sin(91) + 84.47 * cos(68) + sin(29), $expression->evaluate());
	}

	public function testDefaultOptimizationPreservesFloatingPointAdditionGrouping() : void{
		$source = "x + y + z";
		$variables = ["x" => 1e16, "y" => -1e16, "z" => 1.0];
		$expected = $this->unoptimized_parser->parse($source)->evaluate($variables);
		self::assertSame(1.0, $expected);
		self::assertSame($expected, $this->parser->parse($source)->evaluate($variables));
	}

	public function testDefaultOptimizationPreservesFloatingPointMultiplicationGrouping() : void{
		$source = "x * y * z";
		$variables = ["x" => 1e308, "y" => 1e308, "z" => 1e-308];
		$expected = $this->unoptimized_parser->parse($source)->evaluate($variables);
		self::assertSame(INF, $expected);
		self::assertSame($expected, $this->parser->parse($source)->evaluate($variables));
	}

	public function testConstantFoldingOptimizationForNumericLiteral() : void{
		$expression = $this->parser->parse("1.23");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertEquals(1.23, $expression->evaluate());
	}

	public function testConstantFoldingOptimizationForNestedFunctionCall() : void{
		$expression = $this->parser->parse("min(pi())");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertEquals(min([pi()]), $expression->evaluate());
	}

	public function testConstantFoldingOptimizationForNestedFunctionCalls() : void{
		$expression = $this->parser->parse("min(pi() + pi() + pi() + pi())");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertEquals(min([pi() + pi() + pi() + pi()]), $expression->evaluate());
	}

	public function testConstantPropagationOptimization() : void{
		$source = "37.28 * cos(x) + sin(85) * 22 / cos(73) + sin(91) + 84.47 * cos(z) + sin(32)";
		$actual = $this->parser->parse($source);
		$plain = $this->unoptimized_parser->parse($source);
		self::assertLessThan(count($plain->getPostfixExpressionTokens()), count($actual->getPostfixExpressionTokens()));
		foreach([["x" => 0.0, "z" => 0.0], ["x" => 1.0, "z" => 2.0]] as $variables){
			self::assertSame($plain->evaluate($variables), $actual->evaluate($variables));
		}
	}

	public function testDivisionConstantFoldingForRightOperandNan() : void{
		$expression = $this->parser->parse("min(1, 2) / nan");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertNan($expression->evaluate());
	}

	public function testSubtractionConstantFoldingBetweenInf() : void{
		$expression = $this->parser->parse("inf - inf");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertNan($expression->evaluate());
	}

	public function testSubtractionConstantFoldingBetweenNan() : void{
		$expression = $this->parser->parse("nan - nan");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertNan($expression->evaluate());
	}

	public function testDivisionConstantFoldingForFunctionCallOperandsReturningNan() : void{
		$expression = $this->parser->parse("sqrt(-1) / sqrt(-1)");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertNan($expression->evaluate());
	}

	public function testNoOptimization() : void{
		$source = "37.28 * cos(mt_rand(x, y) / 23.84) ** 3";
		$actual = $this->parser->parse($source);
		$expected = $this->unoptimized_parser->parse($source);
		TestUtil::assertExpressionsEqual($expected, $actual);
	}

	public function testNonDeterminsticFunctionCallExclusionFromOptimization() : void{
		$actual = $this->parser->parse("mt_rand(1, 2) - mt_rand(1, 2)");
		$expected = $this->unoptimized_parser->parse("mt_rand(1, 2) - mt_rand(1, 2)");
		TestUtil::assertExpressionsEqual($expected, $actual);
	}

	public function testNonDeterminsticOperatorExclusionFromOptimization() : void{
		$operator = new SimpleBinaryOperator(":", "Random", OperatorPrecedence::EXPONENTIAL, RightOperatorAssignment::instance(), SimpleFunctionInfo::from(Closure::fromCallable("mt_rand"), 0));

		$this->parser->operator_manager->binary_registry->register($operator);
		$actual = $this->parser->parse("1:4 / 1:4");

		$this->unoptimized_parser->operator_manager->binary_registry->register($operator);
		$expected = $this->unoptimized_parser->parse("1:4 / 1:4");
		TestUtil::assertExpressionsEqual($expected, $actual);
	}

	public function testExponentOperatorStrengthReductionForOperandTwo() : void{
		$expression = $this->parser->parse("2 ** 2");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertEquals(4, $expression->evaluate());
	}

	public function testExponentOptimizationPreservesOperandEvaluation() : void{
		foreach(["0 ** (1 / x)", "1 ** (1 / x)", "(1 / x) ** 0"] as $source){
			$expression = $this->parser->parse($source);
			$error = null;
			try{
				$expression->evaluate(["x" => 0]);
			}catch(Throwable $exception){
				$error = $exception::class;
			}
			self::assertSame(DivisionByZeroError::class, $error, $source);
		}
	}

	public function testExponentOptimizationPreservesNumericResultsAndTypes() : void{
		foreach([
			["0 ** x", ["x" => -1], INF],
			["0 ** x", ["x" => 0], 1],
			["0 ** x", ["x" => 2], 0],
			["1 ** x", ["x" => 3], 1],
			["x ** 0", ["x" => 3], 1],
			["x ** 1", ["x" => true], 1],
			["x ** 2", ["x" => 3], 9],
			["(x + y + z) ** 2", ["x" => 1, "y" => 2, "z" => 3], 36]
		] as [$source, $variables, $expected]){
			self::assertSame($expected, $this->parser->parse($source)->evaluate($variables), $source);
		}
	}

	public function testMultiplicationOperatorStrengthReductionForLeftOperandOne() : void{
		$actual = $this->parser->parse("x * 1 + x * (7 / 7)");
		self::assertSame(2, $actual->evaluate(["x" => true]));
	}

	public function testMultiplicationOperatorStrengthReductionForLeftOperandOneWithGrouping() : void{
		$actual = $this->parser->parse("1 * (x + y + z)");
		self::assertSame(3, $actual->evaluate(["x" => true, "y" => false, "z" => 2]));
	}

	public function testMultiplicationOperatorStrengthReductionForRightOperandOne() : void{
		$actual = $this->parser->parse("1 * x + (7 / 7) * x");
		self::assertSame(2, $actual->evaluate(["x" => true]));
	}

	public function testMultiplicationOperatorStrengthReductionForRightOperandOneWithGrouping() : void{
		$actual = $this->parser->parse("1 * (x + y + z)");
		self::assertSame(3, $actual->evaluate(["x" => true, "y" => false, "z" => 2]));
	}

	public function testMultiplicationByOneConvertsBooleanToInteger() : void{
		self::assertSame(1, $this->parser->parse("1 * x")->evaluate(["x" => true]));
		self::assertSame(0, $this->parser->parse("x * 1")->evaluate(["x" => false]));
	}

	public function testMultiplicationOperatorStrengthReductionForOperandZero() : void{
		$expression = $this->parser->parse("x + y * 0 + z * (0 / 2)");
		self::assertEquals(1, $expression->evaluate(["x" => 1, "y" => 2, "z" => 3]));
		$this->expectException(InvalidArgumentException::class);
		$expression->evaluate(["x" => 1, "z" => 3]);
	}

	public function testMultiplicationOperatorStrengthReductionForOperandZeroWithGrouping() : void{
		$expression = $this->parser->parse("(x + y + z) * 0 + 0 * (x + y + z)");
		self::assertEquals(0, $expression->evaluate(["x" => 1, "y" => 2, "z" => 3]));
		$this->expectException(InvalidArgumentException::class);
		$expression->evaluate(["y" => 2, "z" => 3]);
	}

	public function testZeroMultiplicationPreservesNestedDivisionFailure() : void{
		$expression = $this->parser->parse("0 * (4 / x)");
		self::assertEquals(0, $expression->evaluate(["x" => 2]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 0]);
	}

	public function testDivisionOperatorStrengthReductionForEqualOperands() : void{
		$expression = $this->parser->parse("x / x + y / y + z / z");
		self::assertEquals(3, $expression->evaluate(["x" => 1, "y" => 2, "z" => 3]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 0, "y" => 2, "z" => 3]);
	}

	public function testDivisionOperatorStrengthReductionForEqualOperandsWithGrouping() : void{
		$expression = $this->parser->parse("(x + y + z) / (x + y + z)");
		self::assertEquals(1, $expression->evaluate(["x" => 1, "y" => 2, "z" => 3]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 1, "y" => -1, "z" => 0]);
	}

	public function testMultiplicationOperatorStrengthReductionForNanOperands() : void{
		$expression = $this->parser->parse("nan * x");
		self::assertNan($expression->evaluate(["x" => 2]));

		$expression = $this->parser->parse("x * nan");
		self::assertNan($expression->evaluate(["x" => 2]));
	}

	public function testDivisionOperatorStrengthReductionForCommutativelyEqualOperands() : void{
		$expression = $this->parser->parse("(x + y + z) / (y + x + z)");
		self::assertEquals(1, $expression->evaluate(["x" => 1, "y" => 2, "z" => 3]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 1, "y" => -1, "z" => 0]);
	}

	public function testDivisionOperatorStrengthReductionForLeftOperandZero() : void{
		$expression = $this->parser->parse("x + 0 / x + ((3 - 3) / y)");
		self::assertEquals(2, $expression->evaluate(["x" => 2, "y" => 3]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 0, "y" => 3]);
	}

	public function testDivisionOperatorStrengthReductionForLeftOperandZeroWithGrouping() : void{
		$expression = $this->parser->parse("0 / (x + y + z)");
		self::assertEquals(0, $expression->evaluate(["x" => 1, "y" => 2, "z" => 3]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 1, "y" => -1, "z" => 0]);
	}

	public function testDivisionOperatorStrengthReductionForRightOperandNan() : void{
		$expression = $this->parser->parse("mt_rand(1, 2) / nan");
		self::assertNan($expression->evaluate());
	}

	public function testDivisionOperatorStrengthReductionForRightOperandOne() : void{
		$actual = $this->parser->parse("x / 1 + y / 1");
		self::assertSame(1, $actual->evaluate(["x" => true, "y" => false]));
	}

	public function testDivisionOperatorStrengthReductionForRightOperandOneWithGrouping() : void{
		$actual = $this->parser->parse("(x + y + z) / 1");
		self::assertSame(3, $actual->evaluate(["x" => true, "y" => false, "z" => 2]));
	}

	public function testDivisionByOneConvertsBooleanToInteger() : void{
		self::assertSame(1, $this->parser->parse("x / 1")->evaluate(["x" => true]));
		self::assertSame(0, $this->parser->parse("x / 1")->evaluate(["x" => false]));
	}

	public function testDivisionOperatorStrengthReductionForCommonSubExpressionsAmongOperands() : void{
		$expression = $this->parser->parse("(x * y) / (y * z)");
		self::assertEquals(0.5, $expression->evaluate(["x" => 2, "y" => 3, "z" => 4]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 2, "y" => 0, "z" => 4]);
	}

	public function testDivisionOperatorStrengthReductionForNumericSubExpressionsAmongOperands() : void{
		$actual = $this->parser->parse("(x * 2) / (4 * y)");
		self::assertEqualsWithDelta(1 / 3, $actual->evaluate(["x" => 2, "y" => 3]), 0.00000001);
	}

	public function testDivisionPreservesFloatingPointOverflowInDenominator() : void{
		$source = "(x * 2) / (4 * y)";
		$variables = ["x" => 1, "y" => 1e308];
		$expected = $this->unoptimized_parser->parse($source)->evaluate($variables);
		self::assertSame(0.0, $expected);
		self::assertSame($expected, $this->parser->parse($source)->evaluate($variables));
	}

	public function testDivisionOperatorStrengthReductionForCommutativeFunctions() : void{
		$expression = $this->parser->parse("min(x, y) / min(y, x)");
		self::assertEquals(1, $expression->evaluate(["x" => 2, "y" => 3]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 0, "y" => 3]);
	}

	public function testDivisionOperatorStrengthReductionForNonCommutativeFunctions() : void{
		$actual = $this->parser->parse("pow(x, y) / pow(y, x)");
		$expected = $this->unoptimized_parser->parse("pow(x, y) / pow(y, x)");
		TestUtil::assertExpressionsEqual($expected, $actual);
	}

	public function testDivisionOperatorStrengthReductionForQuotientRuleOfExponents() : void{
		$expression = $this->parser->parse("x ** y / x ** z");
		self::assertEquals(4, $expression->evaluate(["x" => 2, "y" => 3, "z" => 1]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 0, "y" => 1, "z" => 1]);
	}

	public function testModuloOperatorStrengthReductionForRightOperandOne() : void{
		$expression = $this->parser->parse("y % (4 - 3)");
		self::assertSame(0, $expression->evaluate(["y" => 7]));
	}

	public function testModuloByOnePreservesLeftOperandEvaluation() : void{
		$expression = $this->parser->parse("(0 / y) % 1");
		self::assertSame(0, $expression->evaluate(["y" => 2]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["y" => 0]);
	}

	public function testModuloOperatorStrengthReductionForRightOperandOneWithGrouping() : void{
		$expression = $this->parser->parse("w % ((x * y) / (y * x))");
		self::assertEquals(0, $expression->evaluate(["w" => 7, "x" => 2, "y" => 3]));
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["w" => 7, "x" => 0, "y" => 3]);
	}

	public function testAdditionOperatorStrengthReductionForOperandZero() : void{
		$actual = $this->parser->parse("(x + 0) + (0 + y)");
		self::assertSame(1, $actual->evaluate(["x" => true, "y" => false]));
	}

	public function testAdditionOperatorStrengthReductionForOperandZeroWithGrouping() : void{
		$actual = $this->parser->parse("(x * y * z) + 0");
		self::assertSame(24, $actual->evaluate(["x" => 2, "y" => 3, "z" => 4]));
	}

	public function testAdditionWithZeroConvertsBooleanToInteger() : void{
		self::assertSame(1, $this->parser->parse("x + 0")->evaluate(["x" => true]));
		self::assertSame(0, $this->parser->parse("0 + x")->evaluate(["x" => false]));
	}

	public function testAdditionOperatorStrengthReductionForNanOperands() : void{
		$expression = $this->parser->parse("nan + x");
		self::assertNan($expression->evaluate(["x" => 2]));

		$expression = $this->parser->parse("x + nan");
		self::assertNan($expression->evaluate(["x" => 2]));
	}

	public function testAdditionOperatorStrengthReductionForNegativeLeftOperand() : void{
		$actual = $this->parser->parse("-x + y");
		self::assertSame(1, $actual->evaluate(["x" => 2, "y" => 3]));
	}

	public function testAdditionPreservesLeftOperandFailureOrder() : void{
		$expression = $this->parser->parse("-x + (1 / y)");
		$this->expectException(InvalidArgumentException::class);
		$expression->evaluate(["y" => 0]);
	}

	public function testAdditionOperatorStrengthReductionForNegativeRightOperand() : void{
		$actual = $this->parser->parse("x + -y");
		self::assertSame(-1, $actual->evaluate(["x" => 2, "y" => 3]));
	}

	public function testAdditionOperatorStrengthReductionForNegativeOperands() : void{
		$actual = $this->parser->parse("-x + -y");
		self::assertSame(-5, $actual->evaluate(["x" => 2, "y" => 3]));
	}

	public function testSubtractionOperatorStrengthReductionForOperandZero() : void{
		$actual = $this->parser->parse("(x - 0) - (0 - y)");
		self::assertSame(5, $actual->evaluate(["x" => 2, "y" => 3]));
	}

	public function testSubtractionOperatorStrengthReductionForOperandZeroWithGrouping() : void{
		$actual = $this->parser->parse("(x * y * z) - 0");
		self::assertSame(24, $actual->evaluate(["x" => 2, "y" => 3, "z" => 4]));
	}

	public function testSubtractionByZeroConvertsBooleanToInteger() : void{
		self::assertSame(1, $this->parser->parse("x - 0")->evaluate(["x" => true]));
		self::assertSame(0, $this->parser->parse("x - 0")->evaluate(["x" => false]));
	}

	public function testSubtractionOperatorStrengthReductionForEqualOperands() : void{
		$expression = $this->parser->parse("x ** (x - x) - 1");
		self::assertSame(0, $expression->evaluate(["x" => 5]));
	}

	public function testSubtractionOperatorStrengthReductionForEqualOperandsYieldingNan() : void{
		$expression = $this->parser->parse("inf - inf");
		self::assertInstanceOf(ConstantExpression::class, $expression);
		self::assertNan($expression->evaluate());
	}

	public function testSubtractionOperatorStrengthReductionForEqualOperandsWithGrouping() : void{
		$expression = $this->parser->parse("(x * y * z) - (x * y * z)");
		self::assertSame(0, $expression->evaluate(["x" => 2, "y" => 3, "z" => 4]));
	}

	public function testSubtractionOperatorStrengthReductionForCommutativelyEqualOperands() : void{
		$expression = $this->parser->parse("(x * y) - (y * x)");
		self::assertSame(0, $expression->evaluate(["x" => 2, "y" => 3]));
	}

	public function testSubtractionOperatorStrengthReductionForCommutativeFunctions() : void{
		$expression = $this->parser->parse("min(x, y) - min(y, x)");
		self::assertSame(0, $expression->evaluate(["x" => 2, "y" => 3]));
	}

	public function testSubtractionPreservesDivisionError() : void{
		$expression = $this->parser->parse("(1 / x) - (1 / x)");
		$this->expectException(DivisionByZeroError::class);
		$expression->evaluate(["x" => 0]);
	}

	public function testSubtractionPreservesMissingVariableError() : void{
		$expression = $this->parser->parse("x - x");
		$this->expectException(InvalidArgumentException::class);
		$expression->evaluate();
	}

	public function testSubtractionPreservesNonFiniteResult() : void{
		$expression = $this->parser->parse("x - x");
		self::assertNan($expression->evaluate(["x" => INF]));
	}

	public function testSubtractionOperatorStrengthReductionForNanOperands() : void{
		$expression = $this->parser->parse("nan - x");
		self::assertNan($expression->evaluate(["x" => 2]));

		$expression = $this->parser->parse("x - nan");
		self::assertNan($expression->evaluate(["x" => 2]));
	}

	public function testNanDoesNotSkipVariableOrDivisionErrors() : void{
		foreach(["nan * x", "x * nan", "nan + x", "x + nan", "x / nan", "nan - x", "x - nan"] as $source){
			$error = null;
			try{
				$this->parser->parse($source)->evaluate();
			}catch(Throwable $exception){
				$error = $exception::class;
			}
			self::assertSame(InvalidArgumentException::class, $error, $source);
		}

		$this->expectException(DivisionByZeroError::class);
		$this->parser->parse("nan + (1 / x)")->evaluate(["x" => 0]);
	}

	public function testSubtractionOperatorStrengthReductionForNegativeLeftOperand() : void{
		$actual = $this->parser->parse("-x - y");
		self::assertSame(-5, $actual->evaluate(["x" => 2, "y" => 3]));
	}

	public function testNegativeLeftSubtractionPreservesIntegerBoundary() : void{
		foreach(["(-1) - x", "-x - y"] as $source){
			$variables = ["x" => PHP_INT_MAX, "y" => 1];
			$expected = $this->unoptimized_parser->parse($source)->evaluate($variables);
			self::assertSame(PHP_INT_MIN, $expected);
			self::assertSame($expected, $this->parser->parse($source)->evaluate($variables), $source);
		}
	}

	public function testSubtractionOperatorStrengthReductionForNegativeRightOperand() : void{
		$actual = $this->parser->parse("x - -y");
		self::assertSame(5, $actual->evaluate(["x" => 2, "y" => 3]));
	}

	public function testSubtractionOperatorStrengthReductionForNegativeOperands() : void{
		$actual = $this->parser->parse("-x - -y");
		self::assertSame(1, $actual->evaluate(["x" => 2, "y" => 3]));
	}

	public function testModuloOperatorStrengthReductionForEqualRvalues() : void{
		$actual = $this->parser->parse("x % y % y");
		$expected = $this->unoptimized_parser->parse("x % y");
		TestUtil::assertExpressionsEqual($expected, $actual);

		$actual = $this->parser->parse("x % y % x");
		$expected = $this->unoptimized_parser->parse("x % y % x");
		TestUtil::assertExpressionsEqual($expected, $actual);
	}

	public function testPositiveOperatorPreservesArithmeticConversion() : void{
		$actual = $this->parser->parse("+x");
		$expected = $this->unoptimized_parser->parse("+x");
		TestUtil::assertExpressionsEqual($expected, $actual);
		self::assertSame(1, $actual->evaluate(["x" => true]));
		self::assertSame(0, $actual->evaluate(["x" => false]));
	}

	public function testRepeatedNegationPreservesArithmetic() : void{
		$actual = $this->parser->parse("---x");
		$plain = $this->unoptimized_parser->parse("---x");
		foreach([2, -3, true, false] as $value){
			self::assertSame($plain->evaluate(["x" => $value]), $actual->evaluate(["x" => $value]));
		}
	}

	public function testIdempotenceFoldingForSingleArgumentFunction() : void{
		$actual = $this->parser->parse("ceil(ceil(ceil(mt_rand(1, 2))))");
		$expected = $this->unoptimized_parser->parse("ceil(mt_rand(1, 2))");
		TestUtil::assertExpressionsEqual($expected, $actual);
	}

	public function testExpressionTokenTreeTraversesDeepNestingWithoutRecursion() : void{
		$token = new NumericLiteralExpressionToken(new Position(0, 1), 7);
		$tree = new ExpressionTokenTree([$token]);
		for($i = 0; $i < 512; ++$i){
			$tree = new ExpressionTokenTree([$tree]);
		}

		self::assertSame([$token], $tree->flatten());
		self::assertCount(513, iterator_to_array($tree->traversePostOrder(), false));
	}

	public function testExpressionTokenTreeRejectsMissingFunctionOperands() : void{
		$operand = new NumericLiteralExpressionToken(new Position(0, 1), 7);
		$function = new FunctionCallExpressionToken(new Position(1, 2), "sum", 2, static fn() : int => 0, 0);

		$this->expectException(InvalidArgumentException::class);
		ExpressionTokenTree::fromPostfix($this->parser, [$operand, $function]);
	}

	public function testIdempotenceFoldingForSingleArgumentFunctionWithoutCommutativity() : void{
		$actual = $this->parser->parse("round(round(round(x)))");
		$expected = $this->unoptimized_parser->parse("round(x)");
		TestUtil::assertExpressionsEqual($expected, $actual);
	}

	public function testIdempotenceFoldingForMultiArgumentFunctionWithCommutativity() : void{
		$actual = $this->parser->parse("min(x, min(y), min(min(z)))");
		$expected = $this->unoptimized_parser->parse("min(x, y, z)");
		TestUtil::assertExpressionsEqual($expected, $actual);
	}

	public function testIdempotenceFoldingForMultiArgumentFunctionWithCommutativityAndDifferingArgumentCount() : void{
		$actual = $this->parser->parse("min(x, min(y, min(z, w)))");
		$expected = $this->unoptimized_parser->parse("min(x, y, z, w)");
		TestUtil::assertExpressionsEqual($expected, $actual);
		self::assertEquals(3, $actual->evaluate(["w" => 3, "x" => 4, "y" => 5, "z" => 6]));
		self::assertEquals(3, $actual->evaluate(["w" => 4, "x" => 5, "y" => 6, "z" => 3]));
		self::assertEquals(3, $actual->evaluate(["w" => 5, "x" => 6, "y" => 3, "z" => 4]));
		self::assertEquals(3, $actual->evaluate(["w" => 6, "x" => 3, "y" => 4, "z" => 5]));
	}

	public function testIdempotenceFoldingForMultiArgumentFunctionWithoutCommutativity() : void{
		$actual = $this->parser->parse("round(round(x, 2), 2)");
		$expected = $this->unoptimized_parser->parse("round(round(x, 2), 2)");
		TestUtil::assertExpressionsEqual($expected, $actual);
	}
}
