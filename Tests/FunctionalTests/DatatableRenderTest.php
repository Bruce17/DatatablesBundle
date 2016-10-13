<?php
/**
 * Created by PhpStorm.
 *
 * @author Michael Raith
 * @email  michael.raith@bcmsolutions.de
 * @date   13.10.2016 15:56
 */

namespace Sg\DatatablesBundle\Tests\FunctionalTests;


use Sg\DatatablesBundle\Tests\BaseDatatablesTestCase;

/**
 * Class DatatableRenderTest
 *
 * @package Sg\DatatablesBundle\Tests\FunctionalTests
 * @extends BaseDatatablesTestCase
 */
class DatatableRenderTest extends BaseDatatablesTestCase
{
    public function setUp()
    {
        parent::setUp();
    }


    public function testTrue()
    {
        $this->assertTrue(true);
    }

    public function skip_testRenderEmptyDatatable()
    {
        $datatable = $this->createDummyDatatable('\Sg\DatatablesBundle\Tests\Datatables\EmptyDatatable');
        $datatable->buildDatatable();

        $result = $this->renderTemplate(
            '@test/datatable-wrapper.html.twig',
            array(
                'datatable' => $datatable,
            )
        );
    }
}