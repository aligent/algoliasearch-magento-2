<?php
namespace Algolia\AlgoliaSearch\Helper;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Search\Request\Filter\Range as RangeFilter;
use Magento\Framework\Search\Request\Filter\Term as TermFilter;
use Magento\Framework\Search\Request\Query\BoolExpression;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Store\Model\StoreManagerInterface;

class FacetHelper extends AbstractHelper
{
    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * FacetHelper constructor.
     * @param Context $context
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    public function getAlgoliaParams(QueryInterface $query, $storeId = null)
    {
        $result = [];
        if ($query->getType() == QueryInterface::TYPE_BOOL) {
            /** @var BoolExpression $query */
            foreach ($query->getMust() as $must) {
                $result = array_merge($result, $this->getAlgoliaParams($must, $storeId));
            }

            foreach ($query->getMustNot() as $mustNot) {
                //TODO: Add not searches
            }

            foreach ($query->getShould() as $should) {
                $result = array_merge($result, $this->getAlgoliaParams($should, $storeId));
            }

        } elseif ($query->getType() == QueryInterface::TYPE_MATCH) {
            //TODO: 
        } elseif ($query->getType() == QueryInterface::TYPE_FILTER) {
            /** @var FilterQuery $query */
            $reference = $query->getReference();
            if ($query->getReferenceType() == \Magento\Framework\Search\Request\Query\Filter::REFERENCE_FILTER) {

                if ($reference instanceof TermFilter) {
                    $value = $reference->getValue();

                    if ($reference->getField() === 'category_ids') {
                        $result['facetFilters'][] = $this->getCategoryFacetFilter($value, $storeId);
                    } elseif (is_array($reference->getValue())) {
                        //TODO: Add a way to perform disjunctive searches
//                        if (array_key_exists('in', $value)) {
//                            foreach ($value['in'] as $individualValue) {
//                                $result['facetFilters'][] = $reference->getField() . ":$individualValue";
//                            }
//                            $result['disjunctiveFacets'][] = $reference->getField();
//                        }
                    } else {
                        $result['facetFilters'][] = $reference->getField() . ":$value";
                    }
                } elseif ($reference instanceof RangeFilter) {
                    if ($reference->getField() === 'price') {
                        if ($reference->getFrom() !== null) {
                            $result['numericFilters'][] = $this->getPriceFacetFilter($reference->getFrom(), '>=', $storeId);
                        }
                        if ($reference->getTo() !== null) {
                            $result['numericFilters'][] = $this->getPriceFacetFilter($reference->getTo(), '<', $storeId);
                        }
                    }
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

        return "categories.level$level:$facet";
    }

    public function getPriceFacetFilter($price, $operation, $storeId)
    {
        $key = 'price.' . $this->storeManager->getStore($storeId)->getCurrentCurrencyCode() . '.default';
        return "$key$operation$price";

    }
}