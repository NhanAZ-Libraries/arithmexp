# arithmexp

> [!NOTE]
> This NhanAZ-Libraries fork preserves the [Muqsit upstream project](https://github.com/Muqsit/arithmexp). Its current patch replaces two dynamic class-name diagnostics with `get_debug_type()` and checks matcher types through class and interface names so DevTools can shade the virion for Calculator. The upstream library remains the source for general Composer installation and feature documentation. The fork has no stable release until its required validation is complete.

[![Upstream CI](https://github.com/Muqsit/arithmexp/actions/workflows/ci.yml/badge.svg)](https://github.com/Muqsit/arithmexp/actions/workflows/ci.yml)
[![Upstream latest release](https://img.shields.io/github/v/release/Muqsit/arithmexp)](https://github.com/Muqsit/arithmexp/releases/latest)

[`arithmexp`](https://github.com/Muqsit/arithmexp) is a powerful mathematical expression parser and evaluator library for PHP with support for [variable substitution](https://github.com/Muqsit/arithmexp#evaluating-a-mathematical-expression), [constant declaration](https://github.com/Muqsit/arithmexp/wiki), [deterministic and non-deterministic function registration](https://github.com/Muqsit/arithmexp/wiki), and more.

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
