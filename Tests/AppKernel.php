<?php
/**
 * Setup a simple smyfony application kernel to do some functional testing.
 *
 * @see http://stackoverflow.com/questions/27501321/functionnal-tests-inside-a-standalone-symfony2s-bundle
 */

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array();

        if (in_array($this->getEnvironment(), array('test'))) {
            $bundles[] = new Symfony\Bundle\FrameworkBundle\FrameworkBundle();
            $bundles[] = new Symfony\Bundle\TwigBundle\TwigBundle();
            $bundles[] = new \Sg\DatatablesBundle\SgDatatablesBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config.yml');
    }
}
