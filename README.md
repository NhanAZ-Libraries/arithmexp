# arithmexp

> [!NOTE]
> This NhanAZ-Libraries fork preserves the [Muqsit upstream project](https://github.com/Muqsit/arithmexp). Its patch makes the virion compatible with DevTools shading and fixes strict inequality, opcode token identity, and evaluation type handling. The upstream library remains the source for general Composer installation and feature documentation. The fork has no stable release until its required validation is complete; PHPStan at maximum level still reports source errors.

[![Upstream CI](https://github.com/Muqsit/arithmexp/actions/workflows/ci.yml/badge.svg)](https://github.com/Muqsit/arithmexp/actions/workflows/ci.yml)
[![Upstream latest release](https://img.shields.io/github/v/release/Muqsit/arithmexp)](https://github.com/Muqsit/arithmexp/releases/latest)

[`arithmexp`](https://github.com/Muqsit/arithmexp) is a powerful mathematical expression parser and evaluator library for PHP with support for [variable substitution](https://github.com/Muqsit/arithmexp#evaluating-a-mathematical-expression), [constant declaration](https://github.com/Muqsit/arithmexp/wiki), [deterministic and non-deterministic function registration](https://github.com/Muqsit/arithmexp/wiki), and more.

## Unreleased fork compatibility note

The fork's pending source revision removes unsafe rewrites of `0 / x`, `x / x`, `x ** y / x ** z`, zero multiplied by an expression, exponentiation with variable operands, and `% 1` with a variable left operand. These expressions now retain operand evaluation; a zero denominator raises `DivisionByZeroError` at evaluation time, as it does with the unoptimized parser. The exponentiation change also preserves results for negative exponents and boolean operands. Earlier fork source could fold these expressions to a value and hide an error or change the result. Other algebraic rewrites still need an error-preservation audit, so this change does not establish full equivalence between optimized and unoptimized evaluation. No stable fork release includes this change yet.

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
