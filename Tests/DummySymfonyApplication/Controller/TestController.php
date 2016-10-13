<?php

namespace Sg\DatatablesBundle\Tests\DummySymfonyApplication\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class TestController extends Controller
{
    public function emptyDatatableAction()
    {
        $datatable = $this->get('app.datatable.empty');
        $datatable->buildDatatable();

        return $this->render(
            'post/index.html.twig',
            array(
                'datatable' => $datatable,
            )
        );
    }
}
