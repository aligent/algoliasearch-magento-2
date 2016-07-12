<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Algolia\AlgoliaSearch\Adapter;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Select;
use Magento\Framework\Search\Adapter\Mysql\Aggregation\Builder as AggregationBuilder;
use Magento\Framework\Search\Adapter\Mysql\Mapper;
use Magento\Framework\Search\Adapter\Mysql\ResponseFactory;
use Magento\Framework\Search\Adapter\Mysql\TemporaryStorageFactory;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\CatalogSearch\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Request\Http;
use Algolia\AlgoliaSearch\Helper\Data as AlgoliaHelper;

/**
 * MySQL Search Adapter
 */
class Algolia implements AdapterInterface
{
    /**
     * Mapper instance
     *
     * @var Mapper
     */
    protected $mapper;

    /**
     * Response Factory
     *
     * @var ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * @var AggregationBuilder
     */
    private $aggregationBuilder;

    /**
     * @var TemporaryStorageFactory
     */
    private $temporaryStorageFactory;
    /**
     * @var ConfigHelper
     */
    protected $config;
    /**
     * @var Data
     */
    protected $catalogSearchHelper;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var AlgoliaHelper
     */
    protected $algoliaHelper;

    protected $request;

    /**
     * @var DocumentFactory
     */
    protected $documentFactory;

    /**
     * @var AttributeValueFactory\
     */
    protected $attributeValueFactory;

    /**
     * @param Mapper $mapper
     * @param ResponseFactory $responseFactory
     * @param ResourceConnection $resource
     * @param AggregationBuilder $aggregationBuilder
     * @param TemporaryStorageFactory $temporaryStorageFactory
     * @param ConfigHelper $config
     * @param Data $catalogSearchHelper
     * @param StoreManagerInterface $storeManager
     * @param AlgoliaHelper $algoliaHelper
     * @param Http $request
     * @param DocumentFactory $documentFactory
     * @param AttributeValueFactory $attributeValueFactory
     */
    public function __construct(
        Mapper $mapper,
        ResponseFactory $responseFactory,
        ResourceConnection $resource,
        AggregationBuilder $aggregationBuilder,
        TemporaryStorageFactory $temporaryStorageFactory,
        ConfigHelper $config,
        Data $catalogSearchHelper,
        StoreManagerInterface $storeManager,
        AlgoliaHelper $algoliaHelper,
        Http $request,
        DocumentFactory $documentFactory,
        AttributeValueFactory $attributeValueFactory
    ) {
        $this->mapper = $mapper;
        $this->responseFactory = $responseFactory;
        $this->resource = $resource;
        $this->aggregationBuilder = $aggregationBuilder;
        $this->temporaryStorageFactory = $temporaryStorageFactory;
        $this->config = $config;
        $this->catalogSearchHelper = $catalogSearchHelper;
        $this->storeManager = $storeManager;
        $this->algoliaHelper = $algoliaHelper;
        $this->request = $request;
        $this->documentFactory = $documentFactory;
        $this->attributeValueFactory = $attributeValueFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function query(RequestInterface $request)
    {
        $query = $this->catalogSearchHelper->getEscapedQueryText();

        $storeId = $this->storeManager->getStore()->getId();
        $temporaryStorage = $this->temporaryStorageFactory->create();

        $documents = [];
        $table = null;

        if (!$this->config->getApplicationID($storeId) || !$this->config->getAPIKey($storeId) || $this->config->isEnabledFrontEnd($storeId) === false ||
            ($this->request->getControllerName() === 'category' && $this->config->replaceCategories($storeId) == false)
        ) {
            $query = $this->mapper->buildQuery($request);
            $table = $temporaryStorage->storeDocumentsFromSelect($query);
            $documents = $this->getDocuments($table);
        } else {
            $algolia_query = $query !== '__empty__' ? $query : '';
            //If instant search is on, do not make a search query unless SEO request is set to 'Yes'
            if (!$this->config->isInstantEnabled($storeId) || $this->config->makeSeoRequest($storeId)) {
                $documents = $this->algoliaHelper->getSearchResult($algolia_query, $storeId);
            }

            $documents_to_store = array_map(function ($document) {

                $scoreAttribute = $this->attributeValueFactory->create();
                $scoreAttribute->setAttributeCode('score');
                $scoreAttribute->setValue($document['score']);

                $documentModel = $this->documentFactory->create();
                $documentModel->setData('id', $document['entity_id']);
                $documentModel->setCustomAttribute('score', $scoreAttribute);

                return $documentModel;
            }, $documents);

            $table = $temporaryStorage->storeApiDocuments($documents_to_store);
        }

        $aggregations = $this->aggregationBuilder->build($request, $table);

        $response = [
            'documents'    => $documents,
            'aggregations' => $aggregations,
        ];

        return $this->responseFactory->create($response);
    }

    /**
     * Executes query and return raw response
     *
     * @param Table $table
     *
     * @return array
     *
     * @throws \Zend_Db_Exception
     */
    private function getDocuments(Table $table)
    {
        $connection = $this->getConnection();
        $select = $connection->select();
        $select->from($table->getName(), ['entity_id', 'score']);

        return $connection->fetchAssoc($select);
    }

    /**
     * @return false|\Magento\Framework\DB\Adapter\AdapterInterface
     */
    private function getConnection()
    {
        return $this->resource->getConnection();
    }
}
