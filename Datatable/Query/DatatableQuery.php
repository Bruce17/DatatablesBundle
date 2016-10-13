<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable\Query;


use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use Sg\DatatablesBundle\Datatable\Data\DatatableFormatter;
use Sg\DatatablesBundle\Datatable\View\DatatableViewInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig_Environment;


/**
 * Class DatatableQuery
 *
 * @package Sg\DatatablesBundle\Datatable\Data
 * @extends AbstractDatatableQuery
 */
class DatatableQuery extends AbstractDatatableQuery
{
    /**
     * @var QueryBuilder
     */
    protected $qb;

    /**
     * DatatableQuery constructor.
     *
     * @param array                  $requestParams
     * @param DatatableViewInterface $datatableView
     * @param array                  $configs
     * @param Twig_Environment       $twig
     * @param bool                   $imagineBundle
     * @param bool                   $doctrineExtensions
     * @param string                 $locale
     */
    public function __construct(
        array $requestParams,
        DatatableViewInterface $datatableView,
        array $configs,
        Twig_Environment $twig,
        $imagineBundle,
        $doctrineExtensions,
        $locale
    ) {
        parent::__construct(
            $requestParams,
            $datatableView,
            $configs,
            $twig,
            $imagineBundle,
            $doctrineExtensions,
            $locale
        );

        $this->qb = $this->em->createQueryBuilder();
    }


    /**
     * @inheritdoc
     */
    public function buildQuery()
    {
        $this->setSelectFrom();
        $this->setLeftJoins($this->qb);
        $this->setWhere($this->qb);
        $this->setWhereAllCallback($this->qb);
        $this->setOrderBy();
        $this->setLimit();

        return $this;
    }

    /**
     * Get query.
     *
     * @return QueryBuilder
     *
     * @deprecated
     */
    public function getQuery()
    {
        @trigger_error('This method is deprecated and will be removed. Please use "getQueryBuilder" instead.',
            E_USER_DEPRECATED);

        return $this->qb;
    }

    /**
     * Set query.
     *
     * @param QueryBuilder $qb
     *
     * @return $this
     *
     * @deprecated
     */
    public function setQuery(QueryBuilder $qb)
    {
        @trigger_error('This method is deprecated and will be removed. Please use "setQueryBuilder" instead.',
            E_USER_DEPRECATED);

        $this->qb = $qb;

        return $this;
    }

    /**
     * Get query builder.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->qb;
    }

    /**
     * Set query builder.
     *
     * @param QueryBuilder $qb
     *
     * @return $this
     */
    public function setQueryBuilder(QueryBuilder $qb)
    {
        $this->qb = $qb;

        return $this;
    }

    //-------------------------------------------------
    // Callbacks
    //-------------------------------------------------

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function addWhereAll($callback)
    {
        if (!is_callable($callback)) {
            throw new Exception(sprintf("Callable expected and %s given", gettype($callback)));
        }

        $this->callbacks['WhereAll'][] = $callback;

        return $this;
    }

    /**
     * Set where all callback.
     *
     * @param QueryBuilder $qb
     *
     * @return $this
     */
    private function setWhereAllCallback(QueryBuilder $qb)
    {
        if (!empty($this->callbacks['WhereAll'])) {
            foreach ($this->callbacks['WhereAll'] as $callback) {
                $callback($qb);
            }
        }

        return $this;
    }

    //-------------------------------------------------
    // Build a query
    //-------------------------------------------------

    /**
     * Set select from.
     *
     * @return $this
     */
    private function setSelectFrom()
    {
        foreach ($this->selectColumns as $key => $value) {
            $this->qb->addSelect('partial ' . $key . '.{' . implode(',', $this->selectColumns[$key]) . '}');
        }

        $this->qb->from($this->entity, $this->tableName);

        return $this;
    }

    /**
     * Set leftJoins.
     *
     * @param QueryBuilder $qb
     *
     * @return $this
     */
    private function setLeftJoins(QueryBuilder $qb)
    {
        foreach ($this->joins as $key => $value) {
            $qb->leftJoin($key, $value);
        }

        return $this;
    }

    /**
     * Searching / Filtering.
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * @param QueryBuilder $qb
     *
     * @return $this
     */
    private function setWhere(QueryBuilder $qb)
    {
        $globalSearch = $this->requestParams['search']['value'];

        // global filtering
        if ('' != $globalSearch) {

            $orExpr = $qb->expr()->orX();

            foreach ($this->columns as $key => $column) {
                if (true === $this->isSearchColumn($column)) {
                    $searchField = $this->searchColumns[$key];

                    if (true === $this->isPostgreSQLConnection) {
                        $searchField = $this->cast($searchField, $column);
                    }

                    $orExpr->add($qb->expr()->like($searchField, '?' . $key));
                    $qb->setParameter($key, '%' . $globalSearch . '%');
                }
            }

            $qb->where($orExpr);
        }

        // individual filtering
        if (true === $this->individualFiltering) {
            $andExpr = $qb->expr()->andX();

            $i = 100;

            foreach ($this->columns as $key => $column) {

                if (true === $this->isSearchColumn($column)) {
                    $filter = $column->getFilter();
                    $searchField = $this->searchColumns[$key];

                    if (array_key_exists($key, $this->requestParams['columns']) === false) {
                        continue;
                    }

                    $searchValue = $this->requestParams['columns'][$key]['search']['value'];

                    if ('' != $searchValue && 'null' != $searchValue) {
                        if (true === $this->isPostgreSQLConnection) {
                            $searchField = $this->cast($searchField, $column);
                        }

                        $andExpr = $filter->addAndExpression($andExpr, $qb, $searchField, $searchValue, $i);
                    }
                }
            }

            if ($andExpr->count() > 0) {
                $qb->andWhere($andExpr);
            }
        }

        return $this;
    }

    /**
     * Ordering.
     * Construct the ORDER BY clause for server-side processing SQL query.
     *
     * @return $this
     */
    private function setOrderBy()
    {
        if (isset($this->requestParams['order']) && count($this->requestParams['order'])) {

            $counter = count($this->requestParams['order']);

            for ($i = 0; $i < $counter; $i++) {
                $columnIdx = (integer)$this->requestParams['order'][$i]['column'];
                $requestColumn = $this->requestParams['columns'][$columnIdx];

                if ('true' == $requestColumn['orderable']) {
                    $this->qb->addOrderBy(
                        $this->orderColumns[$columnIdx],
                        $this->requestParams['order'][$i]['dir']
                    );
                }
            }
        }

        return $this;
    }

    /**
     * Paging.
     * Construct the LIMIT clause for server-side processing SQL query.
     *
     * @return $this
     */
    private function setLimit()
    {
        if (isset($this->requestParams['start']) && -1 != $this->requestParams['length']) {
            $this->qb->setFirstResult($this->requestParams['start'])->setMaxResults($this->requestParams['length']);
        }

        return $this;
    }

    //-------------------------------------------------
    // Results
    //-------------------------------------------------

    /**
     * Query results before filtering.
     *
     * @param integer $rootEntityIdentifier
     *
     * @return int
     */
    private function getCountAllResults($rootEntityIdentifier)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('count(distinct ' . $this->tableName . '.' . $rootEntityIdentifier . ')');
        $qb->from($this->entity, $this->tableName);

        $this->setLeftJoins($qb);
        $this->setWhereAllCallback($qb);

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Query results after filtering.
     *
     * @param integer $rootEntityIdentifier
     * @param bool    $buildQuery
     *
     * @return int
     */
    private function getCountFilteredResults($rootEntityIdentifier, $buildQuery = true)
    {
        if (true === $buildQuery) {
            $qb = $this->em->createQueryBuilder();
            $qb->select('count(distinct ' . $this->tableName . '.' . $rootEntityIdentifier . ')');
            $qb->from($this->entity, $this->tableName);

            $this->setLeftJoins($qb);
            $this->setWhere($qb);
            $this->setWhereAllCallback($qb);

            return (int)$qb->getQuery()->getSingleScalarResult();
        } else {
            $qb = clone $this->qb;

            $qb
                // Reset orderBy part - where might be a special orderBy syntax previously set to order strings as numbers.
                ->resetDQLPart('orderBy')
                ->setFirstResult(null)
                ->setMaxResults(null)
                ->select('count(distinct ' . $this->tableName . '.' . $rootEntityIdentifier . ')');
            if (true === $this->isPostgreSQLConnection) {
                $qb->groupBy($this->tableName . '.' . $rootEntityIdentifier);

                return count($qb->getQuery()->getResult());
            } else {
                return (int)$qb->getQuery()->getSingleScalarResult();
            }
        }
    }

    /**
     * Constructs a Query instance.
     *
     * @return Query
     * @throws Exception
     */
    private function execute()
    {
        $query = $this->qb->getQuery();

        if (true === $this->configs['translation_query_hints']) {
            if (true === $this->doctrineExtensions) {
                $query->setHint(
                    \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
                    'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
                );

                $query->setHint(
                    \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                    $this->locale
                );

                $query->setHint(
                    \Gedmo\Translatable\TranslatableListener::HINT_FALLBACK,
                    1
                );
            } else {
                throw new Exception('execute(): "DoctrineExtensions" does not exist.');
            }
        }

        $query->setHydrationMode(Query::HYDRATE_ARRAY);

        return $query;
    }

    //-------------------------------------------------
    // Response
    //-------------------------------------------------

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getResponse($buildQuery = true, $outputWalkers = false)
    {
        if (false === $buildQuery) {
            $this->buildQuery();
        }

        $paginator = new Paginator($this->execute(), true);
        $paginator->setUseOutputWalkers($outputWalkers);
        $this->paginatorResults = $paginator->getIterator();

        $formatter = new DatatableFormatter($this);
        $formatter->runFormatter();

        $countAllResults = $this->datatableView->getOptions()->getCountAllResults();

        $outputHeader = array(
            'draw'            => (int)$this->requestParams['draw'],
            'recordsTotal'    => true === $countAllResults ? (int)$this->getCountAllResults($this->rootEntityIdentifier) : 0,
            'recordsFiltered' => (int)$this->getCountFilteredResults($this->rootEntityIdentifier, $buildQuery),
        );

        $fullOutput = array_merge($outputHeader, $formatter->getOutput());
        $fullOutput = $this->applyResponseCallbacks($fullOutput);

        $response = new JsonResponse();
        $response->setContent(json_encode($fullOutput));

        return $response;
    }

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function getDataForExport()
    {
        $this->setSelectFrom();
        $this->setLeftJoins($this->qb);
        $this->setWhereAllCallback($this->qb);
        $this->setOrderBy();

        return $this->execute()->getArrayResult();
    }
}
