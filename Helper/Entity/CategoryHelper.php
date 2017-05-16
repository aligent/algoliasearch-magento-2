<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Helper\Image;
use Magento\Catalog\Model\Category;
use Magento\Framework\DataObject;

class CategoryHelper extends BaseHelper
{
    protected static $_categoryAttributes;
    protected static $_rootCategoryId = -1;

    protected function getIndexNameSuffix()
    {
        return '_categories';
    }

    public function getIndexSettings($storeId)
    {
        $searchableAttributes = [];
        $unretrievableAttributes = [];

        foreach ($this->config->getCategoryAdditionalAttributes($storeId) as $attribute) {
            if ($attribute['searchable'] == '1') {
                if ($attribute['order'] == 'ordered') {
                    $searchableAttributes[] = $attribute['attribute'];
                } else {
                    $searchableAttributes[] = 'unordered(' . $attribute['attribute'] . ')';
                }
            }

            if ($attribute['retrievable'] != '1') {
                $unretrievableAttributes[] = $attribute['attribute'];
            }
        }

        $customRankings = $this->config->getCategoryCustomRanking($storeId);

        $customRankingsArr = [];

        foreach ($customRankings as $ranking) {
            $customRankingsArr[] = $ranking['order'] . '(' . $ranking['attribute'] . ')';
        }

        // Default index settings
        $indexSettings = [
            'searchableAttributes'    => array_values(array_unique($searchableAttributes)),
            'customRanking'           => $customRankingsArr,
            'unretrievableAttributes' => $unretrievableAttributes,
        ];

        // Additional index settings from event observer
        $transport = new DataObject($indexSettings);
        $this->eventManager->dispatch('algolia_index_settings_prepare', [
                'store_id'       => $storeId,
                'index_settings' => $transport,
            ]
        );
        $indexSettings = $transport->getData();

        $indexSettings = $this->algoliaHelper->mergeSettings($this->getIndexName($storeId), $indexSettings);

        return $indexSettings;
    }

    public function getAdditionalAttributes($storeId = null)
    {
        return $this->config->getCategoryAdditionalAttributes($storeId);
    }

    public function getCategoryCollectionQuery($storeId, $categoryIds = null)
    {
        $storeRootCategoryPath = sprintf('%d/%d', $this->getRootCategoryId(), $this->storeManager->getStore($storeId)->getRootCategoryId());

        /* @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $categories = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Category\Collection');

        $unserializedCategorysAttrs = $this->getAdditionalAttributes($storeId);

        $additionalAttr = [];

        foreach ($unserializedCategorysAttrs as $attr) {
            $additionalAttr[] = $attr['attribute'];
        }

        $categories
            ->addPathFilter($storeRootCategoryPath)
            ->addNameToResult()
            ->addUrlRewriteToResult()
            ->addIsActiveFilter()
            ->setStoreId($storeId)
            ->addAttributeToSelect(array_merge(['name'], $additionalAttr))
            ->addFieldToFilter('level', ['gt' => 1]);

        if (!$this->config->showCatsNotIncludedInNavigation()) {
            $categories->addAttributeToFilter('include_in_menu', 1);
        }

        if ($categoryIds) {
            $categories->addFieldToFilter('entity_id', ['in' => $categoryIds]);
        }

        return $categories;
    }

    public function getAllAttributes()
    {
        if (is_null(self::$_categoryAttributes)) {
            self::$_categoryAttributes = [];

            $allAttributes = $this->eavConfig->getEntityAttributeCodes('catalog_category');

            $categoryAttributes = array_merge($allAttributes, ['product_count']);

            $excludedAttributes = [
                'all_children', 'available_sort_by', 'children', 'children_count', 'custom_apply_to_products',
                'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'custom_use_parent_settings',
                'default_sort_by', 'display_mode', 'filter_price_range', 'global_position', 'image', 'include_in_menu', 'is_active',
                'is_always_include_in_menu', 'is_anchor', 'landing_page', 'level', 'lower_cms_block',
                'page_layout', 'path_in_store', 'position', 'small_image', 'thumbnail', 'url_key', 'url_path',
                'visible_in_menu', ];

            $categoryAttributes = array_diff($categoryAttributes, $excludedAttributes);

            foreach ($categoryAttributes as $attributeCode) {
                self::$_categoryAttributes[$attributeCode] = $this->eavConfig->getAttribute('catalog_category', $attributeCode)->getFrontendLabel();
            }
        }

        return self::$_categoryAttributes;
    }

    public function getObject(Category $category)
    {
        /** @var $productCollection Mage_Catalog_Model_Resource_Product_Collection */
        $productCollection = $category->getProductCollection();
        $productCollection = $productCollection->addMinimalPrice();

        $category->setProductCount($productCollection->getSize());

        $transport = new DataObject();
        $this->eventManager->dispatch('algolia_category_index_before', ['category' => $category, 'custom_data' => $transport]);
        $customData = $transport->getData();

        $storeId = $category->getStoreId();
        $category->getUrlInstance()->setStore($storeId);
        $path = '';
        foreach ($category->getPathIds() as $categoryId) {
            if ($path != '') {
                $path .= ' / ';
            }
            $path .= $this->getCategoryName($categoryId, $storeId);
        }

        $imageUrl = null;
        try {
            $imageUrl = $category->getImageUrl();
        } catch (\Exception $e) { /* no image, no default: not fatal */
        }

        $data = [
            'objectID'      => $category->getId(),
            'name'          => $category->getName(),
            'path'          => $path,
            'level'         => $category->getLevel(),
            'url'           => $this->getUrl($category),
            'include_in_menu' => $category->getIncludeInMenu(),
            '_tags'         => ['category'],
            'popularity'    => 1,
            'product_count' => $category->getProductCount(),
        ];

        if (!empty($imageUrl)) {
            /** @var Image $imageHelper */
            $imageHelper = $this->objectManager->create('Algolia\AlgoliaSearch\Helper\Image');

            $imageUrl = $imageHelper->removeProtocol($imageUrl);
            $imageUrl = $imageHelper->removeDoubleSlashes($imageUrl);

            $data['image_url'] = $imageUrl;
        }

        foreach ($this->config->getCategoryAdditionalAttributes($storeId) as $attribute) {
            $value = $category->getData($attribute['attribute']);

            $attribute_resource = $category->getResource()->getAttribute($attribute['attribute']);

            if ($attribute_resource) {
                $value = $attribute_resource->getFrontend()->getValue($category);
            }

            if (isset($data[$attribute['attribute']])) {
                $value = $data[$attribute['attribute']];
            }

            if ($value) {
                $data[$attribute['attribute']] = $value;
            }
        }

        $data = array_merge($data, $customData);

        foreach ($data as &$data0) {
            $data0 = $this->try_cast($data0);
        }

        return $data;
    }

    public function getRootCategoryId()
    {
        if (-1 === self::$_rootCategoryId) {
            $collection = $this->objectManager->create('Magento\Catalog\Model\ResourceModel\Category\Collection');
            $collection->addFieldToFilter('parent_id', 0);
            $collection->getSelect()->limit(1);
            $rootCategory = $collection->getFirstItem();
            self::$_rootCategoryId = $rootCategory->getId();
        }

        return self::$_rootCategoryId;
    }

    private function getUrl(Category $category)
    {
        $categoryUrl = $category->getUrl();

        if ($this->config->useSecureUrlsInFrontend($category->getStoreId()) === false) {
            return $categoryUrl;
        }

        $unsecureBaseUrl = $category->getUrlInstance()->getBaseUrl(['_secure' => false]);
        $secureBaseUrl = $category->getUrlInstance()->getBaseUrl(['_secure' => true]);

        if (strpos($categoryUrl, $unsecureBaseUrl) === 0) {
            return substr_replace($categoryUrl, $secureBaseUrl, 0, mb_strlen($unsecureBaseUrl));
        }

        return $categoryUrl;
    }
}
