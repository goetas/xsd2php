<?php
namespace Goetas\Xsd\XsdToPhp\Generator;

use DOMXPath;
use DOMElement;
use DOMDocument;
use goetas\xml\wsdl\Exception;
use Goetas\Xsd\XsdToPhp\Structure\PHPType;
use Goetas\Xsd\XsdToPhp\Structure\PHPTrait;
use Goetas\Xsd\XsdToPhp\Structure\PHPProperty;
use Goetas\Xsd\XsdToPhp\Structure\PHPClass;
use Doctrine\Common\Inflector\Inflector;
use Goetas\Xsd\XsdToPhp\Structure\PHPClassOf;
use Goetas\Xsd\XsdToPhp\Structure\PHPConstant;

class ClassGenerator
{

    protected function handleChecks(PHPType $type)
    {
        $str = '';
        $strVal = '';

        if (($type instanceof PHPClass) && ($type->getChecks('__value') || $type->hasProperty('__value'))) {

            $str .= "protected function _checkValue(\$value)" . PHP_EOL;
            $str .= "{" . PHP_EOL;

            $methodBody = '';

            if ($type->getExtends()) {
                $methodBody .= '$value = parent::_checkValue($value);' . PHP_EOL;
            }

            foreach ($type->getChecks('__value') as $checkType => $checks) {
                if ($checkType == "enumeration") {

                    $vs = array_map(function($check){
                        /** @var PHPConstant $const */
                        $const = $check['constant'];
                        return 'static::'.$const->getName();
                    }, $checks);

                    $strVal .= 'public static function values()'.PHP_EOL;
                    $strVal .= '{'.PHP_EOL;
                    $strVal .= $this->indent('return array('.implode(', ', $vs).');').PHP_EOL;
                    $strVal .= '}'.PHP_EOL;

                    $methodBody .= 'if (!in_array($value, static::values())) {' . PHP_EOL;
                    $methodBody .= $this->indent('$values = implode(\', \', static::values());') . PHP_EOL;
                    $methodBody .= $this->indent("throw new \\InvalidArgumentException(\"The restriction $checkType with '\$values' is not true\");") . PHP_EOL;
                    $methodBody .= '}' . PHP_EOL;
                } elseif ($checkType == "pattern") {
                    foreach ($checks as $check) {
                        $methodBody .= 'if (!preg_match(' . var_export("/" . $check["value"] . "/", true) . ', $value)) {' . PHP_EOL;
                        $methodBody .= $this->indent("throw new \\InvalidArgumentException('The restriction $checkType with value \\'" . $check["value"] . "\\' is not true');") . PHP_EOL;
                        $methodBody .= '}' . PHP_EOL;
                    }
                } elseif ($checkType == "minLength") {
                    foreach ($checks as $check) {
                        $methodBody .= 'if (strlen($value) < ' . $check['value'] . ') {' . PHP_EOL;
                        $methodBody .= $this->indent("throw new \\InvalidArgumentException('The restriction $checkType with value \\'" . $check["value"] . "\\' is not true');") . PHP_EOL;
                        $methodBody .= '}' . PHP_EOL;
                    }
                } elseif ($checkType == "maxLength") {
                    foreach ($checks as $check) {
                        $methodBody .= 'if (strlen($value) > ' . $check['value'] . ') {' . PHP_EOL;
                        $methodBody .= $this->indent("throw new \\InvalidArgumentException('The restriction $checkType with value \\'" . $check["value"] . "\\' is not true');") . PHP_EOL;
                        $methodBody .= '}' . PHP_EOL;
                    }
                }
            }

            $methodBody .= 'return $value;';

            $str .= $this->indent($methodBody) . PHP_EOL;
            $str .= "}" . PHP_EOL;
            $str .= PHP_EOL;
        }

        return $str.PHP_EOL.$strVal;
    }

    protected function handleBody(PHPType $type)
    {
        $str = '';

        if ($type instanceof PHPTrait || $type instanceof PHPClass) {

            foreach ($type->getTraits() as $ext) {
                $str .= 'use \\' . $ext->getFullName() . ";" . PHP_EOL;
            }

            foreach ($type->getConstants() as $const) {
                $str .= $this->handleConstant($const) . PHP_EOL . PHP_EOL;
            }

            foreach ($type->getProperties() as $prop) {
                $str .= $this->handleProperty($prop) . PHP_EOL . PHP_EOL;
            }

            $str .= $this->handleChecks($type);

            foreach ($type->getProperties() as $prop) {
                $str .= $this->handleMethods($prop, $type) . PHP_EOL;
            }
            $str = substr($str, 0, - strlen(PHP_EOL));
        }

        return $str;
    }

    protected function isNativeType(PHPClass $class)
    {
        return ! $class->getNamespace() && in_array($class->getName(), [
            'string',
            'int',
            'float',
            'integer',
            'boolean',
            'array'
        ]);
    }

    protected function hasTypeHint(PHPClass $class)
    {
        return $class->getNamespace() || in_array($class->getName(), [
            'array'
        ]);
    }

    protected function getPhpType(PHPClass $class)
    {
        if (! $class->getNamespace()) {
            if ($this->isNativeType($class)) {
                return $class->getName();
            }
            return "\\" . $class->getName();
        }
        return "\\" . $class->getFullName();
    }

    protected function addValueMethods(PHPProperty $prop, PHPType $class)
    {

        $type = $prop->getType();

        $doc = 'Gets or sets the inner value.' . PHP_EOL . PHP_EOL;

        if ($c = $this->getFirstLineComment($prop->getDoc())) {
            $doc .= $c . PHP_EOL . PHP_EOL;
        }
        if ($type && $type instanceof PHPClassOf) {
            $doc .= "@param \$value " . $this->getPhpType($type->getArg()->getType()) . "[]";
        } elseif ($type) {
            $doc .= "@param \$value " . $this->getPhpType($prop->getType());
        } else {
            $doc .= "@param \$value mixed";
        }
        $doc .= PHP_EOL;

        if ($type && $type instanceof PHPClassOf) {
            $doc .= "@return " . $this->getPhpType($type->getArg()->getType()) . "[]";
        } elseif ($type) {
            $doc .= "@return " . $this->getPhpType($type);
        } else {
            $doc .= "@return mixed";
        }

        $str = $this->writeDocBlock($doc);

        $typedeclaration = '';
        if ($type && $this->hasTypeHint($type)) {
            $typedeclaration = $this->getPhpType($type) . " ";
        }

        $str .= "public function value($typedeclaration\$value = null)" . PHP_EOL;
        $str .= "{" . PHP_EOL;

        $methodBody = "if (\$value !== null) {" . PHP_EOL;
        $methodBody .= $this->indent("\$this->" . $prop->getName() . " = \$this->_checkValue(\$value);") . PHP_EOL;
        $methodBody .= "}" . PHP_EOL;

        $methodBody .= "return \$this->" . $prop->getName() . ";" . PHP_EOL;

        $str .= $this->indent($methodBody) . PHP_EOL;

        $str .= "}" . PHP_EOL;

        $str .= PHP_EOL;

        $str .= "public function __toString()" . PHP_EOL;
        $str .= "{" . PHP_EOL;
        $methodBody = "return strval(\$this->" . $prop->getName() . ");";
        $str .= $this->indent($methodBody) . PHP_EOL;

        $str .= "}" . PHP_EOL;

        $str .= PHP_EOL;

        $doc = "";
        $doc .= "@param \$value " . ($type ? $this->getPhpType($type) : "mixed");

        $str .= $this->writeDocBlock($doc);

        $str .= "protected function __construct(\$value)" . PHP_EOL;
        $str .= "{" . PHP_EOL;
        $methodBody = "\$this->value(\$value);";
        $str .= $this->indent($methodBody) . PHP_EOL;

        $str .= "}" . PHP_EOL;
        $str .= PHP_EOL;

        $doc = "";
        $doc .= "@param \$value " . ($type ? $this->getPhpType($type) : "mixed").PHP_EOL;
        $doc .= "@return " . $class->getName();

        $str .= $this->writeDocBlock($doc);

        $str .= "public static function create(\$value)" . PHP_EOL;
        $str .= "{" . PHP_EOL;
        $methodBody = "return new static(\$value);";
        $str .= $this->indent($methodBody) . PHP_EOL;

        $str .= "}" . PHP_EOL;

        return $str;
    }



    protected function handleSetter(PHPProperty $prop, PHPType $class)
    {
        $type = $prop->getType();

        $str = '';
        $doc = '';

        if ($c = $this->getFirstLineComment($prop->getDoc())) {
            $doc .= $c . PHP_EOL . PHP_EOL;
        }
        if ($type && $type instanceof PHPClassOf) {
            $doc .= "@param $" . $prop->getName() . " " . $this->getPhpType($type->getArg()->getType()) . "[]";
        } elseif ($type) {
            $doc .= "@param $" . $prop->getName() . " " . $this->getPhpType($prop->getType());
        } else {
            $doc .= "@param $" . $prop->getName() . " mixed";
        }

        $doc .= PHP_EOL."@return \\" . $class->getFullName().PHP_EOL;

        $str .= $this->writeDocBlock($doc);

        $typedeclaration = '';
        if ($type && $this->hasTypeHint($type)) {
            $typedeclaration = $this->getPhpType($type) . " ";
        }
        $str .= "public function set" . Inflector::classify($prop->getName()) . "($typedeclaration\$" . $prop->getName() . ")" . PHP_EOL;
        $str .= "{" . PHP_EOL;

        $methodBody = '';
        if ($type && $type instanceof PHPClassOf) {
            $methodBody .= "foreach ($" . $prop->getName() . " as \$item) {" . PHP_EOL;
            $methodBody .= $this->indent("if (!(\$item instanceof " . $this->getPhpType($type->getArg()->getType()) . ") ) {") . PHP_EOL;
            $methodBody .= $this->indent("throw new \\InvalidArgumentException('Argument 1 passed to ' . __METHOD__ . ' be an array of " . $this->getPhpType($type->getArg()->getType()) . "');", 2) .
            PHP_EOL;
            $methodBody .= $this->indent("}") . PHP_EOL;
            $methodBody .= "}" . PHP_EOL;
        }

        $methodBody .= "\$this->" . $prop->getName() . " = \$" . $prop->getName() . ";" . PHP_EOL;
        $methodBody .= "return \$this;";
        $str .= $this->indent($methodBody) . PHP_EOL;

        $str .= "}" . PHP_EOL;

        return $str;
    }
    protected function handleGetter(PHPProperty $prop, PHPType $class)
    {
        $type = $prop->getType();

        $str = '';
        $doc = '';

        if ($c = $this->getFirstLineComment($prop->getDoc())) {
            $doc .= $c . PHP_EOL . PHP_EOL;
        }

        if ($type && $type instanceof PHPClassOf) {
            $doc .= "@return " . $this->getPhpType($type->getArg()->getType()) . "[]";
        } elseif ($type) {
            $doc .= "@return " . $this->getPhpType($type);
        } else {
            $doc .= "@return mixed";
        }

        $str .= $this->writeDocBlock($doc);

        $str .= "public function get" . Inflector::classify($prop->getName()) . "()" . PHP_EOL;
        $str .= "{" . PHP_EOL;
        $methodBody = "return \$this->" . $prop->getName() . ";";
        $str .= $this->indent($methodBody) . PHP_EOL;

        $str .= "}" . PHP_EOL;

        return $str;
    }
    protected function handleValueMethods(PHPProperty $prop, PHPType $class){
        $type = $prop->getType();
        $doc = '';
        $str = '';
        if ($c = $this->getFirstLineComment($prop->getDoc())) {
            $doc .= $c . PHP_EOL . PHP_EOL;
        }

        if ($type) {
            $doc .= "@return " . ($type->getPropertyInHierarchy('__value')->getType()?$this->getPhpType($type->getPropertyInHierarchy('__value')->getType()):"mixed");
        } else {
            $doc .= "@return mixed";
        }


        $str .= $this->writeDocBlock($doc);
        $str .= "public function extract" . Inflector::classify($prop->getName()) . "()" . PHP_EOL;
        $str .= "{" . PHP_EOL;
        $methodBody = "return \$this->" . $prop->getName() . " ? \$this->" . $prop->getName() . "->value() : null;";
        $str .= $this->indent($methodBody) . PHP_EOL;

        $str .= "}" . PHP_EOL;
        return $str;
    }

    protected function handleAdder(PHPProperty $prop, PHPType $class)
    {
        $type = $prop->getType();

        $doc = '';
        $str = '';
        if ($c = $type->getArg()->getDoc()) {
            $doc .= $c . PHP_EOL . PHP_EOL;
        }

        $propName = $type->getArg()->getName() ?  : $prop->getName();

        $doc .= "@param $" . $propName . " " . $this->getPhpType($type->getArg()->getType());
        if ($type->getArg()->getType()->getDoc()) {
            $doc .= " " . $this->getFirstLineComment($type->getArg()->getType()->getDoc());
        }

        if ($doc) {
            $str .= $this->writeDocBlock($doc);
        }

        $typedeclaration = '';
        if ($this->hasTypeHint($type->getArg()->getType())) {
            $typedeclaration = $this->getPhpType($type->getArg()->getType()) . " ";
        }

        $r = array(
            'arrayof',
            'setof',
            'listof',
        );

        $str .= "public function add" . str_ireplace($r, "", Inflector::classify($prop->getName())) . "($typedeclaration\$" . $propName . ")" . PHP_EOL;
        $str .= "{" . PHP_EOL;
        $methodBody = "\$this->" . $prop->getName() . "[] = \$" . $propName . ";" . PHP_EOL;
        $methodBody .= "return \$this;";
        $str .= $this->indent($methodBody) . PHP_EOL;

        $str .= "}" . PHP_EOL;

        return $str;
    }

    protected function handleMethods(PHPProperty $prop, PHPType $class)
    {
        $type = $prop->getType();

        $str = '';
        $str .= PHP_EOL;

        if ($prop->getName() == "__value") {
            $str .= $this->addValueMethods($prop, $class);
        } else {

            if ($prop->getType() && $prop->getType()->hasPropertyInHierarchy('__value')) {
                $str .= $this->handleValueMethods($prop, $class);
            }
            if ($type && $type instanceof PHPClassOf) {
                $str .= PHP_EOL;
                $str .= $this->handleAdder($prop, $class);
            }

            $str .= $this->handleGetter($prop, $class);
            $str .= $this->handleSetter($prop, $class);

        }
        return $str;
    }

    private function getFirstLineComment($str)
    {
        $str = trim($str);
        if ($str && $str[0] !== '@' && ($p = strpos($str, '.')) !== false && $p < 91) {
            return substr($str, 0, $p + 1);
        }
        if ($str && $str[0] !== '@' && ($p = strpos($str, "\n")) !== false && $p < 91) {
            return substr($str, 0, $p + 1);
        }
        return '';
    }

    /**
     * @param PHPConstant[] $constants
     * @return string
     */
    protected function handleConstantValueMethod($constants)
    {
        $str = '';

        $str .= 'public static function values()'.PHP_EOL;
        $str .= '{';
        $str .= $this->indent(sprintf('return array(%s);', $values));
        $str .= '}';

        return $str;
    }

    protected function handleConstant(PHPConstant $const)
    {
        $doc = '';

        if ($const->getDoc()) {
            $doc .= $const->getDoc() . PHP_EOL . PHP_EOL;
        }
        $str = "";
        if ($doc) {
            $str .= $this->writeDocBlock($doc);
        }

        $str .= "const " . $const->getName() . " = ";
        $str .= var_export($const->getValue(), true);
        $str .= ";";
        return $str;
    }

    protected function handleProperty(PHPProperty $prop)
    {
        $doc = '';

        if ($prop->getDoc()) {
            $doc .= $prop->getDoc() . PHP_EOL . PHP_EOL;
        }

        if ($prop->getType()) {
            $doc .= "@var " . $this->getPhpType($prop->getType());
        } else {
            $doc .= "@var mixed";
        }

        $str = "";
        if ($doc) {
            $str .= $this->writeDocBlock($doc);
        }
        $str .= $prop->getVisibility() . " \$" . $prop->getName();

        if ($prop->getType() && (! $prop->getType()->getNamespace() && $prop->getType()->getName() == "array")) {
            $str .= " = array()";
        }
        $str .= ";";

        return $str;
    }

    protected function handleMainDecl(PHPType $type, $aliasExtensioin = null)
    {
        $str = '';
        if ($type instanceof PHPTrait) {
            $str .= 'trait ';
            $str .= $type->getName();
        } else {
            $str .= 'class ';
            $str .= $type->getName();

            if ($type->getExtends()) {
                $str .= ' extends ';
                $str .= $aliasExtensioin ?  : $type->getExtends()->getName();
            }
        }
        return $str;
    }

    public function generate(PHPType $type)
    {
        $str = '<?php' . PHP_EOL;

        $str .= "namespace " . $type->getNamespace() . ";" . PHP_EOL;

        $base = null;
        if ($type instanceof PHPClass) {
            if ($type->getExtends() && $type->getExtends()->getNamespace() != $type->getNamespace()) {
                $str .= PHP_EOL . "use " . $type->getExtends()->getNamespace() . "\\" . $type->getExtends()->getName();

                if ($type->getExtends()->getName() == $type->getName()) {
                    $base = $type->getExtends()->getName() . "Base";
                    $str .= " as " . $base;
                }
                $str .= ";" . PHP_EOL;
            }
        }

        $str .= PHP_EOL;

        $doc = '';
        if ($type->getDoc()) {
            $doc .= $type->getDoc() . PHP_EOL . PHP_EOL;
        }
        if ($doc) {
            $str .= $this->writeDocBlock($doc);
        }

        $str .= $this->handleMainDecl($type, $base);

        $str .= PHP_EOL . "{" . PHP_EOL . PHP_EOL;

        $str .= $this->indent($this->handleBody($type));

        $str .= PHP_EOL . "}" . PHP_EOL;

        return $str;
    }

    protected function writeDocBlock($str)
    {
        $content = '/**' . PHP_EOL;

        $lines = array();

        foreach (explode("\n", trim($str)) as $line) {
            if (! $line) {
                $lines[] = $line;
                continue;
            }
            if ($line[0] === '@') {
                $lines[] = $line;
            } else {
                foreach (explode("\n", wordwrap($line, 90)) as $l) {
                    $lines[] = $l;
                }
            }
        }

        foreach ($lines as $row) {
            $content .= ' * ' . $row . PHP_EOL;
        }
        $content .= ' */' . PHP_EOL;
        return $content;
    }

    protected function indent($str, $times = 1)
    {
        $tabs = str_repeat("    ", $times);

        return $tabs . str_replace("\n", "\n" . $tabs, $str);
    }
}
