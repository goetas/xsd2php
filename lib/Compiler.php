<?php
namespace Goetas\Xsd\XsdToPhp;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * The Compiler class compiles xsd2php into a phar
 */
class Compiler
{
    private $version;
    private $versionDate;

    /**
     * Compiles xsd2php into a single phar file
     *
     * @throws \RuntimeException
     * @param string $pharFile The full path to the file to create
     */
    public function compile($pharFile = 'xsd2php.phar')
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        $process = new Process('git log --pretty="%H" -n1 HEAD', __DIR__);
        if ($process->run() != 0) {
            throw new \RuntimeException('Can\'t run git log. You must ensure to run compile from xsd2php git repository clone and that git binary is available.');
        }
        $this->version = trim($process->getOutput());

        $process = new Process('git log -n1 --pretty=%ci HEAD', __DIR__);
        if ($process->run() != 0) {
            throw new \RuntimeException('Can\'t run git log. You must ensure to run compile from xsd2php git repository clone and that git binary is available.');
        }

        $date = new \DateTime(trim($process->getOutput()));
        $date->setTimezone(new \DateTimeZone('UTC'));
        $this->versionDate = $date->format('Y-m-d H:i:s');

        $process = new Process('git describe --tags --exact-match HEAD');
        if ($process->run() == 0) {
            $this->version = trim($process->getOutput());
        }

        $phar = new \Phar($pharFile, 0, 'xsd2php.phar');
        $phar->setSignatureAlgorithm(\Phar::SHA1);

        $phar->startBuffering();

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->notName('Compiler.php')
            ->in(__DIR__)
        ;

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        foreach ($finder as $file) {
            $this->addFile($phar, $file, false);
        }

        $finder = new Finder();
        $finder->files()
            ->ignoreVCS(true)
            ->name('*.php')
            ->name('LICENSE')
            ->exclude('Tests')
            ->exclude('tests')
            ->in(__DIR__.'/../vendor/composer/')
            ->in(__DIR__.'/../vendor/doctrine/annotations')
            ->in(__DIR__.'/../vendor/doctrine/inflector')
            ->in(__DIR__.'/../vendor/doctrine/lexer')
            ->in(__DIR__.'/../vendor/goetas/')
            ->in(__DIR__.'/../vendor/jms/')
            ->in(__DIR__.'/../vendor/phpcollection/')
            ->in(__DIR__.'/../vendor/phpoption/')
            ->in(__DIR__.'/../vendor/symfony/')
            ->in(__DIR__.'/../vendor/zendframework/')
        ;

        foreach ($finder as $file) {
            $this->addFile($phar, $file);
        }

        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../vendor/autoload.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../vendor/composer/autoload_namespaces.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../vendor/composer/autoload_psr4.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../vendor/composer/autoload_classmap.php'));
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../vendor/composer/autoload_real.php'));
        if (file_exists(__DIR__.'/../vendor/composer/include_paths.php')) {
            $this->addFile($phar, new \SplFileInfo(__DIR__.'/../vendor/composer/include_paths.php'));
        }
        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../vendor/composer/ClassLoader.php'));
        $this->addBin($phar);

        // Stubs
        $phar->setStub($this->getStub());

        $phar->stopBuffering();

        // disabled for interoperability with systems without gzip ext
        // $phar->compressFiles(\Phar::GZ);

//        $this->addFile($phar, new \SplFileInfo(__DIR__.'/../../../LICENSE'), false);

        unset($phar);
    }

    private function addFile(\Phar $phar, \SplFileInfo $file, $strip = true)
    {
        $path = strtr(str_replace(dirname(__DIR__).DIRECTORY_SEPARATOR, '', $file->getRealPath()), '\\', '/');

        $content = file_get_contents($file);
        if ($strip) {
            $content = $this->stripWhitespace($content);
        } elseif ('LICENSE' === basename($file)) {
            $content = "\n".$content."\n";
        }

        $phar->addFromString($path, $content);
    }

    private function addBin(\Phar $phar)
    {
        $content = file_get_contents(__DIR__.'/../bin/xsd2php');
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $phar->addFromString('bin/xsd2php', $content);
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * @param  string $source A PHP string
     * @return string The PHP string with the whitespace removed
     */
    private function stripWhitespace($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }

    private function getStub()
    {
        $stub = <<<'EOF'
#!/usr/bin/env php
<?php
// Avoid APC causing random fatal errors per https://github.com/composer/composer/issues/264
if (extension_loaded('apc') && ini_get('apc.enable_cli') && ini_get('apc.cache_by_default')) {
    if (version_compare(phpversion('apc'), '3.0.12', '>=')) {
        ini_set('apc.cache_by_default', 0);
    } else {
        fwrite(STDERR, 'Warning: APC <= 3.0.12 may cause fatal errors when running xsd2php commands.'.PHP_EOL);
        fwrite(STDERR, 'Update APC, or set apc.enable_cli or apc.cache_by_default to 0 in your php.ini.'.PHP_EOL);
    }
}

Phar::mapPhar('xsd2php.phar');

EOF;

        return $stub . <<<'EOF'
require 'phar://xsd2php.phar/bin/xsd2php';

__HALT_COMPILER();
EOF;
    }
}
