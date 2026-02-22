<?php

declare(strict_types=1);
namespace Bga\Games\Fate\Tests;

use Bga\Games\Fate\OpCommon\MathExpression;
use Exception;
use PHPUnit\Framework\TestCase;

final class MathExpressionTest extends TestCase {

    /**
     * Test parsing and evaluating min with two numeric arguments
     */
    public function testMinFunctionWithTwoNumbers() {
        $expr = MathExpression::parse("min(5,3)");

        $this->assertEquals("min(5,3)", (string)$expr);

        $result = $expr->evaluate(function($x) {
            return $x;
        });

        $this->assertEquals(3, $result);
    }

    /**
     * Test min function with two variables
     */
    public function testMinFunctionWithTwoVariables() {
        $expr = MathExpression::parse("min(a,b)");

        $this->assertEquals("min(a,b)", (string)$expr);

        $result = $expr->evaluate(function($x) {
            $values = ['a' => 10, 'b' => 7];
            return $values[$x] ?? 0;
        });

        $this->assertEquals(7, $result);
    }

    /**
     * Test min function with expressions as arguments
     */
    public function testMinFunctionWithExpressions() {
        $expr = MathExpression::parse("min(a+5,b*2)");

        $result = $expr->evaluate(function($x) {
            $values = ['a' => 3, 'b' => 5];
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

        $result = $expr->evaluate(function($x) {
            return $x;
        });

        $this->assertEquals(3, $result);
    }

    /**
     * Test min function with negative numbers
     */
    public function testMinFunctionWithNegativeNumbers() {
        $expr = MathExpression::parse("min(-5,3)");

        $result = $expr->evaluate(function($x) {
            return $x;
        });

        $this->assertEquals(-5, $result);
    }

    /**
     * Test min function with all equal values
     */
    public function testMinFunctionWithEqualValues() {
        $expr = MathExpression::parse("min(5,5,5)");

        $result = $expr->evaluate(function($x) {
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
        $expr->evaluate(function($x) {
            return $x;
        });
    }

    /**
     * Test min function with complex nested expressions
     */
    public function testMinFunctionWithComplexExpressions() {
        $expr = MathExpression::parse("min(a+b,c*2)");

        $result = $expr->evaluate(function($x) {
            $values = ['a' => 3, 'b' => 4, 'c' => 2];
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

        $result = $expr->evaluate(function($x) {
            $values = ['a' => 5, 'b' => 3, 'c' => 2];
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

        $result = $expr->evaluate(function($x) {
            $values = ['a' => 5, 'b' => 3];
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

        $result = $expr->evaluate(function($x) {
            return $x;
        });

        $this->assertEquals(0, $result);
    }

    /**
     * Test min function preserves integer type
     */
    public function testMinFunctionReturnsInteger() {
        $expr = MathExpression::parse("min(5,3)");

        $result = $expr->evaluate(function($x) {
            return $x;
        });

        $this->assertIsInt($result);
    }

    public function testMaxFunctionWithTwoNumbers() {
        $expr = MathExpression::parse("max(5,3)");
        $this->assertEquals("max(5,3)", (string)$expr);
        $result = $expr->evaluate(fn($x) => $x);
        $this->assertEquals(5, $result);
    }

    public function testMaxFunctionWithVariables() {
        $expr = MathExpression::parse("max(a,b)");
        $result = $expr->evaluate(fn($x) => ['a' => 2, 'b' => 7][$x] ?? 0);
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
}
