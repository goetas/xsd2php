<?php
namespace Goetas\Xsd\XsdToPhp\Jms;

use Exception;
use Goetas\XML\XSDReader\SchemaReader;
use Goetas\XML\XSDReader\Schema\Schema;
use Goetas\XML\XSDReader\Schema\Type\Type;
use Doctrine\Common\Inflector\Inflector;
use Goetas\XML\XSDReader\Schema\Type\BaseComplexType;
use Goetas\XML\XSDReader\Schema\Type\ComplexType;
use Goetas\XML\XSDReader\Schema\Element\Element;
use Goetas\XML\XSDReader\Schema\Item;
use Goetas\XML\XSDReader\Schema\Attribute\Group as AttributeGroup;
use Goetas\XML\XSDReader\Schema\Element\Group;
use Goetas\XML\XSDReader\Schema\Type\SimpleType;
use Goetas\XML\XSDReader\Schema\Attribute\AttributeItem;
use Goetas\XML\XSDReader\Schema\Element\ElementItem;
use Goetas\XML\XSDReader\Schema\Attribute\AttributeContainer;
use Goetas\XML\XSDReader\Schema\Element\ElementContainer;
use Goetas\XML\XSDReader\Schema\Element\ElementSingle;
use Goetas\XML\XSDReader\Schema\Element\ElementDef;
use Goetas\Xsd\XsdToPhp\AbstractConverter;
use Goetas\XML\XSDReader\Schema\Element\ElementRef;

class YamlConverter extends AbstractConverter
{

    public function __construct(){
        parent::__construct();
        $this->addAliasMap("http://www.w3.org/2001/XMLSchema", "dateTime", function (Type $type)
        {
            return "Goetas\Xsd\XsdToPhp\XMLSchema\DateTime";
        });
        $this->addAliasMap("http://www.w3.org/2001/XMLSchema", "time", function (Type $type)
        {
            return "Goetas\Xsd\XsdToPhp\XMLSchema\Time";
        });
        $this->addAliasMap("http://www.w3.org/2001/XMLSchema", "date", function (Type $type)
        {
            return "DateTime<'Y-m-d'>";
        });
    }

    private $classes = [];

    public function convert(array $schemas)
    {
        $visited = array();
        $this->classes = array();
        foreach ($schemas as $schema) {
            $this->navigate($schema, $visited);
        }
        return $this->getTypes();
    }

    private function flattAttributes(AttributeContainer $container)
    {
        $items = array();
        foreach ($container->getAttributes() as $attr) {
            if ($attr instanceof AttributeContainer) {
                $items = array_merge($items, $this->flattAttributes($attr));
            } else {
                $items[] = $attr;
            }
        }
        return $items;
    }

    private function flattElements(ElementContainer $container)
    {
        $items = array();
        foreach ($container->getElements() as $attr) {
            if ($attr instanceof ElementContainer) {
                $items = array_merge($items, $this->flattElements($attr));
            } else {
                $items[] = $attr;
            }
        }
        return $items;
    }

    /**
     *
     * @return PHPClass[]
     */
    public function getTypes()
    {
        uasort($this->classes, function ($a, $b)
        {
            return strcmp(key($a), key($b));
        });

        $ret = array();

        foreach ($this->classes as $definition) {
            $classname = key($definition["class"]);
            if (strpos($classname, '\\') !== false && (! isset($definition["skip"]) || ! $definition["skip"])) {
                $ret[$classname] = $definition["class"];
            }
        }

        return $ret;
    }

    private function navigate(Schema $schema, array &$visited)
    {
        if (isset($visited[spl_object_hash($schema)])) {
            return;
        }
        $visited[spl_object_hash($schema)] = true;

        foreach ($schema->getTypes() as $type) {
            $this->visitType($type);
        }
        foreach ($schema->getElements() as $element) {
            $this->visitElementDef($schema, $element);
        }

        foreach ($schema->getSchemas() as $schildSchema) {
            if (! in_array($schildSchema->getTargetNamespace(), $this->baseSchemas, true)) {
                $this->navigate($schildSchema, $visited);
            }
        }
    }

    private function visitTypeBase(&$class, &$data, Type $type, $name)
    {
        if ($type instanceof BaseComplexType) {
            $this->visitBaseComplexType($class, $data, $type, $name);
        }
        if ($type instanceof ComplexType) {
            $this->visitComplexType($class, $data, $type);
        }
        if ($type instanceof SimpleType) {
            $this->visitSimpleType($class, $data, $type, $name);
        }
    }

    private function &visitElementDef(Schema $schema, ElementDef $element)
    {
        if (! isset($this->classes[spl_object_hash($element)])) {
            $className = $this->findPHPName($element, false);
            $class = array();
            $data = array();
            $ns = $className;
            $class[$ns] = &$data;
            $data["xml_root_name"] = $element->getName();

            if ($schema->getTargetNamespace()) {
                $data["xml_root_namespace"] = $schema->getTargetNamespace();
            }
            $this->classes[spl_object_hash($element)]["class"] = &$class;

            if (! $element->getType()->getName()) {
                $this->visitTypeBase($class, $data, $element->getType(), $element->getName());
            } else {
                $this->handleClassExtension($class, $data, $element->getType(), $element->getName());
            }
        }
        return $this->classes[spl_object_hash($element)]["class"];
    }

    private function findPHPName($type, $isType = true)
    {
        $schema = $type->getSchema();

        if ($alias = $this->getTypeAlias($type, $schema)) {
            return $alias;
        }

        if (! isset($this->namespaces[$schema->getTargetNamespace()])) {
            throw new Exception(sprintf("Non trovo un namespace php per %s, nel file %s", $schema->getTargetNamespace(), $schema->getFile()));
        }
        $ns = $this->namespaces[$schema->getTargetNamespace()];
        $name = Inflector::classify($type->getName());

        if ($isType && $name && substr($name, - 4) !== 'Type') {
            $name .= "Type";
        }

        return $ns . "\\" . $name;
    }

    private function &visitType(Type $type, $force = false)
    {

        if (! isset($this->classes[spl_object_hash($type)])) {

            if ($alias = $this->getTypeAlias($type)) {
                $class = array();
                $class[$alias] = array();

                $this->classes[spl_object_hash($type)]["class"] = &$class;
                $this->classes[spl_object_hash($type)]["skip"] = true;
                return $class;
            }

            $className = $this->findPHPName($type);

            $class = array();
            $data = array();

            $class[$className] = &$data;

            $this->classes[spl_object_hash($type)]["class"] = &$class;

            $this->visitTypeBase($class, $data, $type, $type->getName());

            if ($type instanceof SimpleType){
                $this->classes[spl_object_hash($type)]["skip"] = true;
                return $class;
            }

            if (!$force && ($this->isArrayType($type) || $this->isArrayNestedElement($type))) {
                $this->classes[spl_object_hash($type)]["skip"] = true;
                return $class;
            }
        }elseif ($force) {
            if (!($type instanceof SimpleType) && !$this->getTypeAlias($type)){
                $this->classes[spl_object_hash($type)]["skip"] = false;
            }
        }
        return $this->classes[spl_object_hash($type)]["class"];
    }

    private function &visitTypeAnonymous(Type $type, $name, &$parentClass)
    {
        $class = array();
        $data = array();

        $class[key($parentClass) . "\\" . Inflector::classify($name) . "AType"] = &$data;

        $this->visitTypeBase($class, $data, $type, $name);

        $this->classes[spl_object_hash($type)]["class"] = &$class;

        if ($type instanceof SimpleType){
            $this->classes[spl_object_hash($type)]["skip"] = true;
        }
        return $class;
    }

    private function visitComplexType(&$class, &$data, ComplexType $type)
    {
        $schema = $type->getSchema();
        if (! isset($data["properties"])) {
            $data["properties"] = array();
        }
        foreach ($this->flattElements($type) as $element) {
            $data["properties"][Inflector::camelize($element->getName())] = $this->visitElement($class, $schema, $element);
        }
    }

    private function visitSimpleType(&$class, &$data, SimpleType $type, $name)
    {
        if ($restriction = $type->getRestriction()) {
            $parent = $restriction->getBase();
            if ($parent instanceof Type) {
                $this->handleClassExtension($class, $data, $parent, $name);
            }
        } elseif ($unions = $type->getUnions()) {
            foreach ($unions as $i => $unon) {
                $this->handleClassExtension($class, $data, $unon, $name.$i);
                break;
            }
        }
    }

    private function visitBaseComplexType(&$class, &$data, BaseComplexType $type, $name)
    {
        $parent = $type->getParent();
        if ($parent) {
            $parentType = $parent->getBase();
            if ($parentType instanceof Type) {
                $this->handleClassExtension($class, $data, $parentType, $name);
            }
        }

        $schema = $type->getSchema();
        if (! isset($data["properties"])) {
            $data["properties"] = array();
        }
        foreach ($this->flattAttributes($type) as $attr) {
            $data["properties"][Inflector::camelize($attr->getName())] = $this->visitAttribute($class, $schema, $attr);
        }
    }

    private function handleClassExtension(&$class, &$data, Type $type, $parentName)
    {
        if ($alias = $this->getTypeAlias($type)) {


            $property = array();
            $property["expose"] = true;
            $property["xml_value"] = true;
            $property["access_type"] = "public_method";
            $property["accessor"]["getter"] = "value";
            $property["accessor"]["setter"] = "value";
            $property["type"] = $alias;

            $data["properties"]["__value"] = $property;


        }else{
            $extension = $this->visitType($type, true);

            if (isset($extension['properties']['__value']) && count($extension['properties']) === 1) {
                $data["properties"]["__value"] = $extension['properties']['__value'];
            } else {
                if($type instanceof SimpleType){ // @todo ?? basta come controllo?
                    $property = array();
                    $property["expose"] = true;
                    $property["xml_value"] = true;
                    $property["access_type"] = "public_method";
                    $property["accessor"]["getter"] = "value";
                    $property["accessor"]["setter"] = "value";

                    if ($valueProp = $this->typeHasValue($type, $class, $parentName)) {
                        $property["type"] = $valueProp;
                    } else {
                        $property["type"] = key($extension);
                    }

                    $data["properties"]["__value"] = $property;

                }
            }
        }
    }

    private function visitAttribute(&$class, Schema $schema, AttributeItem $attribute)
    {
        $property = array();
        $property["expose"] = true;
        $property["access_type"] = "public_method";
        $property["serialized_name"] = $attribute->getName();

        $property["accessor"]["getter"] = "get" . Inflector::classify($attribute->getName());
        $property["accessor"]["setter"] = "set" . Inflector::classify($attribute->getName());

        $property["xml_attribute"] = true;

        if ($alias = $this->getTypeAlias($attribute))  {
            $property["type"] = $alias;

        }else if ($itemOfArray = $this->isArrayType($attribute->getType())) {

            if ($valueProp = $this->typeHasValue($itemOfArray, $class, 'xx')) {
                $property["type"] = "Goetas\Xsd\XsdToPhp\Jms\SimpleListOf<" . $valueProp . ">";
            }else{
                $property["type"] = "Goetas\Xsd\XsdToPhp\Jms\SimpleListOf<" . $this->findPHPName($itemOfArray) . ">";
            }

            $property["xml_list"]["inline"] = false;
            $property["xml_list"]["entry_name"] = $itemOfArray->getName();
            $property["xml_list"]["entry_namespace"] = $schema->getTargetNamespace();
        } else {
            $property["type"] = $this->findPHPClass($class, $attribute);
        }
        return $property;
    }

    private function typeHasValue(Type $type, &$parentClass, $name)
    {
        do {
            if ($alias = $this->getTypeAlias($type)) {
                return $alias;
            } else {

                if ($type->getName()) {
                    $class = $this->visitType($type);
                } else {
                    $class = $this->visitTypeAnonymous($type, $name, $parentClass);
                }
                $props = reset($class);
                if (isset($props['properties']['__value']) && count($props['properties']) === 1) {
                    return $props['properties']['__value']['type'];
                }
            }
        } while (method_exists($type, 'getRestriction') && $type->getRestriction() && $type = $type->getRestriction()->getBase());

        return false;
    }

    /**
     *
     * @param PHPClass $class
     * @param Schema $schema
     * @param Element $element
     * @param boolean $arrayize
     * @return \Goetas\Xsd\XsdToPhp\Structure\PHPProperty
     */
    private function visitElement(&$class, Schema $schema, ElementItem $element, $arrayize = true)
    {
        $property = array();
        $property["expose"] = true;
        $property["access_type"] = "public_method";
        $property["serialized_name"] = $element->getName();

        if ($schema->getTargetNamespace()) {
            $property["xml_element"]["namespace"] = $schema->getTargetNamespace();
        }

        $property["accessor"]["getter"] = "get" . Inflector::classify($element->getName());
        $property["accessor"]["setter"] = "set" . Inflector::classify($element->getName());
        $t = $element->getType();

        if ($arrayize){

            if($itemOfArray = $this->isArrayNestedElement($t)) {
                if(!$t->getName()){
                    $classType = $this->visitTypeAnonymous($t, $element->getName(), $class);
                }else{
                    $classType = $this->visitType($t);
                }

                $visited = $this->visitElement($classType, $schema, $itemOfArray, false);

                $property["type"] = "array<" . $visited["type"] . ">";
                $property["xml_list"]["inline"] = false;
                $property["xml_list"]["entry_name"] = $itemOfArray->getName();
                $property["xml_list"]["namespace"] = $schema->getTargetNamespace();
                return $property;
            } elseif ($itemOfArray = $this->isArrayType($t)) {

                if(!$t->getName()){
                    $visitedType = $this->visitTypeAnonymous($itemOfArray, $element->getName(), $class);

                    if($prop = $this->typeHasValue($itemOfArray, $class, 'xx')){
                        $property["type"] = "array<" .$prop . ">";
                    }else{
                        $property["type"] = "array<" . key($visitedType) . ">";
                    }
                }else{
                    $this->visitType($itemOfArray);
                    $property["type"] = "array<" . $this->findPHPName($itemOfArray) . ">";
                }

                $property["xml_list"]["inline"] = false;
                $property["xml_list"]["entry_name"] = $itemOfArray->getName();
                $property["xml_list"]["namespace"] = $schema->getTargetNamespace();
                return $property;
            } elseif ($this->isArrayElement($element)) {
                $property["xml_list"]["inline"] = true;
                $property["xml_list"]["entry_name"] = $element->getName();
                $property["xml_list"]["namespace"] = $schema->getTargetNamespace();

                $property["type"] = "array<" . $this->findPHPClass($class, $element) . ">";
                return $property;
            }
        }

        $property["type"] = $this->findPHPClass($class, $element);
        return $property;
    }

    private function findPHPClass(&$class, Item $node)
    {
        $type = $node->getType();

        if ($alias = $this->getTypeAlias($node->getType())) {
            return $alias;
        }

        if ($node instanceof ElementRef) {
            return key($this->visitElementDef($node->getSchema(), $node->getReferencedElement()));
        }
        if($valueProp = $this->typeHasValue($type, $class, 'xx')){
            return $valueProp;
        }
        if (! $node->getType()->getName()) {
            $visited = $this->visitTypeAnonymous($node->getType(), $node->getName(), $class);
        } else {
            $visited = $this->visitType($node->getType());
        }

        return key($visited);
    }
}
