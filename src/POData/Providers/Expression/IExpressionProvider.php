<?php

declare(strict_types=1);

namespace POData\Providers\Expression;

use POData\Providers\Metadata\ResourceType;
use POData\Providers\Metadata\Type\IType;
use POData\UriProcessor\QueryProcessor\ExpressionParser\Expressions\ExpressionType;
use POData\UriProcessor\QueryProcessor\ExpressionParser\Expressions\PropertyAccessExpression;

/**
 * Class IExpressionProvider.
 */
interface IExpressionProvider
{
    //TODO: does this really need to be part of this interface? see #89

    /**
     * Get the name of the iterator.
     *
     * @return string
     */
    public function getIteratorName();

    /**
     * call-back for setting the resource type.
     *
     * @param ResourceType $resourceType The resource type on which the filter is going to be applied
     *
     * @return void
     */
    public function setResourceType(ResourceType $resourceType);

    /**
     * Call-back for logical expression.
     *
     * @param ExpressionType $expressionType The type of logical expression
     * @param string         $left           The left expression
     * @param string         $right          The left expression
     *
     * @return string
     */
    public function onLogicalExpression(ExpressionType $expressionType, $left, $right);

    /**
     * Call-back for arithmetic expression.
     *
     * @param ExpressionType $expressionType The type of arithmetic expression
     * @param string         $left           The left expression
     * @param string         $right          The left expression
     *
     * @return string
     */
    public function onArithmeticExpression(ExpressionType $expressionType, $left, $right);

    /**
     * Call-back for relational expression.
     *
     * @param ExpressionType $expressionType The type of relation expression
     * @param string         $left           The left expression
     * @param string         $right          The left expression
     *
     * @return string
     */
    public function onRelationalExpression(ExpressionType $expressionType, $left, $right);

    /**
     * Call-back for unary expression.
     *
     * @param ExpressionType $expressionType The type of unary expression
     * @param string         $child          The child expression
     *
     * @return string
     */
    public function onUnaryExpression(ExpressionType $expressionType, $child);

    /**
     * Call-back for constant expression.
     *
     * @param IType  $type  The type of constant
     * @param string $value The value of the constant
     *
     * @return string
     */
    public function onConstantExpression(IType $type, $value);

    /**
     * Call-back for property access expression.
     *
     * @param PropertyAccessExpression $expression The property access expression
     *
     * @return string
     */
    public function onPropertyAccessExpression(PropertyAccessExpression $expression): string;

    /**
     * Call-back for function call expression.
     *
     * @param string        $functionDescription Description of the function
     * @param array<string> $params              Arguments to the functions
     *
     * @return string
     */
    public function onFunctionCallExpression($functionDescription, $params);
}
