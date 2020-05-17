<?php

declare(strict_types=1);

namespace UnitTests\POData\Writers\Json;

use Mockery as m;
use POData\Common\MimeTypes;
use POData\Common\ODataConstants;
use POData\Common\Version;
use POData\ObjectModel\ODataBagContent;
use POData\ObjectModel\ODataEntry;
use POData\ObjectModel\ODataExpandedResult;
use POData\ObjectModel\ODataFeed;
use POData\ObjectModel\ODataLink;
use POData\ObjectModel\ODataMediaLink;
use POData\ObjectModel\ODataProperty;
use POData\ObjectModel\ODataPropertyContent;
use POData\ObjectModel\ODataTitle;
use POData\ObjectModel\ODataURL;
use POData\ObjectModel\ODataURLCollection;
use POData\Providers\ProvidersWrapper;
use POData\Writers\Json\JsonLightMetadataLevel;
use POData\Writers\Json\JsonLightODataWriter;
use UnitTests\POData\TestCase;

class JsonLightODataWriterFullMetadataTest extends TestCase
{
    protected $serviceBase = 'http://services.odata.org/OData/OData.svc';

    public function setUp()
    {
        $this->mockProvider = m::mock(ProvidersWrapper::class)->makePartial();
    }

    public function testWriteURL()
    {
        //NOTE: there's no difference for this between fullmetadata and minimalmetadata

        $this->markTestSkipped("see #80 ODataURL doesn't have enough context to get the meta data return result");

        //IE: http://services.odata.org/v3/OData/OData.svc/Products(0)/$links/Supplier?$format=application/json;odata=fullmetadata

        $oDataUrl      = new ODataURL('http://services.odata.org/OData/OData.svc/Suppliers(0)');
        $writer        = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result        = $writer->write($oDataUrl);
        $this->assertSame($writer, $result);

        //decoding the json string to test, there is no json string comparison in php unit
        $actual = json_decode($writer->getOutput());

        $expected = '{
						"odata.metdata" : "http://services.odata.org/OData/OData.svc/$metadata#Products/$links/Supplier",
						"url": "http://services.odata.org/OData/OData.svc/Suppliers(0)"
					}';
        $expected = json_decode($expected);
        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testWriteURLCollection()
    {
        //NOTE: there's no difference for this between fullmetadata and minimalmetadata

        $this->markTestSkipped("see #80 ODataURL doesn't have enough context to get the meta data return result");
        //see http://services.odata.org/v3/OData/OData.svc/Categories(1)/$links/Products?$format=application/json;odata=fullmetadata

        $oDataUrlCollection       = new ODataURLCollection(
            [
                new ODataURL('http://services.odata.org/OData/OData.svc/Products(0)'),
                new ODataURL('http://services.odata.org/OData/OData.svc/Products(7)'),
                new ODataURL('http://services.odata.org/OData/OData.svc/Products(8)'),
            ],
            null,
            null //simulate no $inlinecount
        );
        $writer                    = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result                    = $writer->write($oDataUrlCollection);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual = json_decode($writer->getOutput());

        $expected = '{
						"odata.metdata" : "http://services.odata.org/OData/OData.svc/$metadata#Products/$links/Products",
		                "value" : [
							{
						        "url": "http://services.odata.org/OData/OData.svc/Products(0)"
							},
						    {
						        "url": "http://services.odata.org/OData/OData.svc/Products(7)"
						    },
						    {
						        "url": "http://services.odata.org/OData/OData.svc/Products(8)"
						    }
						]
					}';

        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());

        $oDataUrlCollection->setCount(44); //simulate an $inlinecount
        $writer                    = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result                    = $writer->write($oDataUrlCollection);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual = json_decode($writer->getOutput());

        $expected = '{
		                "odata.count" : "44",
		                "odata.metdata" : "http://services.odata.org/OData/OData.svc/$metadata#Products/$links/Products",
		                "value" : [
							{
						        "url": "http://services.odata.org/OData/OData.svc/Products(0)"
							},
						    {
						        "url": "http://services.odata.org/OData/OData.svc/Products(7)"
						    },
						    {
						        "url": "http://services.odata.org/OData/OData.svc/Products(8)"
						    }
						]
					}';

        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testWriteFeed()
    {
        //see http://services.odata.org/v3/OData/OData.svc/Categories(0)/Products?$top=2&$format=application/json;odata=fullmetadata

        //entry1
        $entry1           = new ODataEntry();
        $entry1->id       = 'http://services.odata.org/OData/OData.svc/Products(0)';
        $entry1->setSelfLink(new ODataLink('entry1 self link'));
        $entry1->title    = new ODataTitle('title of entry 1');
        $entry1->editLink = 'edit link of entry 1';
        $entry1->type     = 'DataServiceProviderDemo.Product';
        $entry1->eTag     = 'some eTag';

        //entry 1 property content
        $entry1Prop1           = new ODataProperty();
        $entry1Prop1->name     = 'ID';
        $entry1Prop1->typeName = 'Edm.Int16';
        $entry1Prop1->value    = (string) 100;

        $entry1Prop2           = new ODataProperty();
        $entry1Prop2->name     = 'Name';
        $entry1Prop2->typeName = 'Edm.String';
        $entry1Prop2->value    = 'Bread';

        $entry1Prop3           = new ODataProperty();
        $entry1Prop3->name     = 'ReleaseDate';
        $entry1Prop3->typeName = 'Edm.DateTime';
        $entry1Prop3->value    = '2012-09-17T14:17:13';

        $entry1Prop4           = new ODataProperty();
        $entry1Prop4->name     = 'DiscontinuedDate';
        $entry1Prop4->typeName = 'Edm.DateTime';
        $entry1Prop4->value    = null;

        $entry1Prop5           = new ODataProperty();
        $entry1Prop5->name     = 'Price';
        $entry1Prop5->typeName = 'Edm.Decimal';
        $entry1Prop5->value    = 2.5;

        $entry1PropContent             = new ODataPropertyContent();
        $entry1PropContent->properties = [
            $entry1Prop1,
            $entry1Prop2,
            $entry1Prop3,
            $entry1Prop4,
            $entry1Prop5,
        ];
        //entry 1 property content end

        $entry1->propertyContent = $entry1PropContent;

        $entry1->isExpanded       = false;
        $entry1->isMediaLinkEntry = false;

        //entry 1 links NOTE minimalmetadata means this won't be output
        //link1
        $link1        = new ODataLink(
            'http://services.odata.org/OData/OData.svc/Products(0)/Categories',
            'Categories',
            null,
            'http://services.odata.org/OData/OData.svc/Products(0)/Categories'
        );

        $entry1->links = [$link1];
        //entry 1 links end

        //entry 1 end

        $oDataFeed        = new ODataFeed();
        $oDataFeed->id    = 'FEED ID';
        $oDataFeed->title = new ODataTitle('FEED TITLE');
        //self link
        $selfLink            = new ODataLink('Products', 'Products', null, 'Categories(0)/Products');
        $oDataFeed->setSelfLink($selfLink);
        //self link end
        $oDataFeed->entries = [$entry1];

        //next page link: NOTE minimalmetadata means this won't be output
        $nextPageLink            = new ODataLink('Next Page Link', 'Next Page', null, 'http://services.odata.org/OData/OData.svc$skiptoken=12');

        $oDataFeed->nextPageLink = $nextPageLink;
        //feed entries

        //Note that even if the top limits the collection the count should not be output unless inline count is specified
        //IE: http://services.odata.org/v3/OData/OData.svc/Categories?$top=1&$inlinecount=allpages&$format=application/json;odata=fullmetadata
        //The feed count will be null unless inlinecount is specified

        $oDataFeed->rowCount = null;

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($oDataFeed);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual   = json_decode($writer->getOutput());
        $expected = '{
					    "odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#FEED TITLE",
					    "value" : [
				            {
				                "odata.type": "DataServiceProviderDemo.Product",
                                "odata.id": "http://services.odata.org/OData/OData.svc/Products(0)",
                                "odata.etag":"some eTag",
                                "odata.editLink": "edit link of entry 1",
                                "Categories@odata.navigationLinkUrl": "http://services.odata.org/OData/OData.svc/Products(0)/Categories",
				                "ID": 100,
				                "Name": "Bread",
				                "ReleaseDate@odata.type": "Edm.DateTime",
				                "ReleaseDate" : "/Date(1347891433000)/",
				                "DiscontinuedDate" : null,
				                "Price@odata.type": "Edm.Decimal",
				                "Price" : 2.5
				            }
				        ]
					}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());

        //Now we'll simulate an $inlinecount=allpages by specifying a count
        $oDataFeed->rowCount = 33;

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($oDataFeed);
        $this->assertSame($writer, $result);

        //TODO: v3 specifies that the count must be before value..how can we test this well?
        //decoding the json string to test
        $actual   = json_decode($writer->getOutput());
        $expected = '{
						"odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#FEED TITLE",
						"odata.count":"33",
					    "value" : [
				            {
				                "odata.type": "DataServiceProviderDemo.Product",
                                "odata.id": "http://services.odata.org/OData/OData.svc/Products(0)",
                                "odata.etag":"some eTag",
                                "odata.editLink": "edit link of entry 1",
                                "Categories@odata.navigationLinkUrl": "http://services.odata.org/OData/OData.svc/Products(0)/Categories",
				                "ID": 100,
				                "Name": "Bread",
				                "ReleaseDate@odata.type": "Edm.DateTime",
				                "ReleaseDate" : "/Date(1347891433000)/",
				                "DiscontinuedDate" : null,
				                "Price@odata.type": "Edm.Decimal",
				                "Price" : 2.5
				            }
				        ]
					}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testWriteFeedWithEntriesWithComplexProperty()
    {
        //see http://services.odata.org/v3/OData/OData.svc/Suppliers?$top=2&$format=application/json;odata=fullmetadata
        // suppliers have address as a complex property

        //entry1
        $entry1           = new ODataEntry();
        $entry1->id       = 'http://services.odata.org/OData/OData.svc/Suppliers(0)';
        $entry1->setSelfLink(new ODataLink('entry1 self link'));
        $entry1->title    = new ODataTitle('title of entry 1');
        $entry1->editLink = 'edit link of entry 1';
        $entry1->type     = 'ODataDemo.Supplier';
        $entry1->eTag     = 'W/"0"';
        //entry 1 property content
        $entry1PropContent = new ODataPropertyContent();

        $entry1Prop1           = new ODataProperty();
        $entry1Prop1->name     = 'ID';
        $entry1Prop1->typeName = 'Edm.Int16';
        $entry1Prop1->value    = (string) 0;

        $entry1Prop2           = new ODataProperty();
        $entry1Prop2->name     = 'Name';
        $entry1Prop2->typeName = 'Edm.String';
        $entry1Prop2->value    = 'Exotic Liquids';
        //complex type
        $compForEntry1Prop3 = new ODataPropertyContent();

        $compForEntry1Prop3Prop1           = new ODataProperty();
        $compForEntry1Prop3Prop1->name     = 'Street';
        $compForEntry1Prop3Prop1->typeName = 'Edm.String';
        $compForEntry1Prop3Prop1->value    = 'NE 228th';

        $compForEntry1Prop3Prop2           = new ODataProperty();
        $compForEntry1Prop3Prop2->name     = 'City';
        $compForEntry1Prop3Prop2->typeName = 'Edm.String';
        $compForEntry1Prop3Prop2->value    = 'Sammamish';

        $compForEntry1Prop3Prop3           = new ODataProperty();
        $compForEntry1Prop3Prop3->name     = 'State';
        $compForEntry1Prop3Prop3->typeName = 'Edm.String';
        $compForEntry1Prop3Prop3->value    = 'WA';

        $compForEntry1Prop3Prop4           = new ODataProperty();
        $compForEntry1Prop3Prop4->name     = 'ZipCode';
        $compForEntry1Prop3Prop4->typeName = 'Edm.String';
        $compForEntry1Prop3Prop4->value    = '98074';

        $compForEntry1Prop3Prop5           = new ODataProperty();
        $compForEntry1Prop3Prop5->name     = 'Country';
        $compForEntry1Prop3Prop5->typeName = 'Edm.String';
        $compForEntry1Prop3Prop5->value    = 'USA';

        $compForEntry1Prop3->properties = [$compForEntry1Prop3Prop1,
            $compForEntry1Prop3Prop2,
            $compForEntry1Prop3Prop3,
            $compForEntry1Prop3Prop4,
            $compForEntry1Prop3Prop5, ];

        $entry1Prop3           = new ODataProperty();
        $entry1Prop3->name     = 'Address';
        $entry1Prop3->typeName = 'ODataDemo.Address';
        $entry1Prop3->value    = $compForEntry1Prop3;

        $entry1Prop4           = new ODataProperty();
        $entry1Prop4->name     = 'Concurrency';
        $entry1Prop4->typeName = 'Edm.Int16';
        $entry1Prop4->value    = (string) 0;

        $entry1PropContent->properties = [$entry1Prop1, $entry1Prop2, $entry1Prop3, $entry1Prop4];
        //entry 1 property content end

        $entry1->propertyContent = $entry1PropContent;

        $entry1->isExpanded       = false;
        $entry1->isMediaLinkEntry = false;

        //entry 1 links
        //link1
        $link1        = new ODataLink('Products', 'Products', null, 'http://services.odata.org/OData/OData.svc/Suppliers(0)/Products');

        $entry1->links = [$link1];
        //entry 1 links end

        //entry 1 end

        //entry 2
        $entry2           = new ODataEntry();
        $entry2->id       = 'http://services.odata.org/OData/OData.svc/Suppliers(1)';
        $entry2->setSelfLink(new ODataLink('entry2 self link'));
        $entry2->title    = new ODataTitle('title of entry 2');
        $entry2->editLink = 'edit link of entry 2';
        $entry2->type     = 'ODataDemo.Supplier';
        $entry2->eTag     = 'W/"0"';
        //entry 2 property content
        $entry2PropContent = new ODataPropertyContent();

        $entry2Prop1           = new ODataProperty();
        $entry2Prop1->name     = 'ID';
        $entry2Prop1->typeName = 'Edm.Int16';
        $entry2Prop1->value    = 1;

        $entry2Prop2           = new ODataProperty();
        $entry2Prop2->name     = 'Name';
        $entry2Prop2->typeName = 'Edm.String';
        $entry2Prop2->value    = 'Tokyo Traders';
        //complex type
        $compForEntry2Prop3 = new ODataPropertyContent();

        $compForEntry2Prop3Prop1           = new ODataProperty();
        $compForEntry2Prop3Prop1->name     = 'Street';
        $compForEntry2Prop3Prop1->typeName = 'Edm.String';
        $compForEntry2Prop3Prop1->value    = 'NE 40th';

        $compForEntry2Prop3Prop2           = new ODataProperty();
        $compForEntry2Prop3Prop2->name     = 'City';
        $compForEntry2Prop3Prop2->typeName = 'Edm.String';
        $compForEntry2Prop3Prop2->value    = 'Redmond';

        $compForEntry2Prop3Prop3           = new ODataProperty();
        $compForEntry2Prop3Prop3->name     = 'State';
        $compForEntry2Prop3Prop3->typeName = 'Edm.String';
        $compForEntry2Prop3Prop3->value    = 'WA';

        $compForEntry2Prop3Prop4           = new ODataProperty();
        $compForEntry2Prop3Prop4->name     = 'ZipCode';
        $compForEntry2Prop3Prop4->typeName = 'Edm.String';
        $compForEntry2Prop3Prop4->value    = '98052';

        $compForEntry2Prop3Prop5           = new ODataProperty();
        $compForEntry2Prop3Prop5->name     = 'Country';
        $compForEntry2Prop3Prop5->typeName = 'Edm.String';
        $compForEntry2Prop3Prop5->value    = 'USA';

        $compForEntry2Prop3->properties = [$compForEntry2Prop3Prop1,
            $compForEntry2Prop3Prop2,
            $compForEntry2Prop3Prop3,
            $compForEntry2Prop3Prop4,
            $compForEntry2Prop3Prop5, ];

        $entry2Prop3           = new ODataProperty();
        $entry2Prop3->name     = 'Address';
        $entry2Prop3->typeName = 'ODataDemo.Address';
        $entry2Prop3->value    = $compForEntry2Prop3;

        $entry2Prop4           = new ODataProperty();
        $entry2Prop4->name     = 'Concurrency';
        $entry2Prop4->typeName = 'Edm.Int16';
        $entry2Prop4->value    = (string) 0;

        $entry2PropContent->properties = [$entry2Prop1, $entry2Prop2, $entry2Prop3, $entry2Prop4];
        //entry 2 property content end

        $entry2->propertyContent = $entry2PropContent;

        $entry2->isExpanded       = false;
        $entry2->isMediaLinkEntry = false;

        //entry 2 links
        //link1
        $link1        = new ODataLink(
            'Products',
            'Products',
            null,
            'http://services.odata.org/OData/OData.svc/Suppliers(1)/Products'
        );

        $entry2->links = [$link1];
        //entry 2 links end

        //entry 2 end

        $oDataFeed        = new ODataFeed();
        $oDataFeed->id    = 'FEED ID';
        $oDataFeed->title = new ODataTitle('FEED TITLE');
        //self link
        $selfLink            = new ODataLink(
            'Products',
            'Products',
            null,
            'Categories(0)/Products'
        );
        $oDataFeed->setSelfLink($selfLink);
        //self link end

        //next page
        $nextPageLink            = new ODataLink('Next Page Link', 'Next Page', null, 'http://services.odata.org/OData/OData.svc$skiptoken=12');
        $oDataFeed->nextPageLink = $nextPageLink;
        //feed entries

        $oDataFeed->entries = [$entry1, $entry2];

        $oDataFeed->rowCount = null; //simulate no inline count

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($oDataFeed);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual   = json_decode($writer->getOutput());
        $expected = '{
						"odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#FEED TITLE",
					    "value": [
							{
								"odata.type": "ODataDemo.Supplier",
                                "odata.id": "http://services.odata.org/OData/OData.svc/Suppliers(0)",
                                "odata.etag":"W/\"0\"",
                                "odata.editLink": "edit link of entry 1",
                                "Products@odata.navigationLinkUrl": "http://services.odata.org/OData/OData.svc/Suppliers(0)/Products",
                                "ID": 0,
								"Name": "Exotic Liquids",
								"Address": {
									"odata.type": "ODataDemo.Address",
									"Street": "NE 228th",
									 "City": "Sammamish",
									 "State": "WA",
									 "ZipCode": "98074",
									 "Country": "USA"
								},
								"Concurrency": 0
							},
							{
								"odata.type": "ODataDemo.Supplier",
                                "odata.id": "http://services.odata.org/OData/OData.svc/Suppliers(1)",
                                "odata.etag":"W/\"0\"",
                                "odata.editLink": "edit link of entry 2",
                                "Products@odata.navigationLinkUrl": "http://services.odata.org/OData/OData.svc/Suppliers(1)/Products",
                                "ID": 1,
								"Name": "Tokyo Traders",
								"Address": {
									"odata.type": "ODataDemo.Address",
									"Street": "NE 40th",
									"City": "Redmond",
									"State": "WA",
									"ZipCode": "98052",
									"Country": "USA"
								},
								"Concurrency": 0
							}
						]
					}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());

        $oDataFeed->rowCount = 55; //simulate  $inlinecount=allpages

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($oDataFeed);
        $this->assertSame($writer, $result);

        //TODO: spec says count must be before! need to verify positioning in the test somehow
        //decoding the json string to test
        $actual   = json_decode($writer->getOutput());
        $expected = '{
					    "odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#FEED TITLE",
					    "odata.count":"55",
					    "value": [
							{
								"odata.type": "ODataDemo.Supplier",
                                "odata.id": "http://services.odata.org/OData/OData.svc/Suppliers(0)",
                                "odata.etag":"W/\"0\"",
                                "odata.editLink": "edit link of entry 1",
                                "Products@odata.navigationLinkUrl": "http://services.odata.org/OData/OData.svc/Suppliers(0)/Products",
                                "ID": 0,
								"Name": "Exotic Liquids",
								"Address": {
									"odata.type": "ODataDemo.Address",
									"Street": "NE 228th",
									 "City": "Sammamish",
									 "State": "WA",
									 "ZipCode": "98074",
									 "Country": "USA"
								},
								"Concurrency": 0
							},
							{
								"odata.type": "ODataDemo.Supplier",
                                "odata.id": "http://services.odata.org/OData/OData.svc/Suppliers(1)",
                                "odata.etag":"W/\"0\"",
                                "odata.editLink": "edit link of entry 2",
                                "Products@odata.navigationLinkUrl": "http://services.odata.org/OData/OData.svc/Suppliers(1)/Products",
                                "ID": 1,
								"Name": "Tokyo Traders",
								"Address": {
									"odata.type": "ODataDemo.Address",
									"Street": "NE 40th",
									"City": "Redmond",
									"State": "WA",
									"ZipCode": "98052",
									"Country": "USA"
								},
								"Concurrency": 0
							}
						]
					}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testWriteEntry()
    {
        //see http://services.odata.org/v3/OData/OData.svc/Suppliers(0)?$format=application/json;odata=fullmetadata

        //entry
        $entry                  = new ODataEntry();
        $entry->id              = 'http://services.odata.org/OData/OData.svc/Categories(0)';
        $entry->setSelfLink(new ODataLink('entry2 self link'));
        $entry->title           = new ODataTitle('title of entry 2');
        $entry->editLink        = 'edit link of entry 2';
        $entry->type            = 'ODataDemo.Category';
        $entry->eTag            = '';
        $entry->resourceSetName = 'resource set name';

        $entryPropContent = new ODataPropertyContent();
        //entry property
        $entryProp1           = new ODataProperty();
        $entryProp1->name     = 'ID';
        $entryProp1->typeName = 'Edm.Int16';
        $entryProp1->value    = (string) 0;

        $entryProp2           = new ODataProperty();
        $entryProp2->name     = 'Name';
        $entryProp2->typeName = 'Edm.String';
        $entryProp2->value    = 'Food';

        $entryPropContent->properties = [$entryProp1, $entryProp2];

        $entry->propertyContent = $entryPropContent;

        //links
        $link        = new ODataLink(
            'Products',
            'Products',
            null,
            'http://services.odata.org/OData/OData.svc/Categories(0)/Products'
        );
        $entry->links = [$link];

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($entry);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual = json_decode($writer->getOutput());

        $expected = '{
						"odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#resource set name/@Element",
						"odata.type": "ODataDemo.Category",
                        "odata.id": "http://services.odata.org/OData/OData.svc/Categories(0)",
                        "odata.etag":"",
                        "odata.editLink": "edit link of entry 2",
                        "Products@odata.navigationLinkUrl": "http://services.odata.org/OData/OData.svc/Categories(0)/Products",
						"ID": 0,
						"Name": "Food"
					}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testWriteComplexProperty()
    {
        //see http://services.odata.org/v3/OData/OData.svc/Suppliers(0)/Address?$format=application/json;odata=fullmetadata

        $propContent = new ODataPropertyContent();

        //property
        $compProp = new ODataPropertyContent();

        $compProp1           = new ODataProperty();
        $compProp1->name     = 'Street';
        $compProp1->typeName = 'Edm.String';
        $compProp1->value    = 'NE 228th';

        $compProp2           = new ODataProperty();
        $compProp2->name     = 'City';
        $compProp2->typeName = 'Edm.String';
        $compProp2->value    = 'Sammamish';

        $compProp3           = new ODataProperty();
        $compProp3->name     = 'State';
        $compProp3->typeName = 'Edm.String';
        $compProp3->value    = 'WA';

        $compProp4           = new ODataProperty();
        $compProp4->name     = 'ZipCode';
        $compProp4->typeName = 'Edm.String';
        $compProp4->value    = '98074';

        $compProp5           = new ODataProperty();
        $compProp5->name     = 'Country';
        $compProp5->typeName = 'Edm.String';
        $compProp5->value    = 'USA';

        $compProp->properties = [$compProp1,
            $compProp2,
            $compProp3,
            $compProp4,
            $compProp5, ];

        $prop1           = new ODataProperty();
        $prop1->name     = 'Address';
        $prop1->typeName = 'ODataDemo.Address';
        $prop1->value    = $compProp;

        $propContent->properties = [$prop1];

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($propContent);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual = json_decode($writer->getOutput());

        $expected = '{
						"odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#ODataDemo.Address",
						"odata.type": "ODataDemo.Address",
						"Street": "NE 228th",
						"City": "Sammamish",
						"State": "WA",
						"ZipCode": "98074",
						"Country": "USA"
					}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testEntryWithBagProperty()
    {
        //Intro to bags: http://www.odata.org/2010/09/adding-support-for-bags/
        //TODO: bags were renamed to collection in v3 see https://github.com/balihoo/POData/issues/79
        //see http://docs.oasis-open.org/odata/odata-json-format/v4.0/cs01/odata-json-format-v4.0-cs01.html#_Toc365464701
        //can't find a Collection type in online demo

        //entry
        $entry                  = new ODataEntry();
        $entry->id              = 'http://host/service.svc/Customers(1)';
        $entry->setSelfLink(new ODataLink('entry1 self link'));
        $entry->title           = new ODataTitle('title of entry 1');
        $entry->editLink        = 'edit link of entry 1';
        $entry->type            = 'SampleModel.Customer';
        $entry->eTag            = 'some eTag';
        $entry->resourceSetName = 'resource set name';

        $entryPropContent = new ODataPropertyContent();
        //entry property
        $entryProp1           = new ODataProperty();
        $entryProp1->name     = 'ID';
        $entryProp1->typeName = 'Edm.Int16';
        $entryProp1->value    = 1;

        $entryProp2           = new ODataProperty();
        $entryProp2->name     = 'Name';
        $entryProp2->typeName = 'Edm.String';
        $entryProp2->value    = 'mike';

        //property 3 starts
        //bag property for property 3
        $bagEntryProp3 = new ODataBagContent();

        $bagEntryProp3->setPropertyContents([
            'mike@foo.com',
            'mike2@foo.com', ]);
        $bagEntryProp3->setType('Bag(Edm.String)'); //TODO: this might not be what really happens in the code..#61

        $entryProp3           = new ODataProperty();
        $entryProp3->name     = 'EmailAddresses';
        $entryProp3->typeName = 'Bag(Edm.String)';
        $entryProp3->value    = $bagEntryProp3;
        //property 3 ends

        //property 4 starts
        $bagEntryProp4 = new ODataBagContent();

        //property content for bagEntryProp4ContentProp1
        $bagEntryProp4ContentProp1Content = new ODataPropertyContent();

        $bagEntryProp4ContentProp1ContentProp1           = new ODataProperty();
        $bagEntryProp4ContentProp1ContentProp1->name     = 'Street';
        $bagEntryProp4ContentProp1ContentProp1->typeName = 'Edm.String';
        $bagEntryProp4ContentProp1ContentProp1->value    = '123 contoso street';

        $bagEntryProp4ContentProp1ContentProp2           = new ODataProperty();
        $bagEntryProp4ContentProp1ContentProp2->name     = 'Apartment';
        $bagEntryProp4ContentProp1ContentProp2->typeName = 'Edm.String';
        $bagEntryProp4ContentProp1ContentProp2->value    = '508';

        $bagEntryProp4ContentProp1Content->properties = [$bagEntryProp4ContentProp1ContentProp1,
            $bagEntryProp4ContentProp1ContentProp2, ];

        //end property content for bagEntryProp4ContentProp1

        //property content2 for bagEntryProp4ContentProp1
        $bagEntryProp4ContentProp1Content2 = new ODataPropertyContent();

        $bagEntryProp4ContentProp1Content2Prop1           = new ODataProperty();
        $bagEntryProp4ContentProp1Content2Prop1->name     = 'Street';
        $bagEntryProp4ContentProp1Content2Prop1->typeName = 'Edm.String';
        $bagEntryProp4ContentProp1Content2Prop1->value    = '834 foo street';

        $bagEntryProp4ContentProp1Content2Prop2           = new ODataProperty();
        $bagEntryProp4ContentProp1Content2Prop2->name     = 'Apartment';
        $bagEntryProp4ContentProp1Content2Prop2->typeName = 'Edm.String';
        $bagEntryProp4ContentProp1Content2Prop2->value    = '102';

        $bagEntryProp4ContentProp1Content2->properties = [$bagEntryProp4ContentProp1Content2Prop1,
            $bagEntryProp4ContentProp1Content2Prop2, ];

        //end property content for bagEntryProp4ContentProp1

        $bagEntryProp4->setPropertyContents([$bagEntryProp4ContentProp1Content,
            $bagEntryProp4ContentProp1Content2,
        ]);
        $bagEntryProp4->setType('Bag(SampleModel.Address)'); //TODO: this might not be what really happens in the code..#61

        $entryProp4           = new ODataProperty();
        $entryProp4->name     = 'Addresses';
        $entryProp4->typeName = 'Bag(SampleModel.Address)';
        $entryProp4->value    = $bagEntryProp4;
        //property 4 ends

        $entryPropContent->properties = [$entryProp1, $entryProp2, $entryProp3, $entryProp4];

        $entry->propertyContent = $entryPropContent;

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($entry);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual = json_decode($writer->getOutput());

        $expected = '{
						"odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#resource set name/@Element",
						"odata.type": "SampleModel.Customer",
                        "odata.id": "http://host/service.svc/Customers(1)",
                        "odata.etag":"some eTag",
                        "odata.editLink": "edit link of entry 1",
                        "ID": 1,
						"Name": "mike",
						"EmailAddresses": [
							"mike@foo.com", "mike2@foo.com"
				        ],
			            "Addresses": [
		                    {
		                        "Street": "123 contoso street",
		                        "Apartment": "508"
		                    },
		                    {
		                        "Street": "834 foo street",
		                        "Apartment": "102"
		                    }
		                ]
					}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testPrimitiveProperty()
    {
        //NOTE: there is no different between minimalmetadata and fullmetadata for primitive properties

        //see http://services.odata.org/v3/OData/OData.svc/Suppliers(0)/Address/City?$format=application/json;odata=fullmetadata
        $property           = new ODataProperty();
        $property->name     = 'Count';
        $property->typeName = 'Edm.Int16';
        $property->value    = 56;

        $content             = new ODataPropertyContent();
        $content->properties = [$property];

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($content);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual = json_decode($writer->getOutput());

        $expected = '{
	                    "odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#Edm.Int16",
	                    "value" :  56
	                 }';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testWriteEntryWithExpandedEntry()
    {
        //First build up the expanded entry
        $expandedEntry           = new ODataEntry();
        $expandedEntry->id       = 'Expanded Entry 1';
        $expandedEntry->title    = new ODataTitle('Expanded Entry Title');
        $expandedEntry->type     = 'Expanded.Type';
        $expandedEntry->editLink = 'Edit Link URL';
        $expandedEntry->setSelfLink(new ODataLink('Self Link URL'));

        $expandedEntry->mediaLinks = [
            new ODataMediaLink(
                'Media Link Name',
                'Edit Media link',
                'Src Media Link',
                'Media Content Type',
                'Media ETag'
            ),
            new ODataMediaLink(
                'Media Link Name2',
                'Edit Media link2',
                'Src Media Link2',
                'Media Content Type2',
                'Media ETag2'
            ),
        ];

        $expandedEntry->links            = [];
        $expandedEntry->eTag             = 'Entry ETag';
        $expandedEntry->isMediaLinkEntry = false;

        $pr1           = new ODataProperty();
        $pr1->name     = 'fname';
        $pr1->typeName = 'string';
        $pr1->value    = 'Yash';

        $pr2           = new ODataProperty();
        $pr2->name     = 'lname';
        $pr2->typeName = 'string';
        $pr2->value    = 'Kothari';

        $propCon1             = new ODataPropertyContent();
        $propCon1->properties = [$pr1, $pr2];

        $expandedEntryComplexProperty           = new ODataProperty();
        $expandedEntryComplexProperty->name     = 'Expanded Entry Complex Property';
        $expandedEntryComplexProperty->typeName = 'Full Name';
        $expandedEntryComplexProperty->value    = $propCon1;

        $expandedEntryProperty1           = new ODataProperty();
        $expandedEntryProperty1->name     = 'Expanded Entry City Property';
        $expandedEntryProperty1->typeName = 'string';
        $expandedEntryProperty1->value    = 'Ahmedabad';

        $expandedEntryProperty2           = new ODataProperty();
        $expandedEntryProperty2->name     = 'Expanded Entry State Property';
        $expandedEntryProperty2->typeName = 'string';
        $expandedEntryProperty2->value    = 'Gujarat';

        $expandedEntry->propertyContent             = new ODataPropertyContent();
        $expandedEntry->propertyContent->properties = [
            $expandedEntryComplexProperty,
            $expandedEntryProperty1,
            $expandedEntryProperty2,
        ];
        //End the expanded entry

        //build up the main entry

        $entry             = new ODataEntry();
        $entry->id         = 'Main Entry';
        $entry->title      = new ODataTitle('Entry Title');
        $entry->type       = 'Main.Type';
        $entry->editLink   = 'Edit Link URL';
        $entry->setSelfLink(new ODataLink('Self Link URL'));
        $entry->mediaLinks = [
            new ODataMediaLink(
                'Media Link Name',
                'Edit Media link',
                'Src Media Link',
                'Media Content Type',
                'Media ETag'
            ),
            new ODataMediaLink(
                'Media Link Name2',
                'Edit Media link2',
                'Src Media Link2',
                'Media Content Type2',
                'Media ETag2'
            ),
        ];

        $entry->eTag             = 'Entry ETag';
        $entry->isMediaLinkEntry = false;

        $entryProperty1           = new ODataProperty();
        $entryProperty1->name     = 'Main Entry Property 1';
        $entryProperty1->typeName = 'string';
        $entryProperty1->value    = 'Yash';

        $entryProperty2           = new ODataProperty();
        $entryProperty2->name     = 'Main Entry Property 2';
        $entryProperty2->typeName = 'string';
        $entryProperty2->value    = 'Kothari';

        $entry->propertyContent             = new ODataPropertyContent();
        $entry->propertyContent->properties = [$entryProperty1, $entryProperty2];
        //End of main entry

        //Now link the expanded entry to the main entry
        $expandLink                 = new ODataLink(
            null,
            'Expanded Property',
            null,
            'ExpandedURL',
            false,
            new ODataExpandedResult($expandedEntry),
            true
        );
        $entry->links               = [$expandLink];

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($entry);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual = json_decode($writer->getOutput());

        $expected = '{
	"odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#/@Element",
	"odata.type":"Main.Type",
	"odata.id":"Main Entry",
	"odata.etag":"Entry ETag",
	"odata.editLink":"Edit Link URL",
	"Expanded Property@odata.navigationLinkUrl":"ExpandedURL",
    "Expanded Property":{
        "odata.type":"Expanded.Type",
        "odata.id":"Expanded Entry 1",
        "odata.etag":"Entry ETag",
        "odata.editLink":"Edit Link URL",
        "Expanded Entry Complex Property":{
            "odata.type":"Full Name",
            "fname":"Yash",
            "lname":"Kothari"
        },
        "Expanded Entry City Property":"Ahmedabad",
        "Expanded Entry State Property":"Gujarat"
    },
    "Main Entry Property 1":"Yash",
    "Main Entry Property 2":"Kothari"
}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testWriteEntryWithExpandedEntryThatIsNull()
    {

        //build up the main entry

        $entry             = new ODataEntry();
        $entry->id         = 'Main Entry';
        $entry->title      = new ODataTitle('Entry Title');
        $entry->type       = 'Main.Type';
        $entry->editLink   = 'Edit Link URL';
        $entry->setSelfLink(new ODataLink('Self Link URL'));
        $entry->mediaLinks = [
            new ODataMediaLink(
                'Media Link Name',
                'Edit Media link',
                'Src Media Link',
                'Media Content Type',
                'Media ETag'
            ),
            new ODataMediaLink(
                'Media Link Name2',
                'Edit Media link2',
                'Src Media Link2',
                'Media Content Type2',
                'Media ETag2'
            ),
        ];

        $entry->eTag             = 'Entry ETag';
        $entry->isMediaLinkEntry = false;

        $entryProperty1           = new ODataProperty();
        $entryProperty1->name     = 'Main Entry Property 1';
        $entryProperty1->typeName = 'string';
        $entryProperty1->value    = 'Yash';

        $entryProperty2           = new ODataProperty();
        $entryProperty2->name     = 'Main Entry Property 2';
        $entryProperty2->typeName = 'string';
        $entryProperty2->value    = 'Kothari';

        $entry->propertyContent             = new ODataPropertyContent();
        $entry->propertyContent->properties = [$entryProperty1, $entryProperty2];
        //End of main entry

        //Now link the expanded entry to the main entry
        $expandLink                 = new ODataLink(
            null,
            'Expanded Property',
            null,
            'ExpandedURL',
            false,
            null, //<--key part
            true
        );
        $entry->links               = [$expandLink];

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($entry);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual = json_decode($writer->getOutput());

        $expected = '{
	"odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#/@Element",
	"odata.type":"Main.Type",
	"odata.id":"Main Entry",
	"odata.etag":"Entry ETag",
	"odata.editLink":"Edit Link URL",
	"Expanded Property@odata.navigationLinkUrl":"ExpandedURL",
    "Expanded Property":null,
    "Main Entry Property 1":"Yash",
    "Main Entry Property 2":"Kothari"
}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    public function testWriteEntryWithExpandedFeed()
    {
        //First build up the expanded entry 1
        $expandedEntry1           = new ODataEntry();
        $expandedEntry1->id       = 'Expanded Entry 1';
        $expandedEntry1->title    = new ODataTitle('Expanded Entry 1 Title');
        $expandedEntry1->type     = 'Expanded.Type';
        $expandedEntry1->editLink = 'Edit Link URL';
        $expandedEntry1->setSelfLink(new ODataLink('Self Link URL'));

        $expandedEntry1->mediaLinks = [
            new ODataMediaLink(
                'Media Link Name',
                'Edit Media link',
                'Src Media Link',
                'Media Content Type',
                'Media ETag'
            ),
            new ODataMediaLink(
                'Media Link Name2',
                'Edit Media link2',
                'Src Media Link2',
                'Media Content Type2',
                'Media ETag2'
            ),
        ];

        $expandedEntry1->links            = [];
        $expandedEntry1->eTag             = 'Entry ETag';
        $expandedEntry1->isMediaLinkEntry = false;

        $pr1           = new ODataProperty();
        $pr1->name     = 'first';
        $pr1->typeName = 'string';
        $pr1->value    = 'Entry 1 Name First';

        $pr2           = new ODataProperty();
        $pr2->name     = 'last';
        $pr2->typeName = 'string';
        $pr2->value    = 'Entry 1 Name Last';

        $expandedEntry1ComplexProperty                    = new ODataProperty();
        $expandedEntry1ComplexProperty->name              = 'Expanded Entry Complex Property';
        $expandedEntry1ComplexProperty->typeName          = 'Full Name';
        $expandedEntry1ComplexProperty->value             = new ODataPropertyContent();
        $expandedEntry1ComplexProperty->value->properties = [$pr1, $pr2];

        $expandedEntry1Property1           = new ODataProperty();
        $expandedEntry1Property1->name     = 'Expanded Entry City Property';
        $expandedEntry1Property1->typeName = 'string';
        $expandedEntry1Property1->value    = 'Entry 1 City Value';

        $expandedEntry1Property2           = new ODataProperty();
        $expandedEntry1Property2->name     = 'Expanded Entry State Property';
        $expandedEntry1Property2->typeName = 'string';
        $expandedEntry1Property2->value    = 'Entry 1 State Value';

        $expandedEntry1->propertyContent             = new ODataPropertyContent();
        $expandedEntry1->propertyContent->properties = [
            $expandedEntry1ComplexProperty,
            $expandedEntry1Property1,
            $expandedEntry1Property2,
        ];
        //End the expanded entry 1

        //First build up the expanded entry 2
        $expandedEntry2           = new ODataEntry();
        $expandedEntry2->id       = 'Expanded Entry 2';
        $expandedEntry2->title    = new ODataTitle('Expanded Entry 2 Title');
        $expandedEntry2->type     = 'Expanded.Type';
        $expandedEntry2->editLink = 'Edit Link URL';
        $expandedEntry2->setSelfLink(new ODataLink('Self Link URL'));

        $expandedEntry2->mediaLinks = [
            new ODataMediaLink(
                'Media Link Name',
                'Edit Media link',
                'Src Media Link',
                'Media Content Type',
                'Media ETag'
            ),
            new ODataMediaLink(
                'Media Link Name2',
                'Edit Media link2',
                'Src Media Link2',
                'Media Content Type2',
                'Media ETag2'
            ),
        ];

        $expandedEntry2->links            = [];
        $expandedEntry2->eTag             = 'Entry ETag';
        $expandedEntry2->isMediaLinkEntry = false;

        $pr1           = new ODataProperty();
        $pr1->name     = 'first';
        $pr1->typeName = 'string';
        $pr1->value    = 'Entry 2 Name First';

        $pr2           = new ODataProperty();
        $pr2->name     = 'last';
        $pr2->typeName = 'string';
        $pr2->value    = 'Entry 2 Name Last';

        $expandedEntry2ComplexProperty                    = new ODataProperty();
        $expandedEntry2ComplexProperty->name              = 'Expanded Entry Complex Property';
        $expandedEntry2ComplexProperty->typeName          = 'Full Name';
        $expandedEntry2ComplexProperty->value             = new ODataPropertyContent();
        $expandedEntry2ComplexProperty->value->properties = [$pr1, $pr2];

        $expandedEntry2Property1           = new ODataProperty();
        $expandedEntry2Property1->name     = 'Expanded Entry City Property';
        $expandedEntry2Property1->typeName = 'string';
        $expandedEntry2Property1->value    = 'Entry 2 City Value';

        $expandedEntry2Property2           = new ODataProperty();
        $expandedEntry2Property2->name     = 'Expanded Entry State Property';
        $expandedEntry2Property2->typeName = 'string';
        $expandedEntry2Property2->value    = 'Entry 2 State Value';

        $expandedEntry2->propertyContent             = new ODataPropertyContent();
        $expandedEntry2->propertyContent->properties = [
            $expandedEntry2ComplexProperty,
            $expandedEntry2Property1,
            $expandedEntry2Property2,
        ];
        //End the expanded entry 2

        //build up the main entry

        $entry             = new ODataEntry();
        $entry->id         = 'Main Entry';
        $entry->title      = new ODataTitle('Entry Title');
        $entry->type       = 'Main.Type';
        $entry->editLink   = 'Edit Link URL';
        $entry->setSelfLink(new ODataLink('Self Link URL'));
        $entry->mediaLinks = [
            new ODataMediaLink(
                'Media Link Name',
                'Edit Media link',
                'Src Media Link',
                'Media Content Type',
                'Media ETag'
            ),
            new ODataMediaLink(
                'Media Link Name2',
                'Edit Media link2',
                'Src Media Link2',
                'Media Content Type2',
                'Media ETag2'
            ),
        ];

        $entry->eTag             = 'Entry ETag';
        $entry->isMediaLinkEntry = false;

        $entryProperty1           = new ODataProperty();
        $entryProperty1->name     = 'Main Entry Property 1';
        $entryProperty1->typeName = 'string';
        $entryProperty1->value    = 'Yash';

        $entryProperty2           = new ODataProperty();
        $entryProperty2->name     = 'Main Entry Property 2';
        $entryProperty2->typeName = 'string';
        $entryProperty2->value    = 'Kothari';

        $entry->propertyContent             = new ODataPropertyContent();
        $entry->propertyContent->properties = [$entryProperty1, $entryProperty2];
        //End of main entry

        //Create a the expanded feed
        $expandedFeed          = new ODataFeed();
        $expandedFeed->id      = 'expanded feed id';
        $expandedFeed->title   = new ODataTitle('SubCollection');
        $expandedFeed->entries = [$expandedEntry1, $expandedEntry2];

        $expandedFeedSelfLink        = new ODataLink('self', 'SubCollection', null, 'SubCollection Self URL');

        $expandedFeed->setSelfLink($expandedFeedSelfLink);

        //Now link the expanded entry to the main entry
        $expandLink                 = new ODataLink(
            null,
            'SubCollection',
            null,
            'SubCollectionURL',
            true,
            new ODataExpandedResult($expandedFeed),
            true
        );
        $entry->links               = [$expandLink];

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $result = $writer->write($entry);
        $this->assertSame($writer, $result);

        //decoding the json string to test
        $actual = json_decode($writer->getOutput());

        $expected = '{
	"odata.metadata":"http://services.odata.org/OData/OData.svc/$metadata#/@Element",
	"odata.type":"Main.Type",
	"odata.id":"Main Entry",
	"odata.etag":"Entry ETag",
	"odata.editLink":"Edit Link URL",
	"SubCollection@odata.navigationLinkUrl" : "SubCollectionURL",
	"SubCollection" : [
	    {
	        "odata.type":"Expanded.Type",
            "odata.id":"Expanded Entry 1",
            "odata.etag":"Entry ETag",
            "odata.editLink":"Edit Link URL",
	        "Expanded Entry Complex Property":{
	            "odata.type" : "Full Name",
	            "first":"Entry 1 Name First",
	            "last":"Entry 1 Name Last"
	        },
	        "Expanded Entry City Property":"Entry 1 City Value",
	        "Expanded Entry State Property":"Entry 1 State Value"
	    },
	    {
	        "odata.type":"Expanded.Type",
            "odata.id":"Expanded Entry 2",
            "odata.etag":"Entry ETag",
            "odata.editLink":"Edit Link URL",
            "Expanded Entry Complex Property":{
                "odata.type" : "Full Name",
	            "first":"Entry 2 Name First",
	            "last":"Entry 2 Name Last"
	        },
	        "Expanded Entry City Property":"Entry 2 City Value",
	        "Expanded Entry State Property":"Entry 2 State Value"
	    }
	],
    "Main Entry Property 1":"Yash",
    "Main Entry Property 2":"Kothari"
}';
        $expected = json_decode($expected);

        $this->assertEquals([$expected], [$actual], 'raw JSON is: ' . $writer->getOutput());
    }

    /**
     * @var ProvidersWrapper
     */
    protected $mockProvider;

    public function testGetOutputNoResourceSets()
    {
        $this->mockProvider->shouldReceive('getResourceSets')->andReturn([]);
        $this->mockProvider->shouldReceive('getSingletons')->andReturn([]);

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $actual = $writer->writeServiceDocument($this->mockProvider)->getOutput();

        $expected = "{\n    \"d\":{\n        \"EntitySet\":[\n\n        ]\n    }\n}";

        $this->assertJsonStringEqualsJsonString($expected, $actual);
    }

    public function testGetOutputTwoResourceSets()
    {
        $fakeResourceSet1 = m::mock('POData\Providers\Metadata\ResourceSetWrapper');
        $fakeResourceSet1->shouldReceive('getName')->andReturn('Name 1');

        $fakeResourceSet2 = m::mock('POData\Providers\Metadata\ResourceSetWrapper');
        //TODO: this certainly doesn't seem right...see #73
        $fakeResourceSet2->shouldReceive('getName')->andReturn("XML escaped stuff \" ' <> & ?");

        $fakeResourceSets = [
            $fakeResourceSet1,
            $fakeResourceSet2,
        ];

        $this->mockProvider->shouldReceive('getResourceSets')->andReturn($fakeResourceSets);
        $this->mockProvider->shouldReceive('getSingletons')->andReturn([]);

        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);
        $actual = $writer->writeServiceDocument($this->mockProvider)->getOutput();

        $expected = "{\n    \"d\":{\n        \"EntitySet\":[\n            \"Name 1\",\"XML escaped stuff \\\" ' <> & ?\"\n        ]\n    }\n}";

        $this->assertJsonStringEqualsJsonString($expected, $actual);
    }

    /**
     * @dataProvider canHandleProvider
     * @param mixed $id
     * @param mixed $version
     * @param mixed $contentType
     * @param mixed $expected
     */
    public function testCanHandle($id, $version, $contentType, $expected)
    {
        $writer = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), $this->serviceBase);

        $actual = $writer->canHandle($version, $contentType);

        $this->assertEquals($expected, $actual, strval($id));
    }

    public function canHandleProvider()
    {
        return [
            [100, Version::v1(), MimeTypes::MIME_APPLICATION_ATOMSERVICE, false],
            [101, Version::v2(), MimeTypes::MIME_APPLICATION_ATOMSERVICE, false],
            [102, Version::v3(), MimeTypes::MIME_APPLICATION_ATOMSERVICE, false],

            [200, Version::v1(), MimeTypes::MIME_APPLICATION_JSON, false],
            [201, Version::v2(), MimeTypes::MIME_APPLICATION_JSON, false],
            [202, Version::v3(), MimeTypes::MIME_APPLICATION_JSON, false],

            //TODO: is this first one right?  this should NEVER come up, but should we claim to handle this format when
            //it's invalid for V1? Ditto first of the next sections
            [300, Version::v1(), MimeTypes::MIME_APPLICATION_JSON_MINIMAL_META, false],
            [301, Version::v2(), MimeTypes::MIME_APPLICATION_JSON_MINIMAL_META, false],
            [302, Version::v3(), MimeTypes::MIME_APPLICATION_JSON_MINIMAL_META, false],

            [400, Version::v1(), MimeTypes::MIME_APPLICATION_JSON_NO_META, false],
            [401, Version::v2(), MimeTypes::MIME_APPLICATION_JSON_NO_META, false],
            [402, Version::v3(), MimeTypes::MIME_APPLICATION_JSON_NO_META, false],

            [500, Version::v1(), MimeTypes::MIME_APPLICATION_JSON_FULL_META, false],
            [501, Version::v2(), MimeTypes::MIME_APPLICATION_JSON_FULL_META, false],
            [502, Version::v3(), MimeTypes::MIME_APPLICATION_JSON_FULL_META, true],

            [600, Version::v1(), MimeTypes::MIME_APPLICATION_JSON_VERBOSE, false], //this one seems especially wrong
            [601, Version::v2(), MimeTypes::MIME_APPLICATION_JSON_VERBOSE, false],
            [602, Version::v3(), MimeTypes::MIME_APPLICATION_JSON_VERBOSE, false],
        ];
    }

    public function testConstructorWithBadServiceUri()
    {
        $level      = JsonLightMetadataLevel::FULL();
        $serviceUri = '';

        $expected = 'absoluteServiceUri must not be empty or null';
        $actual   = null;

        try {
            new JsonLightODataWriter(PHP_EOL, true, $level, $serviceUri);
        } catch (\Exception $e) {
            $actual = $e->getMessage();
        }
        $this->assertNotNull($actual);
        $this->assertEquals($expected, $actual);
    }

    public function testWritePropertyContentWithFirstPropertyHavingNullValue()
    {
        $level      = JsonLightMetadataLevel::FULL();
        $serviceUri = 'http://localhost/odata.svc';

        $foo = new JsonLightODataWriter(PHP_EOL, true, $level, $serviceUri);

        $property           = new ODataProperty();
        $property->value    = null;
        $property->typeName = 'Edm.String';

        $model               = new ODataPropertyContent();
        $model->properties[] = $property;

        $expected = '{' . PHP_EOL;
        $expected .= '    "odata.metadata":"http://localhost/odata.svc/$metadata#Edm.String","value":null' . PHP_EOL;
        $expected .= '}';
        $foo->write($model);
        $actual   = $foo->getOutput();
        $expected = preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $expected);
        $actual   = preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $actual);
        $this->assertJsonStringEqualsJsonString($expected, $actual);
    }

    public function testWritePropertyContentWithFirstPropertyHavingBagValue()
    {
        $level      = JsonLightMetadataLevel::FULL();
        $serviceUri = 'http://localhost/odata.svc';

        $foo = new JsonLightODataWriter(PHP_EOL, true, $level, $serviceUri);

        $bag                   = new ODataBagContent();
        $bag->setPropertyContents([]);

        $property           = new ODataProperty();
        $property->value    = $bag;
        $property->typeName = 'Edm.String';

        $model               = new ODataPropertyContent();
        $model->properties[] = $property;

        $expected = '{' . PHP_EOL;
        $expected .= '    "odata.metadata":"http://localhost/odata.svc/$metadata#Edm.String","value":[' . PHP_EOL;
        $expected .= PHP_EOL . '    ]' . PHP_EOL;
        $expected .= '}';
        $foo->write($model);
        $actual   = $foo->getOutput();
        $expected = preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $expected);
        $actual   = preg_replace('~(*BSR_ANYCRLF)\R~', "\r\n", $actual);
        $this->assertJsonStringEqualsJsonString($expected, $actual);
    }

    public function testWriteEmptyODataEntry()
    {
        $entry                  = new ODataEntry();
        $entry->resourceSetName = 'Foobars';

        $foo = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), 'http://localhost/odata.svc');

        $actual   = $foo->write($entry)->getOutput();
        $expected = '{' . PHP_EOL . '    "odata.metadata":"http://localhost/odata.svc/$metadata#Foobars/@Element"'
                    . ',"odata.type":"","odata.id":"","odata.etag":"","odata.editLink":""' . PHP_EOL . '}';
        $this->assertJsonStringEqualsJsonString($actual, $expected);
    }

    public function testWriteEmptyODataFeed()
    {
        $feed                  = new ODataFeed();
        $feed->id              = 'http://localhost/odata.svc/feedID';
        $feed->title           = 'title';
        $feed->setSelfLink(new ODataLink(
            ODataConstants::ATOM_SELF_RELATION_ATTRIBUTE_VALUE,
            'Feed Title',
            null,
            'feedID'
        ));

        $foo      = new JsonLightODataWriter(PHP_EOL, true, JsonLightMetadataLevel::FULL(), 'http://localhost/odata.svc');
        $expected = '{' . PHP_EOL
                    . '    "odata.metadata":"http://localhost/odata.svc/$metadata#title","value":['
                    . PHP_EOL . PHP_EOL . '    ]' . PHP_EOL . '}';
        $actual = $foo->write($feed)->getOutput();
        $this->assertTrue(false !== strpos($actual, $expected));
    }
}
