<?php
namespace Sg\DatatablesBundle\Datatable\Query;

use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Sg\DatatablesBundle\Datatable\Data\DatatableFormatter;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * Class DatatableNativeQuery
 *
 * @package Sg\DatatablesBundle\Datatable\Query
 * @extends AbstractDatatableQuery
 */
class DatatableNativeQuery extends AbstractDatatableQuery
{
    /**
     * @var NativeQuery
     */
    protected $nq;

    /**
     * Build query.
     *
     * @return $this
     */
    public function buildQuery()
    {
        // TODO: Implement buildQuery() method.
    }

    /**
     * Add the where-all function.
     *
     * @param callback|callable $callback
     *
     * @return $this
     */
    public function addWhereAll($callback)
    {
        // TODO: Implement addWhereAll() method.
    }

    /**
     * Get a json response object for requested datatable data.
     *
     * @param bool $buildQuery
     * @param bool $outputWalkers
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getResponse($buildQuery = true, $outputWalkers = false)
    {
        if (false === $buildQuery) {
            $this->buildQuery();
        }

        $countAllResults = $this->datatableView->getOptions()->getCountAllResults();

        $recordsTotal = 0;
        if ($countAllResults) {
            $sqlInitial = $this->nq->getSQL();

            $rsm = new ResultSetMappingBuilder($this->em);
            $rsm->addScalarResult('count', 'count');

            $sqlCount = sprintf('SELECT count(*) AS count FROM (%s) AS item;', $sqlInitial);
            $qCount = $this->em->createNativeQuery($sqlCount, $rsm);
            $qCount->setParameters($this->nq->getParameters());

            $recordsTotal = (int)$qCount->getSingleScalarResult();
        }

        $recordsFiltered = 0;
        //TODO: count filtered records

        // Do pagination for native queries.
        if (isset($this->requestParams['start']) && -1 != $this->requestParams['length']) {
            $this->nq->setSQL(sprintf(
                '%s LIMIT %d, %d;',
                $this->nq->getSQL(),
                $this->requestParams['start'],
                $this->requestParams['length']
            ));
        }

        $this->paginatorResults = $this->nq->getResult($this->nq->getHydrationMode());

        $formatter = new DatatableFormatter($this);
        $formatter->runFormatter();

        $outputHeader = array(
            'draw'            => (int)$this->requestParams['draw'],
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsTotal, //$recordsFiltered,
        );

        $fullOutput = array_merge($outputHeader, $formatter->getOutput());
        $fullOutput = $this->applyResponseCallbacks($fullOutput);

        $response = new JsonResponse();
        $response->setContent(json_encode($fullOutput));

        return $response;
    }

    /**
     * Simple function to get results for export to PHPExcel.
     *
     * @return array
     */
    public function getDataForExport()
    {
        // TODO: Implement getDataForExport() method.
    }

    /**
     * @param NativeQuery $nq
     *
     * @return $this
     */
    public function setNativeQuery(NativeQuery $nq)
    {
        $this->nq = $nq;

        return $this;
    }
}
