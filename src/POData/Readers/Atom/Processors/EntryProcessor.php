<?php

declare(strict_types=1);


namespace POData\Readers\Atom\Processors;

use POData\Common\ODataConstants;
use POData\ObjectModel\AtomObjectModel\AtomContent;
use POData\ObjectModel\ODataCategory;
use POData\ObjectModel\ODataEntry;
use POData\ObjectModel\ODataLink;
use POData\ObjectModel\ODataMediaLink;
use POData\ObjectModel\ODataTitle;
use POData\Readers\Atom\Processors\Entry\LinkProcessor;
use POData\Readers\Atom\Processors\Entry\PropertyProcessor;

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

    /** @noinspection PhpUnusedParameterInspection */
    public function __construct()
    {
        $this->oDataEntry                   = new ODataEntry();
        $this->oDataEntry->isMediaLinkEntry = false;
    }

    public function handleStartNode($tagNamespace, $tagName, $attributes)
    {
        $this->handleNode(true, $tagNamespace, $tagName, $attributes);
    }
    public function handleEndNode($tagNamespace, $tagName)
    {
        $this->handleNode(false, $tagNamespace, $tagName);
    }

    private function handleNode(bool $start, string $tagNamespace, string $tagName, array $attributes = [])
    {
        $methodType = $start ? 'Start' : 'End';
        if (strtolower($tagNamespace) !== strtolower(ODataConstants::ATOM_NAMESPACE)) {
            $this->subProcessor->{'handle' . $methodType . 'Node'}($tagNamespace, $tagName, $attributes);
            return;
        }
        $method = 'handle' . $methodType . 'Atom' . ucfirst(strtolower($tagName));
        if(!method_exists($this, $method)){
            $this->onParseError('Atom', $methodType, $tagName);
        }
        $this->{$method}($attributes);
    }


    private function doNothing($attributes = [])
    {
        assert(is_array($attributes));
    }

    protected function handleStartAtomId($attributes)
    {
        $this->doNothing($attributes);
    }
    protected function handleEndAtomId()
    {
        $this->oDataEntry->id = $this->popCharData();
    }
    protected function handleStartAtomTitle($attributes)
    {
        $this->titleType = $this->arrayKeyOrDefault(
            $attributes,
            ODataConstants::ATOM_TYPE_ATTRIBUTE_NAME,
            ''
        );
    }
    protected function handleEndAtomTitle()
    {
        $this->oDataEntry->title = new ODataTitle($this->popCharData(), $this->titleType);
        $this->titleType         = null;
    }
    protected function handleStartAtomSummary()
    {
        //TODO: for some reason we do not support this......
        $this->doNothing();
    }

    protected function handleEndAtomSummary()
    {
        //TODO: for some reason we do not support this......
        $this->doNothing();
    }
    protected function handleStartAtomUpdated()
    {
        $this->doNothing();
    }
    protected function handleEndAtomUpdated()
    {
        $this->oDataEntry->updated = $this->popCharData();
    }

    protected function handleStartAtomLink($attributes)
    {
        $this->subProcessor = new LinkProcessor($attributes);
    }

    protected function handleEndAtomLink()
    {
        assert($this->subProcessor instanceof LinkProcessor);
        $this->handleLink($this->subProcessor->getObjetModelObject());
        $this->subProcessor = null;
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
    }
    protected function handleEndAtomCategory()
    {
        assert($this->objectModelSubNode instanceof ODataCategory);
        $this->oDataEntry->setType($this->objectModelSubNode);
    }
    protected function handleStartAtomContent($attributes)
    {
        $this->subProcessor       = new PropertyProcessor($attributes);
        $this->objectModelSubNode = new AtomContent(
            $this->arrayKeyOrDefault($attributes, ODataConstants::ATOM_TYPE_ATTRIBUTE_NAME, 'application/xml')
        );
    }
    protected function handleEndAtomContent()
    {
        assert($this->objectModelSubNode instanceof AtomContent);
        $this->objectModelSubNode->properties = $this->subProcessor->getObjetModelObject();
        $this->oDataEntry->setAtomContent($this->objectModelSubNode);
        $this->subProcessor = null;
    }
    protected function handleStartAtomName($attributes)
    {
        $this->doNothing($attributes);
    }
    protected function handleEndAtomName()
    {
        $this->doNothing();
    }
    protected function handleStartAtomAuthor($attributes)
    {
        $this->doNothing($attributes);
    }
    protected function handleEndAtomAuthor()
    {
        $this->doNothing();
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
    private function handleODataLink(ODataLink $link ){
        if($link->name === ODataConstants::ATOM_EDIT_RELATION_ATTRIBUTE_VALUE){
            $this->oDataEntry->editLink = $link;
        }else{
            $this->oDataEntry->links[] = $link;
        }
    }
    private function handleODataMediaLink(ODataMediaLink $link)
    {
        if($link->name === ODataConstants::ATOM_EDIT_MEDIA_RELATION_ATTRIBUTE_VALUE){
            $this->oDataEntry->mediaLink        = $link;
            $this->oDataEntry->isMediaLinkEntry = true;
        }else{
            $this->oDataEntry->mediaLinks[] = $link;
        }
    }
}
