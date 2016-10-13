<?php
namespace Sg\DatatablesBundle\Datatable\Query;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Sg\DatatablesBundle\Datatable\Column\AbstractColumn;
use Sg\DatatablesBundle\Datatable\View\DatatableViewInterface;
use Twig_Environment;
use Exception;

/**
 * Class AbstractDatatableQuery
 *
 * @package    Sg\DatatablesBundle\Datatable\Query
 * @implements DatatableQueryInterface
 * @abstract
 */
abstract class AbstractDatatableQuery implements DatatableQueryInterface
{
    /**
     * @var array
     */
    protected $requestParams;

    /**
     * @var DatatableViewInterface
     */
    protected $datatableView;

    /**
     * @var string
     */
    protected $entity;

    /**
     * @var boolean
     */
    protected $individualFiltering;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var ClassMetadata
     */
    protected $metadata;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var mixed
     */
    protected $rootEntityIdentifier;

    /**
     * @var array
     */
    protected $selectColumns;

    /**
     * @var array
     */
    protected $virtualColumns;

    /**
     * @var array
     */
    protected $joins;

    /**
     * @var array
     */
    protected $searchColumns;

    /**
     * @var array
     */
    protected $orderColumns;

    /**
     * @var array
     */
    protected $callbacks;

    /**
     * @var callable
     */
    protected $lineFormatter;

    /**
     * @var AbstractColumn[]
     */
    protected $columns;

    /**
     * @var array
     */
    protected $paginatorResults;

    /**
     * @var array
     */
    protected $configs;

    /**
     * @var Twig_Environment
     */
    protected $twig;

    /**
     * @var boolean
     */
    protected $imagineBundle;

    /**
     * @var boolean
     */
    protected $doctrineExtensions;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var boolean
     */
    protected $isPostgreSQLConnection;


    /**
     * AbstractDatatableQuery constructor
     *
     * @param array                  $requestParams
     * @param DatatableViewInterface $datatableView
     * @param array                  $configs
     * @param Twig_Environment       $twig
     * @param boolean                $imagineBundle
     * @param boolean                $doctrineExtensions
     * @param string                 $locale
     *
     * @throws Exception
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
        $this->requestParams = $requestParams;
        $this->datatableView = $datatableView;

        $this->individualFiltering = $this->datatableView->getOptions()->getIndividualFiltering();

        $this->entity = $this->datatableView->getEntity();
        $this->em = $this->datatableView->getEntityManager();
        $this->metadata = $this->getMetadata($this->entity);
        $this->tableName = $this->getTableName($this->metadata);
        $this->rootEntityIdentifier = $this->getIdentifier($this->metadata);

        $this->selectColumns = array();
        $this->virtualColumns = $datatableView->getColumnBuilder()->getVirtualColumns();
        $this->joins = array();
        $this->searchColumns = array();
        $this->orderColumns = array();
        $this->callbacks = array();
        $this->columns = $datatableView->getColumnBuilder()->getColumns();
        $this->paginator = array();

        $this->configs = $configs;

        $this->twig = $twig;
        $this->imagineBundle = $imagineBundle;
        $this->doctrineExtensions = $doctrineExtensions;
        $this->locale = $locale;
        $this->isPostgreSQLConnection = false;

        $this->setLineFormatter();
        $this->setupColumnArrays();

        $this->setupPostgreSQL();
    }


    //-------------------------------------------------
    // PostgreSQL
    //-------------------------------------------------

    /**
     * Setup PostgreSQL
     *
     * @return $this
     * @throws \Doctrine\ORM\ORMException
     */
    protected function setupPostgreSQL()
    {
        if ($this->em->getConnection()->getDriver()->getName() === 'pdo_pgsql') {
            $this->isPostgreSQLConnection = true;
            $this->em->getConfiguration()->addCustomStringFunction('CAST', '\Sg\DatatablesBundle\DQL\CastFunction');
        }

        return $this;
    }

    /**
     * Cast search field.
     *
     * @param string         $searchField
     * @param AbstractColumn $column
     *
     * @return string
     */
    protected function cast($searchField, AbstractColumn $column)
    {
        if ('datetime' === $column->getAlias() || 'boolean' === $column->getAlias() || 'column' === $column->getAlias()) {
            return 'CAST(' . $searchField . ' AS text)';
        }

        return $searchField;
    }


    //-------------------------------------------------
    // Setup query
    //-------------------------------------------------

    /**
     * Setup column arrays.
     *
     * @author stwe <https://github.com/stwe>
     * @author Gaultier Boniface <https://github.com/wysow>
     * @author greg-avanim <https://github.com/greg-avanim>
     *
     * @return $this
     */
    protected function setupColumnArrays()
    {
        /* Example:
              SELECT
                  partial fos_user.{id},
                  partial posts_comments.{title,id},
                  partial posts.{id,title}
              FROM
                  AppBundle\Entity\User fos_user
              LEFT JOIN
                  fos_user.posts posts
              LEFT JOIN
                  posts.comments posts_comments
              ORDER BY
                  posts_comments.title asc
         */

        $this->selectColumns[$this->tableName][] = $this->rootEntityIdentifier;

        foreach ($this->columns as $key => $column) {
            /** @var AbstractColumn $column */
            $data = $column->getDql();

            $currentPart = $this->tableName;
            $currentAlias = $currentPart;

            $metadata = $this->metadata;

            if (true === $this->isSelectColumn($data)) {
                $parts = explode('\\\\.', $data);

                if (count($parts) > 1) {
                    // If it's an embedded class, we can query without JOIN
                    if (array_key_exists($parts[0], $metadata->embeddedClasses)) {
                        $this->selectColumns[$currentAlias][] = str_replace('\\', '', $data);
                        $this->addSearchOrderColumn($key, $currentAlias, $data);
                        continue;
                    }
                } else {
                    $parts = explode('.', $data);

                    while (count($parts) > 1) {
                        $previousPart = $currentPart;
                        $previousAlias = $currentAlias;

                        $currentPart = array_shift($parts);
                        $currentAlias = ($previousPart == $this->tableName ? '' : $previousPart . '_') . $currentPart; // This condition keeps stable queries callbacks

                        if (!array_key_exists($previousAlias . '.' . $currentPart, $this->joins)) {
                            $this->joins[$previousAlias . '.' . $currentPart] = $currentAlias;
                        }

                        $metadata = $this->setIdentifierFromAssociation($currentAlias, $currentPart, $metadata);
                    }

                    $this->selectColumns[$currentAlias][] = $this->getIdentifier($metadata);
                    $this->selectColumns[$currentAlias][] = $parts[0];
                    $this->addSearchOrderColumn($key, $currentAlias, $parts[0]);
                }
            } else {
                $this->orderColumns[] = null;
                $this->searchColumns[] = null;
            }
        }

        return $this;
    }


    //-------------------------------------------------
    // Callbacks
    //-------------------------------------------------

    /**
     * Apply response callbacks.
     *
     * @param array $data
     *
     * @return array
     */
    protected function applyResponseCallbacks(array $data)
    {
        if (!empty($this->callbacks['Response'])) {
            foreach ($this->callbacks['Response'] as $callback) {
                $data = $callback($data, $this);
            }
        }

        return $data;
    }


    //-------------------------------------------------
    // Helper
    //-------------------------------------------------

    /**
     * Add search/order columns.
     *
     * @param integer $key
     * @param string  $columnTableName
     * @param string  $data
     */
    protected function addSearchOrderColumn($key, $columnTableName, $data)
    {
        $column = $this->columns[$key];

        true === $column->getOrderable() ? $this->orderColumns[] = $columnTableName . '.' . $data : $this->orderColumns[] = null;
        true === $column->getSearchable() ? $this->searchColumns[] = $columnTableName . '.' . $data : $this->searchColumns[] = null;
    }

    /**
     * Get metadata.
     *
     * @param string $entity
     *
     * @return ClassMetadata
     * @throws Exception
     */
    protected function getMetadata($entity)
    {
        try {
            $metadata = $this->em->getClassMetadata($entity);
        } catch (MappingException $e) {
            throw new Exception('getMetadata(): Given object ' . $entity . ' is not a Doctrine Entity.');
        }

        return $metadata;
    }

    /**
     * Get table name.
     *
     * @param ClassMetadata $metadata
     *
     * @return string
     */
    protected function getTableName(ClassMetadata $metadata)
    {
        return strtolower($metadata->getTableName());
    }

    /**
     * Get identifier.
     *
     * @param ClassMetadata $metadata
     *
     * @return mixed
     */
    protected function getIdentifier(ClassMetadata $metadata)
    {
        $identifiers = $metadata->getIdentifierFieldNames();

        return array_shift($identifiers);
    }

    /**
     * Set identifier from association.
     *
     * @author Gaultier Boniface <https://github.com/wysow>
     *
     * @param string|array       $association
     * @param string             $key
     * @param ClassMetadata|null $metadata
     *
     * @return ClassMetadata
     * @throws Exception
     */
    protected function setIdentifierFromAssociation($association, $key, $metadata = null)
    {
        if (null === $metadata) {
            $metadata = $this->metadata;
        }

        $targetEntityClass = $metadata->getAssociationTargetClass($key);
        $targetMetadata = $this->getMetadata($targetEntityClass);
        $this->selectColumns[$association][] = $this->getIdentifier($targetMetadata);

        return $targetMetadata;
    }

    /**
     * Is select column.
     *
     * @param string $data
     *
     * @return bool
     */
    protected function isSelectColumn($data)
    {
        if (null !== $data && !in_array($data, $this->virtualColumns)) {
            return true;
        }

        return false;
    }

    /**
     * Is search column.
     *
     * @param AbstractColumn $column
     *
     * @return bool
     */
    protected function isSearchColumn(AbstractColumn $column)
    {
        if (false === $this->configs['search_on_non_visible_columns']) {
            if (null !== $column->getDql() && true === $column->getSearchable() && true === $column->getVisible()) {
                return true;
            }
        } else {
            if (null !== $column->getDql() && true === $column->getSearchable()) {
                return true;
            }
        }

        return false;
    }


    //-------------------------------------------------
    // Callbacks
    //-------------------------------------------------

    /**
     * @inheritdoc
     *
     * @throws Exception
     */
    public function addResponseCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new Exception(sprintf("Callable expected and %s given", gettype($callback)));
        }

        $this->callbacks['Response'][] = $callback;

        return $this;
    }


    //-------------------------------------------------
    // Getters
    //-------------------------------------------------

    /**
     * @inheritdoc
     */
    public function getLineFormatter()
    {
        return $this->lineFormatter;
    }

    /**
     * @inheritdoc
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @inheritdoc
     */
    public function getPaginatorResults()
    {
        return $this->paginatorResults;
    }

    /**
     * @inheritdoc
     */
    public function getTwig()
    {
        return $this->twig;
    }

    /**
     * @inheritdoc
     */
    public function getImagineBundle()
    {
        return $this->imagineBundle;
    }

    /**
     * @inheritdoc
     */
    public function setLineFormatter()
    {
        $this->lineFormatter = $this->datatableView->getLineFormatter();

        return $this;
    }
}
