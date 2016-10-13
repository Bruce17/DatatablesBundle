<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Datatable\Data;

use Sg\DatatablesBundle\Datatable\Query as DatatableQuery;
use Sg\DatatablesBundle\Datatable\View\DatatableViewInterface;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Twig_Environment;

/**
 * Class DatatableDataManager
 *
 * @package Sg\DatatablesBundle\Datatable\Data
 */
class DatatableDataManager
{
    /**
     * The request.
     *
     * @var Request
     */
    private $request;

    /**
     * The Twig Environment service.
     *
     * @var Twig_Environment
     */
    private $twig;

    /**
     * Configuration settings.
     *
     * @var array
     */
    private $configs;

    /**
     * True if the LiipImagineBundle is installed.
     *
     * @var boolean
     */
    private $imagineBundle;

    /**
     * True if GedmoDoctrineExtensions installed.
     *
     * @var boolean
     */
    private $doctrineExtensions;

    /**
     * The locale.
     *
     * @var string
     */
    private $locale;

    //-------------------------------------------------
    // Ctor.
    //-------------------------------------------------

    /**
     * Ctor.
     *
     * @param RequestStack     $requestStack
     * @param Twig_Environment $twig
     * @param array            $configs
     * @param array            $bundles
     */
    public function __construct(RequestStack $requestStack, Twig_Environment $twig, array $configs, array $bundles)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->twig = $twig;
        $this->configs = $configs;
        $this->imagineBundle = false;
        $this->doctrineExtensions = false;

        if (true === class_exists('Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker')) {
            $this->doctrineExtensions = true;
        }

        if (true === array_key_exists('LiipImagineBundle', $bundles)) {
            $this->imagineBundle = true;
        }

        $this->locale = $this->request->getLocale();
    }

    //-------------------------------------------------
    // Public
    //-------------------------------------------------

    /**
     * Get query.
     *
     * @param DatatableViewInterface $datatableView
     *
     * @return DatatableQuery
     */
    public function getQueryFrom(DatatableViewInterface $datatableView)
    {
        $type = $datatableView->getAjax()->getType();
        $parameterBag = null;

        if ('GET' === strtoupper($type)) {
            $parameterBag = $this->request->query;
        }

        if ('POST' === strtoupper($type)) {
            $parameterBag = $this->request->request;
        }

        $nativeQueryMethod = $datatableView->getOptions()->getNativeQueryMethod();

        if (empty($nativeQueryMethod)) {
            $query = new DatatableQuery\DatatableQuery(
                $parameterBag->all(),
                $datatableView,
                $this->configs,
                $this->twig,
                $this->imagineBundle,
                $this->doctrineExtensions,
                $this->locale
            );
        } else {
            $query = new DatatableQuery\DatatableNativeQuery(
                $parameterBag->all(),
                $datatableView,
                $this->configs,
                $this->twig,
                $this->imagineBundle,
                $this->doctrineExtensions,
                $this->locale
            );

            // Fetch native query from the entities repository.
            //TODO: add check if method exists
            $nq = $datatableView->getEntityManager()
                ->getRepository($datatableView->getEntity())
                ->{$nativeQueryMethod}();

            $query->setNativeQuery($nq);
        }

        return $query;
    }
}
