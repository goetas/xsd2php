<?php
namespace Goetas\Xsd\XsdToPhp\Tests\Converter\PHP;

class Xsd2PhpGroupTest extends Xsd2PhpBase
{

    public function testAutoDiscoveryTraits()
    {
        $content = '
             <xs:schema targetNamespace="http://www.example.com" xmlns:xs="http://www.w3.org/2001/XMLSchema">
                <xs:group name="element-1">

                </xs:group>

                <xs:attributeGroup name="element-2"/>
               <xs:attribute name="element-3" type="xs:string"/>
               </xs:schema>
            ';
        $classes = $this->getClasses($content);
        $this->assertCount(0, $classes);
    }

    public function testGroupArray()
    {
        $content = '
             <xs:schema targetNamespace="http://www.example.com" xmlns:xs="http://www.w3.org/2001/XMLSchema">
                <xs:group name="EG_ExtensionList">
                    <xs:sequence>
                      <xs:element name="ext" type="xs:string" minOccurs="0" maxOccurs="unbounded"/>
                    </xs:sequence>
                </xs:group>
                <xs:complexType name="complexType-1">
                    <xs:sequence>
                        <xs:group ref="EG_ExtensionList"/>
                    </xs:sequence>
                </xs:complexType>
               </xs:schema>
            ';
        $classes = $this->getClasses($content);

        $this->assertCount(1, $classes);
        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $classes['Example\ComplexType1Type']);
    }

    public function testSomeAnonymous()
    {
        $content = '
             <xs:schema targetNamespace="http://www.example.com" xmlns:xs="http://www.w3.org/2001/XMLSchema"  xmlns:ex="http://www.example.com">
                    <xs:complexType name="complexType-1">
                        <xs:sequence>
                            <xs:element name="string1">
                                <xs:simpleType>
                                    <xs:restriction base="xs:string"></xs:restriction>
                                </xs:simpleType>
                            </xs:element>
                            <xs:element name="string2">
                                <xs:complexType>
                                    <xs:sequence>
                                        <xs:element name="string3" type="xs:string"/>
                                    </xs:sequence>
                                </xs:complexType>
                            </xs:element>
                        </xs:sequence>
                        <xs:attribute name="att">
                            <xs:simpleType>
                                <xs:restriction base="xs:string"></xs:restriction>
                            </xs:simpleType>
                        </xs:attribute>
                    </xs:complexType>
            </xs:schema>
            ';
        $classes = $this->getClasses($content);
        $this->assertCount(2, $classes);

        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $complexType1 = $classes['Example\ComplexType1Type']);
        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $s2 = $classes['Example\ComplexType1Type\String2AType']);

        $s1Prop = $complexType1->getProperty('string1');
        $this->assertSame('Example\ComplexType1Type\String1AType', $s1Prop->getType()->getFullName());

        $s2Prop = $complexType1->getProperty('string2');
        $this->assertSame($s2, $s2Prop->getType());

        $a1Prop = $complexType1->getProperty('att');
        $this->assertSame('Example\ComplexType1Type\AttAType', $a1Prop->getType()->getFullName());

    }

    public function testSomeInheritance()
    {
        $content = '
             <xs:schema targetNamespace="http://www.example.com" xmlns:xs="http://www.w3.org/2001/XMLSchema"  xmlns:ex="http://www.example.com">
                <xs:complexType name="complexType-1">
                     <xs:attribute name="attribute-2" type="xs:string"/>
                     <xs:sequence>
                            <xs:element name="complexType-1-el-1" type="xs:string"/>
                     </xs:sequence>
                </xs:complexType>
                <xs:complexType name="complexType-2">
                     <xs:complexContent>
                        <xs:extension base="ex:complexType-1">
                             <xs:sequence>
                                <xs:element name="complexType-2-el1" type="xs:string"></xs:element>
                            </xs:sequence>
                            <xs:attribute name="complexType-2-att1" type="xs:string"></xs:attribute>
                        </xs:extension>
                    </xs:complexContent>
                </xs:complexType>
            </xs:schema>
            ';
        $classes = $this->getClasses($content);
        $this->assertCount(2, $classes);

        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $complexType1 = $classes['Example\ComplexType1Type']);
        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $complexType2 = $classes['Example\ComplexType2Type']);

        $this->assertSame($complexType1, $complexType2->getExtends());

        $property = $complexType2->getProperty('complexType2Att1');
        $this->assertEquals('complexType2Att1', $property->getName());
        $this->assertEquals('', $property->getType()->getNamespace());
        $this->assertEquals('string', $property->getType()->getName());

        $property = $complexType2->getProperty('complexType2El1');
        $this->assertEquals('complexType2El1', $property->getName());
        $this->assertEquals('', $property->getType()->getNamespace());
        $this->assertEquals('string', $property->getType()->getName());

    }

    public function getMaxOccurs()
    {
        return [
            [null, false],
            ['1', false],
            /*
            ['2', true],
            ['3', true],
            ['10', true],
            ['unbounded', true]
            */
        ];
    }

    public function testArray()
    {
        $content = '
             <xs:schema targetNamespace="http://www.example.com" xmlns:xs="http://www.w3.org/2001/XMLSchema"  xmlns:ex="http://www.example.com">
                   <xs:complexType name="complexType-1">
                        <xs:sequence>
                            <xs:element name="strings" type="ex:ArrayOfStrings"></xs:element>
                        </xs:sequence>
                    </xs:complexType>

                    <xs:complexType name="ArrayOfStrings">
                        <xs:sequence>
                            <xs:element name="string" type="xs:string" maxOccurs="unbounded" minOccurs="1"></xs:element>
                        </xs:sequence>
                    </xs:complexType>
            </xs:schema>
            ';
        $classes = $this->getClasses($content);
        $this->assertCount(1, $classes);
        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $complexType1 = $classes['Example\ComplexType1Type']);

        $property = $complexType1->getProperty('strings');

        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClassOf', $typeOf = $property->getType());
        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPProperty', $typeProp = $typeOf->getArg());
        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $typePropType = $typeProp->getType());

        $this->assertEquals('', $typePropType->getNamespace());
        $this->assertEquals('string', $typePropType->getName());

    }

    /**
     * @dataProvider getMaxOccurs
     */
    public function testMaxOccurs($max, $isArray)
    {
        $content = '
             <xs:schema targetNamespace="http://www.example.com" xmlns:xs="http://www.w3.org/2001/XMLSchema">
                <xs:complexType name="complexType-1">
                     <xs:sequence>
                            <xs:element ' . ($max !== null ? (' maxOccurs="' . $max . '"') : "") . ' name="complexType-1-el-1" type="xs:string"/>
                     </xs:sequence>
                </xs:complexType>
            </xs:schema>
            ';
        $classes = $this->getClasses($content);
        $this->assertCount(1, $classes);
        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $complexType1 = $classes['Example\ComplexType1Type']);

    }


    public function testGeneralParts()
    {
        $content = '
             <xs:schema targetNamespace="http://www.example.com" xmlns:xs="http://www.w3.org/2001/XMLSchema">
                <xs:group name="group-1">
                    <xs:sequence>
                        <xs:element name="group-1-el-1" type="xs:string"/>
                        <xs:group ref="group-2"/>
                    </xs:sequence>
                </xs:group>

                <xs:group name="group-2">
                    <xs:sequence>
                        <xs:element name="group-2-el-1" type="xs:string"/>
                    </xs:sequence>
                </xs:group>

               <xs:element name="element-1" type="xs:string"/>

                <xs:attributeGroup name="attributeGroup-1">
                    <xs:attribute name="attributeGroup-1-att-1" type="xs:string"/>
                    <xs:attribute ref="attribute-1" />
                    <xs:attributeGroup ref="attributeGroup-2" />
                </xs:attributeGroup>

                <xs:attributeGroup name="attributeGroup-2">
                    <xs:attribute name="attributeGroup-2-att-2" type="xs:string"/>
                </xs:attributeGroup>

                <xs:attribute name="attribute-1" type="xs:string"/>

                <xs:complexType name="complexType-1">
                     <xs:attribute ref="attribute-1"/>
                     <xs:attribute name="attribute-2" type="xs:string"/>
                     <xs:attributeGroup ref="attributeGroup-1"/>

                     <xs:sequence>
                            <xs:group ref="group-1"/>
                            <xs:element ref="element-1"/>
                            <xs:element name="complexType-1-el-1" type="xs:string"/>
                     </xs:sequence>
                </xs:complexType>
            </xs:schema>
            ';
        $classes = $this->getClasses($content);

        $this->assertCount(2, $classes);

        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $complexType1 = $classes['Example\ComplexType1Type']);
        $this->assertInstanceOf('Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass', $element1 = $classes['Example\Element1']);


        //$complexType1
        $property = $complexType1->getProperty('attribute1');
        $this->assertEquals('attribute1', $property->getName());
        $this->assertEquals('', $property->getType()->getNamespace());
        $this->assertEquals('string', $property->getType()->getName());

        $property = $complexType1->getProperty('attribute2');
        $this->assertEquals('attribute2', $property->getName());
        $this->assertEquals('', $property->getType()->getNamespace());
        $this->assertEquals('string', $property->getType()->getName());

        $property = $complexType1->getProperty('complexType1El1');
        $this->assertEquals('complexType1El1', $property->getName());
        $this->assertEquals('', $property->getType()->getNamespace());
        $this->assertEquals('string', $property->getType()->getName());

    }

}