<?php
namespace Sg\DatatablesBundle\Datatable\Query;


use Doctrine\ORM\Tools\Pagination\Paginator;
use Sg\DatatablesBundle\Datatable\Column\AbstractColumn;
use Twig_Environment;

/**
 * Interface DatatableQueryInterface
 *
 * @package Sg\DatatablesBundle\Datatable\Query
 */
interface DatatableQueryInterface
{
    /**
     * Build query.
     *
     * @return $this
     */
    public function buildQuery();

    /**
     * Add the where-all function.
     *
     * @param callback|callable $callback
     *
     * @return $this
     */
    public function addWhereAll($callback);

    /**
     * Add response callback.
     *
     * @param callback|callable $callback
     *
     * @return $this
     */
    public function addResponseCallback($callback);

    /**
     * Get a json response object for requested datatable data.
     *
     * @param bool $buildQuery
     * @param bool $outputWalkers
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getResponse($buildQuery = true, $outputWalkers = false);

    /**
     * Simple function to get results for export to PHPExcel.
     *
     * @return array
     */
    public function getDataForExport();

    /**
     * Get lineFormatter.
     *
     * @return callable
     */
    public function getLineFormatter();

    /**
     * Get columns.
     *
     * @return AbstractColumn[]
     */
    public function getColumns();

    /**
     * Get paginator.
     *
     * @return array
     */
    public function getPaginatorResults();

    /**
     * Get Twig Environment.
     *
     * @return Twig_Environment
     */
    public function getTwig();

    /**
     * Get imagineBundle.
     *
     * @return boolean
     */
    public function getImagineBundle();

    /**
     * Set the line formatter function.
     *
     * @return $this
     */
    public function setLineFormatter();
}