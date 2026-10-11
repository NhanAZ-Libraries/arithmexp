# arithmexp

> [!NOTE]
> This NhanAZ-Libraries fork preserves the [Muqsit upstream project](https://github.com/Muqsit/arithmexp). Its patch makes the virion compatible with DevTools shading and fixes strict inequality, opcode token identity, and evaluation type handling. The upstream library remains the source for general Composer installation and feature documentation. The fork has no stable release until its required validation is complete; PHPStan at maximum level still reports source errors.

[![Upstream CI](https://github.com/Muqsit/arithmexp/actions/workflows/ci.yml/badge.svg)](https://github.com/Muqsit/arithmexp/actions/workflows/ci.yml)
[![Upstream latest release](https://img.shields.io/github/v/release/Muqsit/arithmexp)](https://github.com/Muqsit/arithmexp/releases/latest)

[`arithmexp`](https://github.com/Muqsit/arithmexp) is a powerful mathematical expression parser and evaluator library for PHP with support for [variable substitution](https://github.com/Muqsit/arithmexp#evaluating-a-mathematical-expression), [constant declaration](https://github.com/Muqsit/arithmexp/wiki), [deterministic and non-deterministic function registration](https://github.com/Muqsit/arithmexp/wiki), and more.

## Unreleased fork compatibility note

The fork's pending source revision removes unsafe rewrites of `0 / x`, `x / x`, `x ** y / x ** z`, zero multiplied by an expression, exponentiation with variable operands, `% 1` with a variable left operand, subtraction of identical variable expressions, reordering a negative left operand in addition, multiplication by one, addition with zero, subtraction by zero, division by one, unary plus, and NaN combined with a variable expression. These expressions now retain operand evaluation order or arithmetic conversion. A zero denominator raises `DivisionByZeroError` at evaluation time, as it does with the unoptimized parser. Several changes also preserve non-finite results or arithmetic result types in their affected cases. Earlier fork source could fold these expressions to a value and hide an error or change the result.

The default parser no longer registers `ReorderExpressionOptimizer`. A commutative operator does not guarantee that regrouping floating-point operands preserves the result. For example, `x + y + z` with `x = 1e16`, `y = -1e16` and `z = 1` now evaluates to `1` in both default and unoptimized parsers. The optimizer class remains available for explicit registration, but callers that opt in must validate its effects for their expressions. Division strength reduction also no longer cancels numeric factors across separate products, because that can change overflow and rounding. Other algebraic rewrites still need an error-preservation audit, so this change does not establish full equivalence between optimized and unoptimized evaluation. No stable fork release includes this change yet.

Subtraction with a negative left operand now keeps its original grouping. Rewriting `(-1) - x` as a negated sum promoted the result from an integer to a float when `x` was `PHP_INT_MAX`.

Nested `min()` and `max()` calls also retain their argument order and grouping. Flattening them changed results when an argument was NaN, because PHP's comparison behavior for NaN depends on evaluation order.

`sqrt()` now calls PHP's square-root function directly. Rewriting it as exponentiation changed the sign of negative zero.

The pending parser changes reject empty parentheses used as operands and argument separators consumed as operator operands with a `ParseException`. These malformed expressions previously could hang or expose an internal `RuntimeException`. Zero-argument calls such as `pi()` remain valid.

> [!TIP]
> Try out arithmexp parser on the [**demo site**](https://arithmexp.cosmicpe.dev/)!

## Installation with composer
```
composer require muqsit/arithmexp
```

## Evaluating a mathematical expression
To evaluate a mathematical expression, create a [`Parser`](https://github.com/Muqsit/arithmexp/blob/master/src/muqsit/arithmexp/Parser.php) and invoke `Parser::parse()` with an expression string to obtain a reusable [`Expression`](https://github.com/Muqsit/arithmexp/blob/master/src/muqsit/arithmexp/expression/Expression.php) object.
`Expression::evaluate()` returns the value of the expression.
```php
$parser = Parser::createDefault();
$expression = $parser->parse("2 + 3");
var_dump($expression->evaluate()); // int(5)

$expression = $parser->parse("mt_rand()");
var_dump($expression->evaluate()); // int(1370501507)
var_dump($expression->evaluate()); // int(1522981420)
```

Variables may be substituted at evaluation-time by passing an `array<string, int|float|bool>` value to `Expression::evaluate()`.
```php
$expression = $parser->parse("x + y");
var_dump($expression->evaluate(["x" => 2, "y" => 3])); // int(5)
var_dump($expression->evaluate(["x" => 1.5, "y" => 1.5])); // float(3)

$expression = $parser->parse("a > b or c");
var_dump($expression->evaluate(["a" => 1, "b" => 2, "c" => true])); // bool(true)
```
The return value type of the evaluation is consistent with that of PHP's—`int + int` returns an `int` value, while `float + int|float` returns a `float` value.
`bool + bool` returns an `int` value, while `int || int` returns a `bool` value.

> [!NOTE]
> Check out the [**wiki**](https://github.com/Muqsit/arithmexp/wiki) for documentation notes and further implementation details.
