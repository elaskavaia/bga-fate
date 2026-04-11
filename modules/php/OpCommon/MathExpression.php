<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);
namespace Bga\Games\Fate\OpCommon;

use Exception;

// MathExpression depends on OpLexer which is defined in OpExpression.php
require_once __DIR__ . "/OpExpression.php";

/** This can evaluate simple math expressions for purposes of pre-condition or achievements evaluation
 *
 */
abstract class MathExpression {
    abstract public function evaluate($mapper);
    abstract public function __toString();
    abstract public function toArray();
    static function parse($str) {
        return MathExpressionParser::parse($str);
    }

    public static function arr($str) {
        $expr = static::parse($str);
        $res = $expr->toArray();
        return $res;
    }
}
class MathTerminalExpression extends MathExpression {
    public $left;
    function __construct($left) {
        $this->left = $left;
    }
    public function evaluate($mapper) {
        $value = $this->left;
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (!$value) {
            return 0;
        }
        $value = $mapper($value);
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (!$value) {
            return 0;
        }
        throw new Exception("Failed to resolved MathTerminalExpression '$value'");
    }
    public function __toString() {
        return $this->left;
    }

    public function toArray() {
        return $this->left;
    }
}
class MathUnaryExpression extends MathExpression {
    public $op;
    public $right;
    function __construct(string $op, $right) {
        if (!is_string($op)) {
            throw new Exception("Operator should be string");
        }
        $this->op = $op;
        $this->right = $right;
    }
    public function __toString() {
        return sprintf("(%s(%s))", $this->op, $this->right);
    }
    public function evaluate($mapper) {
        $right = $this->right->evaluate($mapper);
        $op = $this->op;
        throw new Exception("Cannot evaluate MathUnaryExpression");
        //$res = eval("return $op($right);");
        //return (int)($res);
    }

    public function toArray() {
        return [$this->op, $this->right->toArray()];
    }
}
class MathBinaryExpression extends MathExpression {
    public $op;
    public $left;
    public $right;

    function __construct(string $op, $left, $right) {
        if (!is_string($op)) {
            throw new Exception("Operator should be string");
        }
        $this->op = $op;
        $this->left = $left;
        $this->right = $right;
    }

    public function __toString() {
        return sprintf("(%s %s %s)", $this->left, $this->op, $this->right);
    }

    public function toArray() {
        return [$this->op, $this->left->toArray(), $this->right->toArray()];
    }

    public function evaluate($mapper) {
        $op = $this->op;
        if ($op == "(") {
            // function calls?
        }
        $left = $this->left->evaluate($mapper);
        $right = $this->right->evaluate($mapper);

        //$res = eval("return $left $op $right;");
        $res = 0;
        switch ($op) {
            case "+":
                $res = $left + $right;
                break;
            case "-":
                $res = $left - $right;
                break;
            case "/":
                $res = $left / $right;
                break;
            case "%":
                $res = $left % $right;
                break;
            case "*":
                $res = $left * $right;
                break;
            case "<":
                $res = $left < $right;
                break;
            case "<=":
                $res = $left <= $right;
                break;
            case ">":
                $res = $left > $right;
                break;
            case ">=":
                $res = $left >= $right;
                break;
            case "==":
                $res = $left == $right;
                break;
            case "&":
                $res = $left & $right;
                break;
            case "|":
                $res = $left | $right;
                break;
            case "&&":
                $res = $left && $right;
                break;
            case "||":
                $res = $left || $right;
                break;
        }
        return (int) $res;
    }
}

class MathTernaryExpression extends MathExpression {
    public $condition;
    public $consequent;
    public $alternate;

    function __construct($condition, $consequent, $alternate) {
        $this->condition = $condition;
        $this->consequent = $consequent;
        $this->alternate = $alternate;
    }

    public function __toString() {
        return sprintf("(%s ? %s : %s)", $this->condition, $this->consequent, $this->alternate);
    }

    public function toArray() {
        return ["?:", $this->condition->toArray(), $this->consequent->toArray(), $this->alternate->toArray()];
    }

    public function evaluate($mapper) {
        $conditionValue = $this->condition->evaluate($mapper);
        if ($conditionValue) {
            return $this->consequent->evaluate($mapper);
        } else {
            return $this->alternate->evaluate($mapper);
        }
    }
}

class MathFunctionExpression extends MathExpression {
    public $name;
    public $args;

    function __construct(string $name, array $args) {
        $this->name = $name;
        $this->args = $args;
    }

    public function __toString() {
        $argStrings = array_map(function ($arg) {
            return (string) $arg;
        }, $this->args);
        return sprintf("%s(%s)", $this->name, implode(",", $argStrings));
    }

    public function toArray() {
        $argArrays = array_map(function ($arg) {
            return $arg->toArray();
        }, $this->args);
        return [$this->name, ...$argArrays];
    }

    public function evaluate($mapper) {
        $evaluatedArgs = array_map(function ($arg) use ($mapper) {
            return $arg->evaluate($mapper);
        }, $this->args);

        switch ($this->name) {
            case "min":
                if (count($evaluatedArgs) < 2) {
                    throw new Exception("min function requires at least 2 arguments");
                }
                return (int) min(...$evaluatedArgs);
            case "max":
                if (count($evaluatedArgs) < 2) {
                    throw new Exception("max function requires at least 2 arguments");
                }
                return (int) max(...$evaluatedArgs);
            default:
                throw new Exception("Unknown function: {$this->name}");
        }
    }
}

class MathExpressionParser {
    private $tokens;
    private $lexer;
    function __construct($str) {
        $this->lexer = new MathLexer();
        $tokens = $this->lexer->tokenize($str);
        $this->tokens = $tokens;
    }
    static function parse($str): MathExpression {
        $parser = new MathExpressionParser($str);
        return $parser->parseExpression();
    }
    function peek() {
        if ($this->isEos()) {
            return null;
        }
        $pop = $this->tokens[0];
        return $pop;
    }
    function eos() {
        if (!$this->isEos()) {
            throw new Exception("Unexpected tokens " . join(" ", $this->tokens));
        }
    }
    function isEos() {
        return count($this->tokens) == 0;
    }

    function pop() {
        if ($this->isEos()) {
            throw new Exception("Cannot shift");
        }
        $pop = array_shift($this->tokens);
        return $pop;
    }
    function consume($bip) {
        $pop = $this->pop();
        if ($bip != $pop) {
            throw new Exception("Expected $bip but got $pop");
        }
    }
    function parseTerm() {
        $lookup = $this->peek();
        if ($lookup == "(") {
            $this->consume("(");
            $expr = $this->parseExpression();
            $this->consume(")");
            return $expr;
        }
        $op = $this->pop();
        $tt = $this->lexer->getTerminalName($op);
        if ($tt != "T_IDENTIFIER" && $tt != "T_NUMBER") {
            throw new Exception("Unexpected token '$op' $tt");
        }

        // Check if this is a function call
        if ($tt == "T_IDENTIFIER" && $this->peek() == "(") {
            $this->consume("(");
            $args = $this->parseFunctionArgs();
            $this->consume(")");
            return new MathFunctionExpression($op, $args);
        }

        return new MathTerminalExpression($op);
    }

    function parseFunctionArgs() {
        $args = [];

        // Handle empty argument list
        if ($this->peek() == ")") {
            return $args;
        }

        // Parse first argument (stop at comma)
        $args[] = $this->parseExpression([","]);

        // Parse remaining arguments separated by commas
        while ($this->peek() == ",") {
            $this->consume(",");
            $args[] = $this->parseExpression([","]);
        }

        return $args;
    }
    private function resolveOperator(string $op): string {
        $tt = $this->lexer->getTerminalName($op);
        if ($tt == "T_IDENTIFIER") {
            if ($op === "or") {
                return "||";
            } elseif ($op === "and") {
                return "&&";
            }
            throw new Exception("Unexpected token $op");
        }
        if ($tt == "T_NUMBER") {
            throw new Exception("Unexpected token $op");
        }
        return $op;
    }

    function parseExpression($stopTokens = []) {
        $left = $this->parseTerm();

        while (true) {
            $lookup = $this->peek();

            if ($lookup === null || $lookup === ")" || in_array($lookup, $stopTokens)) {
                return $left;
            }

            if ($lookup === "?") {
                return $this->parseTernary($left, $stopTokens);
            }

            $op = $this->resolveOperator($this->pop());
            $right = $this->parseTerm();
            $left = new MathBinaryExpression($op, $left, $right);
        }
    }

    function parseTernary($condition, $stopTokens = []) {
        $this->consume("?");
        $consequent = $this->parseExpression(array_merge($stopTokens, [":"]));
        $this->consume(":");
        $alternate = $this->parseExpression($stopTokens);
        return new MathTernaryExpression($condition, $consequent, $alternate);
    }
}

class MathLexer extends OpLexer {
    function __construct() {
        parent::__construct();
    }
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new MathLexer();
        }

        return self::$instance;
    }

    static function toregex(string $str) {
        if ($str[0] == '\'') {
            $str = static::unquote($str);
        }

        if ($str[0] == "/") {
            return $str;
        }
        return "/^{$str}\$/";
    }

    static function unquote(string $str) {
        return stripslashes(substr(substr($str, 1), 0, -1));
    }
}
