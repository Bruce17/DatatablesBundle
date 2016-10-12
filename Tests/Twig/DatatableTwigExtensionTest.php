<?php
namespace Sg\DatatablesBundle\Tests\Twig;


use Sg\DatatablesBundle\Twig\DatatableTwigExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Class DatatableTwigExtensionTest
 *
 * @package Sg\DatatablesBundle\Tests\Twig
 * @extends KernelTestCase
 */
class DatatableTwigExtensionTest extends KernelTestCase
{
    /**
     * @var DatatableTwigExtension
     */
    protected $extension;


    protected function setUp()
    {
        static::bootKernel();

        $container = static::$kernel->getContainer();

        $this->extension = $container->get('sg_datatables.twig.extension');
    }


    public function testGetName()
    {
        $this->assertEquals('sg_datatables_twig_extension', $this->extension->getName());
    }
}
