<?php
namespace Goetas\Xsd\XsdToPhp\Naming;

use Doctrine\Common\Inflector\Inflector;
use Goetas\XML\XSDReader\Schema\Item;
use Goetas\XML\XSDReader\Schema\Type\Type;

class ShortNamingStrategy implements NamingStrategy
{

    public function getTypeName(Type $type)
    {
        $name = $this->classify($type->getName());
        if ($name && substr($name, -4) !== 'Type') {
            $name .= "Type";
        }
        return $name;
    }

    public function getAnonymousTypeName(Type $type, $parentName)
    {
        return $this->classify($parentName) . "AType";
    }

    public function getPropertyName($item)
    {
        return Inflector::camelize(str_replace(".", " ", $item->getName()));
    }

    public function getItemName(Item $item)
    {
        return $this->classify($item->getName());
    }

    private function classify($name)
    {
        return Inflector::classify(str_replace(".", " ", $name));
    }
}