<?php

declare(strict_types=1);
namespace Bga\Games\Fate\Tests;

use Bga\Games\Fate\OpCommon\MathExpression;
use Bga\Games\Fate\OpCommon\MathExpressionParser;
use Exception;
use PHPUnit\Framework\TestCase;

final class MathExpressionTest extends TestCase {
    /**
     * Test parsing and evaluating min with two numeric arguments
     */
    public function testMinFunctionWithTwoNumbers() {
        $expr = MathExpression::parse("min(5,3)");

        $this->assertEquals("min(5,3)", (string) $expr);

        $result = $expr->evaluate(function ($x) {
            return $x;
        });

        $this->assertEquals(3, $result);
    }

    /**
     * Test min function with two variables
     */
    public function testMinFunctionWithTwoVariables() {
        $expr = MathExpression::parse("min(a,b)");

        $this->assertEquals("min(a,b)", (string) $expr);

        $result = $expr->evaluate(function ($x) {
            $values = ["a" => 10, "b" => 7];
            return $values[$x] ?? 0;
        });

        $this->assertEquals(7, $result);
    }

    /**
     * Test min function with expressions as arguments
     */
    public function testMinFunctionWithExpressions() {
        $expr = MathExpression::parse("min(a+5,b*2)");

        $result = $expr->evaluate(function ($x) {
            $values = ["a" => 3, "b" => 5];
            return $values[$x] ?? 0;
        });

        // min(3+5, 5*2) = min(8, 10) = 8
        $this->assertEquals(8, $result);
    }

    /**
     * Test min function with more than two arguments
     */
    public function testMinFunctionWithMultipleArguments() {
        $expr = MathExpression::parse("min(10,5,15,3)");

        $result = $expr->evaluate(function ($x) {
            return $x;
        });

        $this->assertEquals(3, $result);
    }

    /**
     * Test min function with negative numbers
     */
    public function testMinFunctionWithNegativeNumbers() {
        $expr = MathExpression::parse("min(-5,3)");

        $result = $expr->evaluate(function ($x) {
            return $x;
        });

        $this->assertEquals(-5, $result);
    }

    /**
     * Test min function with all equal values
     */
    public function testMinFunctionWithEqualValues() {
        $expr = MathExpression::parse("min(5,5,5)");

        $result = $expr->evaluate(function ($x) {
            return $x;
        });

        $this->assertEquals(5, $result);
    }

    /**
     * Test min function throws exception with less than 2 arguments
     */
    public function testMinFunctionWithOneArgumentThrowsException() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("min function requires at least 2 arguments");

        $expr = MathExpression::parse("min(5)");
        $expr->evaluate(function ($x) {
            return $x;
        });
    }

    /**
     * Test min function with complex nested expressions
     */
    public function testMinFunctionWithComplexExpressions() {
        $expr = MathExpression::parse("min(a+b,c*2)");

        $result = $expr->evaluate(function ($x) {
            $values = ["a" => 3, "b" => 4, "c" => 2];
            return $values[$x] ?? 0;
        });

        // min(3+4, 2*2) = min(7, 4) = 4
        $this->assertEquals(4, $result);
    }

    /**
     * Test min function can be used within larger expressions
     */
    public function testMinFunctionInLargerExpression() {
        $expr = MathExpression::parse("min(a,b)+c");

        $result = $expr->evaluate(function ($x) {
            $values = ["a" => 5, "b" => 3, "c" => 2];
            return $values[$x] ?? 0;
        });

        // min(5,3) + 2 = 3 + 2 = 5
        $this->assertEquals(5, $result);
    }

    /**
     * Test min function array representation
     */
    public function testMinFunctionToArray() {
        $expr = MathExpression::parse("min(5,3)");
        $array = $expr->toArray();

        $this->assertEquals(["min", "5", "3"], $array);
    }

    /**
     * Test min function with expressions array representation
     */
    public function testMinFunctionWithExpressionsToArray() {
        $expr = MathExpression::parse("min(a+5,b*2)");
        $array = $expr->toArray();

        $this->assertEquals(["min", ["+", "a", "5"], ["*", "b", "2"]], $array);
    }

    /**
     * Test min function with comparison operators
     */
    public function testMinFunctionWithComparisonResult() {
        $expr = MathExpression::parse("min(a,b)>2");

        $result = $expr->evaluate(function ($x) {
            $values = ["a" => 5, "b" => 3];
            return $values[$x] ?? 0;
        });

        // min(5,3) > 2 = 3 > 2 = true = 1
        $this->assertEquals(1, $result);
    }

    /**
     * Test min function with zero values
     */
    public function testMinFunctionWithZeroValues() {
        $expr = MathExpression::parse("min(0,5)");

        $result = $expr->evaluate(function ($x) {
            return $x;
        });

        $this->assertEquals(0, $result);
    }

    /**
     * Test min function preserves integer type
     */
    public function testMinFunctionReturnsInteger() {
        $expr = MathExpression::parse("min(5,3)");

        $result = $expr->evaluate(function ($x) {
            return $x;
        });

        $this->assertIsInt($result);
    }

    public function testMaxFunctionWithTwoNumbers() {
        $expr = MathExpression::parse("max(5,3)");
        $this->assertEquals("max(5,3)", (string) $expr);
        $result = $expr->evaluate(fn($x) => $x);
        $this->assertEquals(5, $result);
    }

    public function testMaxFunctionWithVariables() {
        $expr = MathExpression::parse("max(a,b)");
        $result = $expr->evaluate(fn($x) => ["a" => 2, "b" => 7][$x] ?? 0);
        $this->assertEquals(7, $result);
    }

    public function testMaxFunctionWithMultipleArguments() {
        $expr = MathExpression::parse("max(10,5,15,3)");
        $result = $expr->evaluate(fn($x) => $x);
        $this->assertEquals(15, $result);
    }

    public function testMaxFunctionWithOneArgumentThrowsException() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("max function requires at least 2 arguments");
        $expr = MathExpression::parse("max(5)");
        $expr->evaluate(fn($x) => $x);
    }

    // -------------------------------------------------------------------------
    // or / and aliases
    // -------------------------------------------------------------------------

    public function testOrAliasForLogicalOr(): void {
        $expr = MathExpression::parse("a or b");
        $result = $expr->evaluate(fn($x) => ["a" => 0, "b" => 1][$x] ?? 0);
        $this->assertEquals(1, $result);
    }

    public function testOrBothFalse(): void {
        $expr = MathExpression::parse("a or b");
        $result = $expr->evaluate(fn($x) => 0);
        $this->assertEquals(0, $result);
    }

    public function testOrBothTrue(): void {
        $expr = MathExpression::parse("a or b");
        $result = $expr->evaluate(fn($x) => 1);
        $this->assertEquals(1, $result);
    }

    public function testAndAliasForLogicalAnd(): void {
        $expr = MathExpression::parse("a and b");
        $result = $expr->evaluate(fn($x) => ["a" => 1, "b" => 1][$x] ?? 0);
        $this->assertEquals(1, $result);
    }

    public function testAndOneFalse(): void {
        $expr = MathExpression::parse("a and b");
        $result = $expr->evaluate(fn($x) => ["a" => 1, "b" => 0][$x] ?? 0);
        $this->assertEquals(0, $result);
    }

    public function testOrWithComparison(): void {
        // rank==3 or legend — simulates the Courage card filter
        $expr = MathExpression::parse("rank==3 or legend");
        // rank=3, not legend → true (rank matches)
        $result = $expr->evaluate(fn($x) => ["rank" => 3, "legend" => 0][$x] ?? 0);
        $this->assertEquals(1, $result);
        // rank=1, legend → true (legend matches)
        $result = $expr->evaluate(fn($x) => ["rank" => 1, "legend" => 1][$x] ?? 0);
        $this->assertEquals(1, $result);
        // rank=1, not legend → false
        $result = $expr->evaluate(fn($x) => ["rank" => 1, "legend" => 0][$x] ?? 0);
        $this->assertEquals(0, $result);
    }

    public function testAndWithComparison(): void {
        // Note: no operator precedence, so use parentheses for complex expressions
        $expr = MathExpression::parse("(a>2) and (b<5)");
        $result = $expr->evaluate(fn($x) => ["a" => 3, "b" => 4][$x] ?? 0);
        $this->assertEquals(1, $result);
        $result = $expr->evaluate(fn($x) => ["a" => 1, "b" => 4][$x] ?? 0);
        $this->assertEquals(0, $result);
    }

    public function testChainedOrOperators(): void {
        // a or b or c — tests that the loop handles 3+ chained binary ops
        $expr = MathExpression::parse("a or b or c");
        $result = $expr->evaluate(fn($x) => ["a" => 0, "b" => 0, "c" => 1][$x] ?? 0);
        $this->assertEquals(1, $result);
        $result = $expr->evaluate(fn($x) => 0);
        $this->assertEquals(0, $result);
    }

    public function testChainedBinaryOperators(): void {
        // 1 + 2 + 3 — tests that chained addition works
        $this->checkExprValue("1 + 2 + 3", 6);
    }

    public function testEqualityOperator(): void {
        $this->checkExprValue("10 == 10", 1);
        $this->checkExprValue("10 == 11", 0);
    }

    public function testOpExpressionPush(): void {
        $res = MathExpressionParser::parse("a1 + a2");
        $this->assertEquals("(a1 + a2)", $res->__toString());

        $res = MathExpressionParser::parse("a <= 10");
        $this->assertEquals("(a <= 10)", $res->__toString());

        $res = MathExpressionParser::parse("a <= -10");
        $this->assertEquals("(a <= -10)", $res->__toString());
        $mapper = function ($x) {
            return 10;
        };
        $this->assertEquals(0, $res->evaluate($mapper));

        $res = MathExpressionParser::parse("b >= 10");
        $this->assertEquals("(b >= 10)", $res->__toString());
        $mapper = function ($x) {
            return 1;
        };
        $this->assertEquals(0, $res->evaluate($mapper));

        $mapper = function ($x) {
            return 10;
        };
        $this->assertEquals(1, $res->evaluate($mapper));

        $res = MathExpressionParser::parse("(gen)");
        $this->assertEquals("gen", $res->__toString());
    }

    function checkExpr(string $expr, int $result, $mapper = null) {
        $res = MathExpressionParser::parse($expr);
        $this->assertEquals($expr, $res->__toString());
        $this->assertEquals($result, $res->evaluate($mapper));
    }
    function checkExprValue(string $expr, int $result, $mapper = null) {
        $res = MathExpressionParser::parse($expr);
        $this->assertEquals($result, $res->evaluate($mapper));
    }
    public function testOpExpressionEval(): void {
        $this->checkExprValue("2+2", 4);
        $this->checkExpr("(2 + 2)", 4);

        $this->checkExprValue("2 < 10", 1);
        $this->checkExprValue("2 > 10", 0);
        $this->checkExprValue("10 >= 10", 1);
        $this->checkExprValue("10 <= 10", 1);
        $this->checkExprValue("10 <= 11", 1);
        $this->checkExprValue("10 >= 11", 0);
        $this->checkExprValue("2*3", 6);
        $this->checkExprValue("5/2", 2);
        $this->checkExprValue("5%2", 1);
        $this->checkExprValue("(1 + 2) + 3", 6);
        $this->checkExpr("(1 & 1)", 1);
        $this->checkExpr("((1 > 0) & (2 > 10))", 0);
        $this->checkExprValue("(1 >= 0) & (0 >= 1)", 0);

        //$this->checkExprValue("10 == 11",0);
        //$this->checkExprValue("10 == 10",1);

        $mapper = function ($x) {
            switch ($x) {
                case "a":
                    return 3;
                case "b":
                    return 7;
                case "t":
                    return -3;
                case "g":
                    return 1;
                default:
                    return $x;
            }
        };

        $this->checkExpr("(a + 2)", 5, $mapper);
        $this->checkExpr("(b - a)", 4, $mapper);
        $this->checkExprValue("(a > 0) & (b > 0)", 1, $mapper);
        $this->checkExprValue("t>=-10", 1, $mapper);
        $this->checkExprValue("t<-1", 1, $mapper);
        $this->checkExprValue("t>-1", 0, $mapper);

        $this->checkExprValue("(g>=3)*4", 0, $mapper);

        //$this->checkExpr("- a",-3,$mapper);
    }
}
