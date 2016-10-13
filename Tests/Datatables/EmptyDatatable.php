<?php
namespace Sg\DatatablesBundle\Tests\Datatables;


use Sg\DatatablesBundle\Datatable\View\AbstractDatatableView;
use Sg\DatatablesBundle\Datatable\View\Style;

/**
 * Class EmptyDatatable
 *
 * @package Sg\DatatablesBundle\Tests\Datatables
 * @extends AbstractDatatableView
 */
class EmptyDatatable extends AbstractDatatableView
{
    /**
     * Builds the datatable.
     *
     * @param array $options
     */
    public function buildDatatable(array $options = array())
    {
        $this->topActions->set(array(
            'actions' => array(),
        ));

        $this->features->set(array(
            'scroll_x'   => true,
            'extensions' => array(
                'buttons'    => array('pdf'),
                'responsive' => true,
            ),
        ));

        $this->ajax->set(array(
            'url'  => '',
            'type' => 'GET',
        ));

        $this->options->set(array(
            'class'                   => Style::BOOTSTRAP_3_STYLE . ' table-condensed',
            'use_integration_options' => true,
            'force_dom'               => false,
        ));
    }

    /**
     * Returns Entity.
     *
     * @return string
     */
    public function getEntity()
    {
        return 'AppBundle\Entity\Post';
    }

    /**
     * Returns the name of this datatable view.
     *
     * @return string
     */
    public function getName()
    {
        return 'empty_datatable';
    }
}