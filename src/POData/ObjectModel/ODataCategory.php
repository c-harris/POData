<?php

declare(strict_types=1);

namespace POData\ObjectModel;

/**
 * Class ODataCategory.
 * @package POData\ObjectModel
 */
class ODataCategory
{
    /**
     * Term.
     *
     * @var string
     */
    public $term;

    /**
     * Scheme.
     *
     * @var string
     */
    public $scheme;

    /**
     * ODataCategory constructor.
     *
     * @param        $term
     * @param string $scheme
     */
    public function __construct($term, $scheme = 'http://schemas.microsoft.com/ado/2007/08/dataservices/scheme')
    {
        $this->term   = $term;
        $this->scheme = $scheme;
    }
}
