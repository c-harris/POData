<?php

namespace ODataProducer\UriProcessor\QueryProcessor\OrderByParser;


use ODataProducer\Providers\Metadata\ResourceType;
use ODataProducer\Providers\Metadata\ResourceSetWrapper;

/**
 * Class OrderByRootNode
 *
 * A type to represent root node of 'OrderBy Tree', the root node includes
 * details of resource set pointed by the request resource path uri.
 *
 * @package ODataProducer\UriProcessor\QueryProcessor\OrderByParser
 */
class OrderByRootNode extends OrderByNode
{
    /**
     * The resource type resource set pointed by the request resource
     * path uri.
     * 
     * @var ResourceType
     */
    private $_baseResourceType;

    /**
     * Constructs a new instance of 'OrderByRootNode' representing 
     * root of 'OrderBy Tree'
     * 
     * @param ResourceSetWrapper $resourceSetWrapper The resource set pointed by 
     *                                               the request resource path uri.
     * @param ResourceType       $baseResourceType   The resource type resource set
     *                                               pointed by the request resource
     *                                               path uri.
     */
    public function __construct(ResourceSetWrapper $resourceSetWrapper, 
        ResourceType $baseResourceType
    ) {
        parent::__construct(null, null, $resourceSetWrapper);
        $this->_baseResourceType = $baseResourceType;
    }

    /**
     * (non-PHPdoc)
     * 
     * @see library/ODataProducer/QueryProcessor/OrderByParser/ODataProducer\QueryProcessor\OrderByParser.OrderByNode::getResourceType()
     * 
     * @return ResourceType
     */
    public function getResourceType()
    {
        return $this->_baseResourceType;
    }
}