<?php
namespace Goetas\Xsd\XsdToPhp\Tests\Issues\I63;

use Goetas\Xsd\XsdToPhp\Jms\YamlConverter;
use Goetas\Xsd\XsdToPhp\Naming\ShortNamingStrategy;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class I111Test extends \PHPUnit_Framework_TestCase
{

    public function testNamespace()
    {
        $reader = new SchemaReader();
        $schema = $reader->readFile(__DIR__.'/data.xsd');

        $jmsConv = new YamlConverter(new ShortNamingStrategy());
        $jmsConv->addNamespace('http://www.example.com', 'Tst');
        $jmsConv->addNamespace('http://www.example2.com', 'Tst');

        $phpClasses = $jmsConv->convert([$schema]);
        $type1 = $phpClasses['Tst\ComplexType1Type']['Tst\ComplexType1Type'];

        $propertyElement2 = $type1['properties']['element2'];
        $this->assertEquals('http://www.example2.com', $propertyElement2['xml_element']['namespace']);

        $propertyElement1 = $type1['properties']['element1'];
        $this->assertEquals('http://www.example.com', $propertyElement1['xml_element']['namespace']);
    }
}