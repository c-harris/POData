<?php

declare(strict_types=1);


namespace POData\Readers\Atom\Processors;

use Closure;
use POData\Common\ODataConstants;
use POData\ObjectModel\AtomObjectModel\AtomContent;
use POData\ObjectModel\ODataCategory;
use POData\ObjectModel\ODataEntry;
use POData\ObjectModel\ODataLink;
use POData\ObjectModel\ODataMediaLink;
use POData\ObjectModel\ODataTitle;
use POData\Readers\Atom\Processors\Entry\LinkProcessor;
use POData\Readers\Atom\Processors\Entry\PropertyProcessor;
use SplStack;

class EntryProcessor extends BaseNodeHandler
{
    private $oDataEntry;
    private $titleType;

    /**
     * @var AtomContent|ODataCategory
     */
    private $objectModelSubNode;

    /**
     * @var LinkProcessor|PropertyProcessor $subProcessor
     */
    private $subProcessor;

    /**
     * @var SplStack|callable
     */
    private $tagEndQueue;

    /** @noinspection PhpUnusedParameterInspection */
    public function __construct()
    {
        $this->oDataEntry                   = new ODataEntry();
        $this->oDataEntry->isMediaLinkEntry = false;
        $this->tagEndQueue                  = new SplStack();
    }

    public function handleStartNode($tagNamespace, $tagName, $attributes)
    {
        if (strtolower($tagNamespace) !== strtolower(ODataConstants::ATOM_NAMESPACE)) {
            $this->subProcessor->handleStartNode($tagNamespace, $tagName, $attributes);
            return;
        }
        $method = 'handleStartAtom' . ucfirst(strtolower($tagName));
        if (!method_exists($this, $method)) {
            $this->onParseError('Atom', 'start', $tagName);
        }
        $this->{$method}($attributes);
    }
    public function handleEndNode($tagNamespace, $tagName)
    {
        if (strtolower($tagNamespace) !== strtolower(ODataConstants::ATOM_NAMESPACE)) {
            $this->subProcessor->handleEndNode($tagNamespace, $tagName);
            return;
        }
        assert(!$this->tagEndQueue->isEmpty(), 'every node that opens should register a end tag');
        $endMethod = $this->tagEndQueue->pop();
        $endMethod();
    }

    private function doNothing()
    {
        return function () {};
    }

    private function bindHere(Closure $closure)
    {
        return $closure->bindTo($this, get_class($this));
    }
    private function enqueueEnd(Closure $closure)
    {
        $this->tagEndQueue->push($this->bindHere($closure));
    }
    protected function handleStartAtomId()
    {
        $this->enqueueEnd(function () {
            $this->oDataEntry->id = $this->popCharData();
        });
    }
    protected function handleStartAtomTitle($attributes)
    {
        $this->titleType = $this->arrayKeyOrDefault(
            $attributes,
            ODataConstants::ATOM_TYPE_ATTRIBUTE_NAME,
            ''
        );
        $this->enqueueEnd(function () {
            $this->oDataEntry->title = new ODataTitle($this->popCharData(), $this->titleType);
            $this->titleType         = null;
        });
    }
    protected function handleStartAtomSummary()
    {
        //TODO: for some reason we do not support this......
        $this->enqueueEnd($this->doNothing());
    }
    protected function handleStartAtomUpdated()
    {
        $this->enqueueEnd(function () {
            $this->oDataEntry->updated = $this->popCharData();
        });
    }
    protected function handleStartAtomLink($attributes)
    {
        $this->subProcessor = new LinkProcessor($attributes);
        $this->enqueueEnd(function () {
            assert($this->subProcessor instanceof LinkProcessor);
            $this->handleLink($this->subProcessor->getObjetModelObject());
            $this->subProcessor = null;
        });
    }
    protected function handleStartAtomCategory($attributes)
    {
        $this->objectModelSubNode = new ODataCategory(
            $this->arrayKeyOrDefault($attributes, ODataConstants::ATOM_CATEGORY_TERM_ATTRIBUTE_NAME, ''),
            $this->arrayKeyOrDefault(
                $attributes,
                ODataConstants::ATOM_CATEGORY_SCHEME_ATTRIBUTE_NAME,
                'http://schemas.microsoft.com/ado/2007/08/dataservices/scheme'
            )
        );
        $this->enqueueEnd(function () {
            assert($this->objectModelSubNode instanceof ODataCategory);
            $this->oDataEntry->setType($this->objectModelSubNode);
        });
    }
    protected function handleStartAtomContent($attributes)
    {
        $this->subProcessor       = new PropertyProcessor($attributes);
        $this->objectModelSubNode = new AtomContent(
            $this->arrayKeyOrDefault($attributes, ODataConstants::ATOM_TYPE_ATTRIBUTE_NAME, 'application/xml')
        );
        $this->enqueueEnd(function () {
            assert($this->objectModelSubNode instanceof AtomContent);
            $this->objectModelSubNode->properties = $this->subProcessor->getObjetModelObject();
            $this->oDataEntry->setAtomContent($this->objectModelSubNode);
            $this->subProcessor = null;
        });
    }
    protected function handleStartAtomName()
    {
        $this->enqueueEnd($this->doNothing());
    }
    protected function handleStartAtomAuthor()
    {
        $this->enqueueEnd($this->doNothing());
    }




    public function handleChildComplete($objectModel)
    {
        $this->subProcessor->handleChildComplete($objectModel);
    }

    public function getObjetModelObject()
    {
        return $this->oDataEntry;
    }

    public function handleCharacterData($characters)
    {
        if (null === $this->subProcessor) {
            parent::handleCharacterData($characters);
        } else {
            $this->subProcessor->handleCharacterData($characters);
        }
    }

    /**
     * @param ODataLink|ODataMediaLink $link
     */
    private function handleLink($link)
    {
        switch (true) {
            case $link instanceof ODataMediaLink:
                $this->handleODataMediaLink($link);
                break;
            case $link instanceof ODataLink:
                $this->handleODataLink($link);
                break;
        }
    }
    private function handleODataLink(ODataLink $link)
    {
        if ($link->name === ODataConstants::ATOM_EDIT_RELATION_ATTRIBUTE_VALUE) {
            $this->oDataEntry->editLink = $link;
        } else {
            $this->oDataEntry->links[] = $link;
        }
    }
    private function handleODataMediaLink(ODataMediaLink $link)
    {
        if ($link->name === ODataConstants::ATOM_EDIT_MEDIA_RELATION_ATTRIBUTE_VALUE) {
            $this->oDataEntry->mediaLink        = $link;
            $this->oDataEntry->isMediaLinkEntry = true;
        } else {
            $this->oDataEntry->mediaLinks[] = $link;
        }
    }
}
