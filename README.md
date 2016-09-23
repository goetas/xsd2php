xsd2php
=======

[![Build Status](https://travis-ci.org/goetas/xsd2php.svg?branch=master)](https://travis-ci.org/goetas/xsd2php)
[![Code Coverage](https://scrutinizer-ci.com/g/goetas/xsd2php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/goetas/xsd2php/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/goetas/xsd2php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/goetas/xsd2php/?branch=master)

Convert XSD into PHP classes.

With `goetas/xsd2php` you can convert any XSD/WSDL definition into PHP classes.

**XSD2PHP can also generate [JMS Serializer](http://jmsyst.com/libs/serializer) compatible metadata that can be used to serialize/unserialize the object instances**.

Installation
-----------

There is one recommended way to install xsd2php via [Composer](https://getcomposer.org/):


* adding the dependency to your ``composer.json`` file:

```js
  "require-dev": {
      ..
      "goetas/xsd2php":"^2.1",
      ..
  }
```

Usage
-----

With this example we will convert [OTA XSD definitions](http://opentravel.org/Specifications/OnlineXmlSchema.aspx) into PHP classes.

Suppose that you have allo XSD files in `/home/my/ota`.

Generate PHP classes
--------------------

```sh
vendor/bin/xsd2php convert:php \
`/home/my/ota/OTA_HotelAvail*.xsd \

--ns-map='http://www.opentravel.org/OTA/2003/05;Mercurio/OTA/2007B/' \

--ns-dest='Mercurio/OTA/2007B/;src/Mercurio/OTA/V2007B' \

--alias-map='http://www.opentravel.org/OTA/2003/05;CustomOTADateTimeFormat;Vendor/Project/CustomDateClass'

```
What about namespaces?
* `http://www.opentravel.org/OTA/2003/05` will be converted into `Mercurio/OTA/2007B` PHP namespace

Where place the files?
* `Mercurio/OTA/2007B` classes will be placed into `src/Mercurio/OTA/V2007B` directory


What about custom types?
* `--alias-map='http://www.opentravel.org/OTA/2003/05;CustomOTADateTimeFormat;Vendor/Project/CustomDateClass'`
will instruct XSD2PHP to not generate any class for `CustomOTADateTimeFormat` type inside the `http://www.opentravel.org/OTA/2003/05` namespace.
All reference to this type are replaced with the `Vendor/Project/CustomDateClass` class.


### Use composer scripts to generate classes
```js
  "scripts": {
    "build": "xsd2php convert:php '/home/my/ota/OTA_HotelAvail*.xsd' --ns-map='http://www.opentravel.org/OTA/2003/05;Mercurio/OTA/2007B/' --ns-dest='Mercurio/OTA/2007B/;src/Mercurio/OTA/V2007B'"
  }
```

Now you can build your classes with `composer build`.

Serialize / Unserialize
-----------------------

XSD2PHP can also generate for you [JMS Serializer](http://jmsyst.com/libs/serializer) metadata that you can use to serialize/unserialize the generated PHP class instances.

```sh
vendor/bin/xsd2php  convert:jms-yaml \
`/home/my/ota/OTA_HotelAvail*.xsd \

--ns-map='http://www.opentravel.org/OTA/2003/05;Mercurio/OTA/2007B/'  \
--ns-dest='Mercurio/OTA/2007B/;src/Metadata/JMS;' \

--alias-map='http://www.opentravel.org/OTA/2003/05;CustomOTADateTimeFormat;Vendor/Project/CustomDateClass'

```

What about namespaces?
* `http://www.opentravel.org/OTA/2003/05` will be converted into `Mercurio/OTA/2007B` PHP namespace

Where place the files?
* `http://www.opentravel.org/OTA/2003/05` will be placed into `src/Metadata/JMS` directory

What about custom types?
* `--alias-map='http://www.opentravel.org/OTA/2003/05;CustomOTADateTimeFormat;Vendor/Project/CustomDateClass'`
will instruct XSD2PHP to not generate any metadata information for `CustomOTADateTimeFormat` type inside the `http://www.opentravel.org/OTA/2003/05` namespace.
All reference to this type are replaced with the `Vendor/Project/CustomDateClass` class. You have to provide a [custom serializer](http://jmsyst.com/libs/serializer/master/handlers#subscribing-handlers) for this type


* Add xsd2php dependency to satisfy BaseTypesHandler and XmlSchemaDateHandler.

```js
"require" : {
    "goetas-webservices/xsd2php-runtime":"^0.2.2",
}
```

```php
use JMS\Serializer\SerializerBuilder;
use JMS\Serializer\Handler\HandlerRegistryInterface;

use GoetasWebservices\Xsd\XsdToPhpRuntime\Jms\Handler\BaseTypesHandler;
use GoetasWebservices\Xsd\XsdToPhpRuntime\Jms\Handler\XmlSchemaDateHandler;

$serializerBuilder = SerializerBuilder::create();
$serializerBuilder->addMetadataDir('metadata dir', 'DemoNs');
$serializerBuilder->configureHandlers(function (HandlerRegistryInterface $handler) use ($serializerBuilder) {
    $serializerBuilder->addDefaultHandlers();
    $handler->registerSubscribingHandler(new BaseTypesHandler()); // XMLSchema List handling
    $handler->registerSubscribingHandler(new XmlSchemaDateHandler()); // XMLSchema date handling

    // $handler->registerSubscribingHandler(new YourhandlerHere());
});

$serializer = $serializerBuilder->build();

// deserialize the XML into Demo\MyObject object
$object = $serializer->deserialize('<some xml/>', 'DemoNs\MyObject', 'xml');

// some code ....

// serialize the Demo\MyObject back into XML
$newXml = $serializer->serialize($object, 'xml');

```

Dealing with `xsd:anyType` or `xsd:anySimpleType`
-------------------------------------------------

If your XSD contains `xsd:anyType` or `xsd:anySimpleType` types you have to specify a handler for this.

When you generate the JMS metadata you have to specify a custom handler:

```sh
bin/xsd2php.php convert:jms-yaml \

 ... various params ... \

--alias-map='http://www.w3.org/2001/XMLSchema;anyType;MyCustomAnyTypeHandler' \
--alias-map='http://www.w3.org/2001/XMLSchema;anyType;MyCustomAnySimpleTypeHandler' \

```

Now you have to create a custom serialization handler:

```php
use JMS\Serializer\XmlSerializationVisitor;
use JMS\Serializer\XmlDeserializationVisitor;

use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\VisitorInterface;
use JMS\Serializer\Context;

class MyHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods()
    {
        return array(
            array(
                'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                'format' => 'xml',
                'type' => 'MyCustomAnyTypeHandler',
                'method' => 'deserializeAnyType'
            ),
            array(
                'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                'format' => 'xml',
                'type' => 'MyCustomAnyTypeHandler',
                'method' => 'serializeAnyType'
            )
        );
    }

    public function serializeAnyType(XmlSerializationVisitor $visitor, $data, array $type, Context $context)
    {
        // serialize your object here
    }

    public function deserializeAnyType(XmlDeserializationVisitor $visitor, $data, array $type)
    {
        // deserialize your object here
    }
}
```

Naming Strategy
---------------

There are two types of naming strategies: `short` and `long`. The default is `short`, this naming strategy can however generate naming conflicts.

The `long` naming strategy will suffix elements with `Element` and types with `Type`.

* `MyNamespace\User` will become `MyNamespace\UserElement`
* `MyNamespace\UserType` will become `MyNamespace\UserTypeType`

An XSD for instance with a type named `User`, a type named `UserType`, a root element named `User` and `UserElement`, will only work when using the `long` naming strategy.

* If you don't have naming conflicts and you want to have short and descriptive class names, use the `--naming-strategy=short` option.
* If you have naming conflicts use the `--naming-strategy=long` option.
* If you want to be safe, use the `--naming-strategy=long` option.



Note
----

I'm sorry for the terrible written english within the documentation, I'm trying to improve it.
Pull Requests are welcome.

