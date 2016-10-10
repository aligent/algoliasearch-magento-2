<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class CategoryRow extends Category
{
    /** @var ResourceConnection */
    protected $resource;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CategoryHelper $categoryHelper
     * @param Data $helper
     * @param AlgoliaHelper $algoliaHelper
     * @param Queue $queue
     * @param ConfigHelper $configHelper
     * @param ManagerInterface $messageManager
     * @param ResourceConnection $resource
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CategoryHelper $categoryHelper,
        Data $helper,
        AlgoliaHelper $algoliaHelper,
        Queue $queue,
        ConfigHelper $configHelper,
        ManagerInterface $messageManager,
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
        parent::__construct(
            $storeManager, 
            $categoryHelper, 
            $helper, 
            $algoliaHelper, 
            $queue, 
            $configHelper,
            $messageManager
        );
    }

    public function execute($rowIds)
    {
        $categoryIds = $this->getCategoryIdsFromRowIds($rowIds);
        parent::execute($categoryIds);
    }

    protected function getCategoryIdsFromRowIds($rowIds)
    {
        /** AdapterInterface $connection */
        $connection = $this->resource->getConnection();

        $select = $connection->select()->from(
            $connection->getTableName('catalog_category_entity'),
            ['entity_id']
        )->where(
            'row_id IN (?)',
            $rowIds
        );

        return $connection->fetchCol($select);
    }
}
