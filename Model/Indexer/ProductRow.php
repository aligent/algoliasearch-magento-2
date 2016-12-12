<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductRow extends Product
{
    /** @var ResourceConnection */
    protected $resource;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ProductHelper $productHelper
     * @param Data $helper
     * @param AlgoliaHelper $algoliaHelper
     * @param ConfigHelper $configHelper
     * @param Queue $queue
     * @param ManagerInterface $messageManager
     * @param ResourceConnection $resource
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ProductHelper $productHelper,
        Data $helper,
        AlgoliaHelper $algoliaHelper,
        ConfigHelper $configHelper,
        Queue $queue,
        ManagerInterface $messageManager,
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
        parent::__construct(
            $storeManager, 
            $productHelper, 
            $helper, 
            $algoliaHelper, 
            $configHelper, 
            $queue,
            $messageManager
        );
    }

    public function execute($rowIds)
    {
        $productIds = $this->getProductIdsFromRowIds($rowIds);
        parent::execute($productIds);
    }

    public function executeList(array $ids)
    {
        $productIds = $this->getProductIdsFromRowIds($ids);
        parent::execute($productIds);
    }

    public function executeRow($id)
    {
        $productIds = $this->getProductIdsFromRowIds([$id]);
        $this->execute($productIds);
    }

    protected function getProductIdsFromRowIds($rowIds)
    {
        /** AdapterInterface $connection */
        $connection = $this->resource->getConnection();

        $select = $connection->select()->from(
            $connection->getTableName('catalog_product_entity'),
            ['entity_id']
        )->where(
            'row_id IN (?)',
            $rowIds
        );
        
        return $connection->fetchCol($select);
    }
}
