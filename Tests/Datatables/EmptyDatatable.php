<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Tests\Datatables;

use Sg\DatatablesBundle\Datatable\View\AbstractDatatableView;

/**
 * Class EmptyDatatable
 *
 * @package Sg\DatatablesBundle\Tests\Datatables
 */
class EmptyDatatable extends AbstractDatatableView
{
    /**
     * {@inheritdoc}
     */
    public function buildDatatable(array $options = array())
    {
        $this->topActions->set(array(
            'actions' => array(),
        ));
        $this->features->set(array());
        $this->options->set(array());

        $this->ajax->set(array(
            'url'  => 'foo/bar',
            'type' => 'GET',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getEntity()
    {
        return 'AppBundle\Entity\Empty';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'empty_datatable';
    }
}
