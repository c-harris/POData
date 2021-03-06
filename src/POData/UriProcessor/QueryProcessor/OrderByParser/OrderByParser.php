<?php

declare(strict_types=1);

namespace POData\UriProcessor\QueryProcessor\OrderByParser;

use POData\Common\InvalidOperationException;
use POData\Common\Messages;
use POData\Common\ODataException;
use POData\Common\ReflectionHandler;
use POData\Providers\Metadata\ResourceEntityType;
use POData\Providers\Metadata\ResourceProperty;
use POData\Providers\Metadata\ResourcePropertyKind;
use POData\Providers\Metadata\ResourceSetWrapper;
use POData\Providers\Metadata\ResourceType;
use POData\Providers\Metadata\Type\Binary;
use POData\Providers\ProvidersWrapper;
use POData\UriProcessor\QueryProcessor\ExpressionParser\ExpressionLexer;
use POData\UriProcessor\QueryProcessor\ExpressionParser\ExpressionTokenId;
use ReflectionClass;
use ReflectionException;

/**
 * Class OrderByParser.
 *
 * Class to parse $orderby query option and perform syntax validation
 * and build 'OrderBy Tree' along with next level of validation, the
 * created tree is used for building sort functions and 'OrderByInfo' structure.
 *
 * The syntax of orderby clause is:
 *
 * OrderByClause         : OrderByPathSegment [, OrderByPathSegment]*
 * OrderByPathSegment    : OrderBySubPathSegment[/OrderBySubPathSegment]*[asc|desc]?
 * OrderBySubPathSegment : identifier
 */
class OrderByParser
{
    /**
     * Collection of anonymous sorter function corresponding to each orderby path segment.
     *
     * @var callable[]
     */
    private $comparisonFunctions = [];

    /**
     * The top level sorter function generated from orderby path segments.
     *
     * @var callable
     */
    private $topLevelComparisonFunction;

    /**
     * The structure holds information about the navigation properties
     * used in the orderby clause (if any), and orderby path if IDSQP
     * implementor want to perform sorting.
     *
     * @var OrderByInfo
     */
    private $orderByInfo;

    /**
     * Reference to metadata and query provider wrapper.
     *
     * @var ProvidersWrapper
     */
    private $providerWrapper;

    /**
     * This object will be of type of the resource set identified by the request uri.
     *
     * @var mixed
     */
    private $dummyObject;

    /**
     * Root node for tree ordering.
     *
     * @var OrderByNode
     */
    private $rootOrderByNode;

    /**
     * Creates new instance of OrderByParser.
     *
     * @param ProvidersWrapper $providerWrapper Reference to metadata
     *                                          and query provider
     *                                          wrapper
     */
    private function __construct(ProvidersWrapper $providerWrapper)
    {
        $this->providerWrapper = $providerWrapper;
    }

    /**
     * This function perform the following tasks with the help of internal helper
     * functions
     * (1) Read the orderby clause and perform basic syntax checks
     * (2) Build 'Order By Tree', creates anonymous sorter function for each leaf node and check for error
     * (3) Build 'OrderInfo' structure, holds information about the navigation
     *     properties used in the orderby clause (if any) and orderby path if
     *     IDSQP implementor want to perform sorting
     * (4) Build top level anonymous sorter function
     * (4) Release resources hold by the 'Order By Tree'
     * (5) Create 'InternalOrderInfo' structure, which wraps 'OrderInfo' and top
     *     level sorter function.
     *
     * @param ResourceSetWrapper $resourceSetWrapper ResourceSetWrapper for the resource targeted by resource path
     * @param ResourceType       $resourceType       ResourceType for the resource targeted by resource path
     * @param string             $orderBy            The orderby clause
     * @param ProvidersWrapper   $providerWrapper    Reference to the wrapper for IDSQP and IDSMP impl
     *
     * @throws InvalidOperationException
     * @throws ReflectionException
     * @throws ODataException            If any error occur while parsing orderby clause
     * @return InternalOrderByInfo
     */
    public static function parseOrderByClause(
        ResourceSetWrapper $resourceSetWrapper,
        ResourceType $resourceType,
        $orderBy,
        ProvidersWrapper $providerWrapper
    ) {
        assert(is_string($orderBy), 'OrderBy clause must be a string');
        $orderBy = trim($orderBy);
        if (0 == strlen($orderBy)) {
            throw new InvalidOperationException('OrderBy clause must not be trimmable to an empty string');
        }
        $orderByParser = new self($providerWrapper);
        try {
            $instance = $resourceType->getInstanceType();
            assert($instance instanceof ReflectionClass, get_class($instance));
            $orderByParser->dummyObject = $instance->newInstanceArgs([]);
        } catch (ReflectionException $reflectionException) {
            throw ODataException::createInternalServerError(Messages::orderByParserFailedToCreateDummyObject());
        }
        $orderByParser->rootOrderByNode = new OrderByRootNode($resourceSetWrapper, $resourceType);
        $orderByPathSegments            = $orderByParser->readOrderBy($orderBy);

        $orderByParser->buildOrderByTree($orderByPathSegments);
        $orderByParser->createOrderInfo($orderByPathSegments);
        $orderByParser->generateTopLevelComparisonFunction();
        //Recursively release the resources
        $orderByParser->rootOrderByNode->free();
        //creates internal order info wrapper
        $internalOrderInfo = new InternalOrderByInfo(
            $orderByParser->orderByInfo,
            $orderByParser->comparisonFunctions,
            $orderByParser->topLevelComparisonFunction,
            $orderByParser->dummyObject,
            $resourceType
        );
        unset($orderByParser->orderByInfo);
        unset($orderByParser->topLevelComparisonFunction);

        return $internalOrderInfo;
    }

    /**
     * Read orderby clause.
     *
     * @param string $value orderby clause to read
     *
     * @throws ODataException If any syntax error found while reading the clause
     * @return array<array>   An array of 'OrderByPathSegment's, each of which
     *                        is array of 'OrderBySubPathSegment's
     */
    private function readOrderBy($value)
    {
        $orderByPathSegments = [];
        $lexer               = new ExpressionLexer($value);
        $i                   = 0;
        while ($lexer->getCurrentToken()->getId() != ExpressionTokenId::END()) {
            $orderBySubPathSegment = $lexer->readDottedIdentifier();
            if (!array_key_exists($i, $orderByPathSegments)) {
                $orderByPathSegments[$i] = [];
            }

            $orderByPathSegments[$i][] = $orderBySubPathSegment;
            $tokenId                   = $lexer->getCurrentToken()->getId();
            if ($tokenId != ExpressionTokenId::END()) {
                if ($tokenId != ExpressionTokenId::SLASH()) {
                    if ($tokenId != ExpressionTokenId::COMMA()) {
                        $lexer->validateToken(ExpressionTokenId::IDENTIFIER());
                        $identifier = $lexer->getCurrentToken()->Text;
                        if ($identifier !== 'asc' && $identifier !== 'desc') {
                            // force lexer to throw syntax error as we found
                            // unexpected identifier
                            $lexer->validateToken(ExpressionTokenId::DOT());
                        }

                        $orderByPathSegments[$i][] = '*' . $identifier;
                        $lexer->nextToken();
                        $tokenId = $lexer->getCurrentToken()->getId();
                        if ($tokenId != ExpressionTokenId::END()) {
                            $lexer->validateToken(ExpressionTokenId::COMMA());
                            ++$i;
                        }
                    } else {
                        ++$i;
                    }
                }

                $lexer->nextToken();
            }
        }

        return $orderByPathSegments;
    }

    /**
     * Build 'OrderBy Tree' from the given orderby path segments, also build
     * comparison function for each path segment.
     *
     * @param array(array) &$orderByPathSegments Collection of orderby path segments,
     *                                           this is passed by reference
     *                                           since we need this function to
     *                                           modify this array in two cases:
     *                                           1. if asc or desc present, then the
     *                                           corresponding sub path segment
     *                                           should be removed
     *                                           2. remove duplicate orderby path
     *                                           segment
     *
     * @throws ReflectionException
     * @throws ODataException      If any error occurs while processing the orderby path segments
     * @return mixed
     */
    private function buildOrderByTree(&$orderByPathSegments)
    {
        foreach ($orderByPathSegments as $index1 => &$orderBySubPathSegments) {
            /** @var OrderByNode $currentNode */
            $currentNode   = $this->rootOrderByNode;
            $currentObject = $this->dummyObject;
            $ascending     = true;
            $subPathCount  = count($orderBySubPathSegments);
            // Check sort order is specified in the path, if so set a
            // flag and remove that segment
            if ($subPathCount > 1) {
                if ('*desc' === $orderBySubPathSegments[$subPathCount - 1]) {
                    $ascending = false;
                    unset($orderBySubPathSegments[$subPathCount - 1]);
                    --$subPathCount;
                } elseif ('*asc' === $orderBySubPathSegments[$subPathCount - 1]) {
                    unset($orderBySubPathSegments[$subPathCount - 1]);
                    --$subPathCount;
                }
            }

            $ancestors = [$this->rootOrderByNode->getResourceSetWrapper()->getName()];
            foreach ($orderBySubPathSegments as $index2 => $orderBySubPathSegment) {
                $isLastSegment      = ($index2 == $subPathCount - 1);
                $resourceSetWrapper = null;
                /** @var ResourceEntityType $resourceType */
                $resourceType = $currentNode->getResourceType();
                /** @var ResourceProperty $resourceProperty */
                $resourceProperty = $resourceType->resolveProperty($orderBySubPathSegment);
                if (null === $resourceProperty) {
                    throw ODataException::createSyntaxError(
                        Messages::orderByParserPropertyNotFound(
                            $resourceType->getFullName(),
                            $orderBySubPathSegment
                        )
                    );
                }
                /** @var ResourcePropertyKind $rKind */
                $rKind   = $resourceProperty->getKind();
                $rawKind = ($rKind instanceof ResourcePropertyKind) ? $rKind->getValue() : $rKind;

                if ($resourceProperty->isKindOf(ResourcePropertyKind::BAG())) {
                    throw ODataException::createBadRequestError(
                        Messages::orderByParserBagPropertyNotAllowed(
                            $resourceProperty->getName()
                        )
                    );
                } elseif ($resourceProperty->isKindOf(ResourcePropertyKind::PRIMITIVE())) {
                    if (!$isLastSegment) {
                        throw ODataException::createBadRequestError(
                            Messages::orderByParserPrimitiveAsIntermediateSegment(
                                $resourceProperty->getName()
                            )
                        );
                    }

                    $type = $resourceProperty->getInstanceType();
                    if ($type instanceof Binary) {
                        throw ODataException::createBadRequestError(
                            Messages::orderByParserSortByBinaryPropertyNotAllowed($resourceProperty->getName())
                        );
                    }
                } elseif ($rawKind == ResourcePropertyKind::RESOURCESET_REFERENCE
                    || $rawKind == ResourcePropertyKind::RESOURCE_REFERENCE
                ) {
                    $this->assertion($currentNode instanceof OrderByRootNode || $currentNode instanceof OrderByNode);
                    $resourceSetWrapper = $currentNode->getResourceSetWrapper();
                    $this->assertion(null !== $resourceSetWrapper);
                    $resourceSetWrapper
                        = $this->providerWrapper->getResourceSetWrapperForNavigationProperty(
                            $resourceSetWrapper,
                            $resourceType,
                            $resourceProperty
                        );
                    if (null === $resourceSetWrapper) {
                        throw ODataException::createBadRequestError(
                            Messages::badRequestInvalidPropertyNameSpecified(
                                $resourceType->getFullName(),
                                $orderBySubPathSegment
                            )
                        );
                    }

                    if ($rKind == ResourcePropertyKind::RESOURCESET_REFERENCE()) {
                        throw ODataException::createBadRequestError(
                            Messages::orderByParserResourceSetReferenceNotAllowed(
                                $resourceProperty->getName(),
                                $resourceType->getFullName()
                            )
                        );
                    }

                    $resourceSetWrapper->checkResourceSetRightsForRead(true);
                    if ($isLastSegment) {
                        throw ODataException::createBadRequestError(
                            Messages::orderByParserSortByNavigationPropertyIsNotAllowed(
                                $resourceProperty->getName()
                            )
                        );
                    }

                    $ancestors[] = $orderBySubPathSegment;
                } elseif ($resourceProperty->isKindOf(ResourcePropertyKind::COMPLEX_TYPE())) {
                    if ($isLastSegment) {
                        throw ODataException::createBadRequestError(
                            Messages::orderByParserSortByComplexPropertyIsNotAllowed(
                                $resourceProperty->getName()
                            )
                        );
                    }

                    $ancestors[] = $orderBySubPathSegment;
                } else {
                    throw ODataException::createInternalServerError(
                        Messages::orderByParserUnexpectedPropertyType()
                    );
                }

                $node = $currentNode->findNode($orderBySubPathSegment);
                if (null === $node) {
                    if ($resourceProperty->isKindOf(ResourcePropertyKind::PRIMITIVE())) {
                        $node = new OrderByLeafNode(
                            $orderBySubPathSegment,
                            $resourceProperty,
                            $ascending
                        );
                        $this->comparisonFunctions[] = $node->buildComparisonFunction($ancestors);
                    } elseif ($resourceProperty->getKind() == ResourcePropertyKind::RESOURCE_REFERENCE()) {
                        $node = new OrderByNode(
                            $orderBySubPathSegment,
                            $resourceProperty,
                            $resourceSetWrapper
                        );
                        // Initialize this member variable (identified by
                        // $resourceProperty) of parent object.
                        try {
                            $instance = $resourceProperty->getInstanceType();
                            assert($instance instanceof ReflectionClass, get_class($instance));
                            $object = $instance->newInstanceArgs();
                            $resourceType->setPropertyValue($currentObject, $resourceProperty->getName(), $object);
                            $currentObject = $object;
                        } catch (ReflectionException $reflectionException) {
                            $this->throwBadAccessOrInitException($resourceProperty, $resourceType);
                        }
                    } elseif ($resourceProperty->getKind() == ResourcePropertyKind::COMPLEX_TYPE()) {
                        $node = new OrderByNode($orderBySubPathSegment, $resourceProperty, null);
                        // Initialize this member variable
                        // (identified by $resourceProperty)of parent object.
                        try {
                            $instance = $resourceProperty->getInstanceType();
                            assert($instance instanceof ReflectionClass, get_class($instance));
                            $object = $instance->newInstanceArgs();
                            $resourceType->setPropertyValue($currentObject, $resourceProperty->getName(), $object);
                            $currentObject = $object;
                        } catch (ReflectionException $reflectionException) {
                            $this->throwBadAccessOrInitException($resourceProperty, $resourceType);
                        }
                    }

                    $currentNode->addNode($node);
                } else {
                    try {
                        $currentObject = ReflectionHandler::getProperty($currentObject, $resourceProperty->getName());
                    } catch (ReflectionException $reflectionException) {
                        $this->throwBadAccessOrInitException($resourceProperty, $resourceType);
                    }

                    if ($node instanceof OrderByLeafNode) {
                        //remove duplicate orderby path
                        unset($orderByPathSegments[$index1]);
                    }
                }

                $currentNode = $node;
            }
        }
        return null;
    }

    /**
     * Assert that the given condition is true, if false throw ODataException for unexpected state.
     *
     * @param bool $condition The condition to assert
     *
     * @throws ODataException
     */
    private function assertion($condition)
    {
        if (!$condition) {
            throw ODataException::createInternalServerError(Messages::orderByParserUnExpectedState());
        }
    }

    /**
     * @param  ResourceProperty $resourceProperty
     * @param  ResourceType     $resourceType
     * @throws ODataException
     */
    private function throwBadAccessOrInitException(
        ResourceProperty $resourceProperty,
        ResourceType $resourceType
    ): void {
        throw ODataException::createInternalServerError(
            Messages::orderByParserFailedToAccessOrInitializeProperty(
                $resourceProperty->getName(),
                $resourceType->getName()
            )
        );
    }

    /**
     * Traverse 'Order By Tree' and create 'OrderInfo' structure.
     *
     * @param array<array> $orderByPaths The orderby paths
     *
     * @throws ODataException If parser finds an inconsistent-tree state, throws unexpected state error
     * @return void
     */
    private function createOrderInfo(array $orderByPaths): void
    {
        $orderByPathSegments           = [];
        $navigationPropertiesInThePath = [];
        foreach ($orderByPaths as $index => $orderBySubPaths) {
            /** @var OrderByNode $currentNode */
            $currentNode            = $this->rootOrderByNode;
            $orderBySubPathSegments = [];
            foreach ($orderBySubPaths as $orderBySubPath) {
                /** @var OrderByNode $node */
                $node = $currentNode->findNode($orderBySubPath);
                $this->assertion(null !== $node);
                $resourceProperty = $node->getResourceProperty();
                if ($node instanceof OrderByNode && null !== $node->getResourceSetWrapper()) {
                    if (!array_key_exists($index, $navigationPropertiesInThePath)) {
                        $navigationPropertiesInThePath[$index] = [];
                    }

                    $navigationPropertiesInThePath[$index][] = $resourceProperty;
                }

                $orderBySubPathSegments[] = new OrderBySubPathSegment($resourceProperty);
                /** @var OrderByLeafNode $currentNode */
                $currentNode = $node;
            }

            $this->assertion($currentNode instanceof OrderByLeafNode);
            /** @var OrderByLeafNode $newNode */
            $newNode               = $currentNode;
            $orderByPathSegments[] = new OrderByPathSegment($orderBySubPathSegments, $newNode->isAscending());
            unset($orderBySubPathSegments);
        }

        $this->orderByInfo = new OrderByInfo(
            $orderByPathSegments,
            empty($navigationPropertiesInThePath) ? null : $navigationPropertiesInThePath
        );
    }

    /**
     * Generates top level comparison function from sub comparison functions.
     * @throws ODataException
     */
    private function generateTopLevelComparisonFunction()
    {
        $comparisonFunctionCount = count($this->comparisonFunctions);
        $this->assertion(0 < $comparisonFunctionCount);
        if (1 == $comparisonFunctionCount) {
            $this->topLevelComparisonFunction = $this->comparisonFunctions[0];
        } else {
            $funcList                         = $this->comparisonFunctions;
            $this->topLevelComparisonFunction = function ($object1, $object2) use ($funcList) {
                $ret = 0;
                foreach ($funcList as $f) {
                    $ret = $f($object1, $object2);
                    if (0 != $ret) {
                        return $ret;
                    }
                }
                return $ret;
            };
        }
    }
}
