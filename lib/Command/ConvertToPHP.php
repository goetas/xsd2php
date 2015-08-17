<?php
namespace Goetas\Xsd\XsdToPhp\Command;

use Goetas\XML\XSDReader\Schema\Item;
use Goetas\Xsd\XsdToPhp\AbstractConverter;
use Goetas\Xsd\XsdToPhp\Naming\NamingStrategy;
use Goetas\Xsd\XsdToPhp\PathGenerator\PathGeneratorException;
use Goetas\Xsd\XsdToPhp\Php\ClassGenerator;
use Goetas\Xsd\XsdToPhp\Php\PathGenerator\Psr4PathGenerator;
use Goetas\Xsd\XsdToPhp\Php\PhpConverter;
use Goetas\Xsd\XsdToPhp\Php\Structure\PHPClass;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Code\Generator\FileGenerator;

class ConvertToPHP extends AbstractConvert
{
    /**
     *
     * @see Console\Command\Command
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('convert:php');
        $this->setDescription('Convert XSD definitions into PHP classes');
    }

    /**
     * @param NamingStrategy $naming
     * @return PhpConverter
     */
    protected function getConverterter(NamingStrategy $naming)
    {
        return new PhpConverter($naming);
    }

    /**
     * @param AbstractConverter $converter
     * @param array $schemas
     * @param array $targets
     * @param OutputInterface $output
     * @return mixed|void
     * @throws PathGeneratorException
     */
    protected function convert(AbstractConverter $converter, array $schemas, array $targets, OutputInterface $output)
    {
        $generator = new ClassGenerator();
        $pathGenerator = new Psr4PathGenerator($targets);

        /** @var ProgressHelper $progress */
        $progress = $this->getHelperSet()->get('progress');

        $items = $converter->convert($schemas);
        $progress->start($output, count($items));

        /** @var PHPClass $item */
        foreach ($items as $item) {
            $progress->advance(1, true);
            $output->write(" Creating <info>" . OutputFormatter::escape($item->getFullName()) . "</info>... ");
            $path = $pathGenerator->getPath($item);

            $fileGen = new FileGenerator();
            $fileGen->setFilename($path);
            $classGen = new \Zend\Code\Generator\ClassGenerator();

            if ($generator->generate($classGen, $item)) {

                $fileGen->setClass($classGen);

                $fileGen->write();
                $output->writeln("done.");
            } else {
                $output->write("skip.");

            }
        }
        $progress->finish();
    }
}
