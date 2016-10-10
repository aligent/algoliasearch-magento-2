<?php
namespace Algolia\AlgoliaSearch\Helper;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Filter\Range as RangeFilter;
use Magento\Framework\Search\Request\Filter\Term as TermFilter;
use Magento\Framework\Search\Request\Query\BoolExpression;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Store\Model\StoreManagerInterface;

class FacetHelper extends AbstractHelper
{
    /** Max to use if numeric filter has no explicit max set */
    const MAX_RANGE = 99999999;

    protected $attributes = [];

    protected $categories = [];

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var AttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * FacetHelper constructor.
     * @param Context $context
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param AttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        Context $context,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->attributeRepository = $attributeRepository;
        parent::__construct($context);
    }

    protected function getAttributeText($attributeCode, $attributeValue)
    {
        if (!array_key_exists($attributeCode, $this->attributes)) {
            try {
                $attribute = $this->attributeRepository->get('catalog_product', $attributeCode);
                $this->attributes[$attributeCode] = $attribute;
            } catch (NoSuchEntityException $e) {
                return $attributeValue;
            }
        }
        $attribute = $this->attributes[$attributeCode];
        if ($attribute && $attribute->getSource()) {
            $text = $attribute->getSource()->getOptionText($attributeValue);
            return $text !== false ? $text : $attributeValue;
        }
        return $attributeValue;
    }

    public function getAlgoliaParams(QueryInterface $query, $storeId = null)
    {
        $result = [];
        if ($query->getType() == QueryInterface::TYPE_BOOL) {
            /** @var BoolExpression $query */
            foreach ($query->getMust() as $must) {
                $result = array_merge_recursive($result, $this->getAlgoliaParams($must, $storeId));
            }

            foreach ($query->getMustNot() as $mustNot) {
                //TODO: Add not searches
            }

            foreach ($query->getShould() as $should) {
                $result = array_merge_recursive($result, $this->getAlgoliaParams($should, $storeId));
            }

        } elseif ($query->getType() == QueryInterface::TYPE_MATCH) {
            //TODO: Figure out what a match query is
        } elseif ($query->getType() == QueryInterface::TYPE_FILTER) {
            /** @var FilterQuery $query */
            $reference = $query->getReference();
            if ($query->getReferenceType() == \Magento\Framework\Search\Request\Query\Filter::REFERENCE_FILTER) {

                if ($reference instanceof TermFilter) {
                    $value = $reference->getValue();

                    if ($reference->getField() === 'category_ids') {
                        $result['filters'][] = $this->getCategoryFacetFilter($value, $storeId);
                    } elseif (is_array($value)) {
                        if (array_key_exists('in', $value)) {
                            $disjunctiveFilter = [];
                            foreach ($value['in'] as $individualValue) {
                                $valueText = $this->getAttributeText($reference->getField(), $individualValue);
                                $disjunctiveFilter[] = $reference->getField() . ":\"$valueText\"";
                            }
                            $result['filters'][] = implode(" OR ", $disjunctiveFilter);
                        }
                    } else {
                        $result['filters'][] = $reference->getField() . ":\"$value\"";
                    }
                } elseif ($reference instanceof RangeFilter) {
                    $field = $reference->getField() == 'price' ? $this->getPriceFacetFilter($storeId) : $reference->getField();
                    $result['filters'][] = $field . ":" . ($reference->getFrom() ?: 0) . " TO " . ($reference->getTo() ?: self::MAX_RANGE);
                }
            }
        }

        return $result;
    }

    /**
     * @param $categoryId
     * @param $storeId
     * @return string
     */
    public function getCategoryFacetFilter($categoryId, $storeId)
    {
        $key = "$categoryId-$storeId";

        if (!array_key_exists($key, $this->categories)) {
            /** @var CategoryInterface $category */
            $category = $this->categoryRepository->get($categoryId, $storeId);
            $path = $category->getPath();
            $facetArray = [];

            $pathArray = explode('/', $path);

            //Remove everything up to the root category for the store
            $rootCategoryId = $this->storeManager->getStore($storeId)->getRootCategoryId();
            $rootIndex = array_search($rootCategoryId, $pathArray);
            if ($rootIndex !== false) {
                $pathArray = array_slice($pathArray, $rootIndex + 1);
            }

            foreach ($pathArray as $pathPart) {
                $category = $this->categoryRepository->get($pathPart, $storeId);
                $facetArray[] = $category->getName();
            }

            //The actual level in Algolia is the level of the category underneath the root category, starting with 0
            //E.g. a category directly underneath the root category is level 0
            $level = count($facetArray) - 1;

            //Algolia categories stored with separator of ' /// '
            $facet = implode(' /// ', $facetArray);

            $this->categories[$key] = "categories.level$level:\"$facet\"";
        }

        return $this->categories[$key];
    }

    public function getPriceFacetFilter($storeId)
    {
        return 'price.' . $this->storeManager->getStore($storeId)->getCurrentCurrencyCode() . '.default';
    }
}