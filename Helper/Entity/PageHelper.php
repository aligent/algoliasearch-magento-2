<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Magento\Cms\Model\Page;

class PageHelper extends BaseHelper
{
    protected function getIndexNameSuffix()
    {
        return '_pages';
    }

    public function getIndexSettings($storeId)
    {
        return [
            'searchableAttributes' => ['unordered(slug)', 'unordered(name)', 'unordered(content)'],
            'attributesToSnippet'  => ['content:7'],
        ];
    }

    public function getPages($storeId)
    {
        /** @var \Magento\Cms\Model\Page $pageModel */
        $pageModel = $this->objectManager->create('\Magento\Cms\Model\Page');

        $magento_pages = $pageModel->getCollection()
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1);

        $excluded_pages = array_values($this->config->getExcludedPages());

        foreach ($excluded_pages as &$excluded_page) {
            $excluded_page = $excluded_page['attribute'];
        }

        $pages = [];

        /** @var Page $page */
        foreach ($magento_pages as $page) {
            if (in_array($page->getIdentifier(), $excluded_pages)) {
                continue;
            }

            $pageObject = [];

            $pageObject['slug'] = $page->getIdentifier();
            $pageObject['name'] = $page->getTitle();

            $page->setStoreId($storeId);

            if (!$page->getId()) {
                continue;
            }

            $content = $page->getContent();
            if ($this->config->getRenderTemplateDirectives()) {
                $content = $this->filterProvider->getPageFilter()->filter($content);
            }

            $pageObject['objectID'] = $page->getId();
            $pageObject['url'] = $this->getStoreUrl($storeId)->getUrl(null, ['_direct' => $page->getIdentifier(), '_secure' => $this->config->useSecureUrlsInFrontend($storeId)]);

            $pageObject['content'] = $this->strip($content, array('script', 'style'));

            $pages[] = $pageObject;
        }

        return $pages;
    }
}
