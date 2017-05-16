<?php

namespace Algolia\AlgoliaSearch\Helper;

class Image extends \Magento\Catalog\Helper\Image
{
    protected $logger;

    public function __construct(\Magento\Framework\App\Helper\Context $context,
                                \Magento\Catalog\Model\Product\ImageFactory $productImageFactory,
                                \Magento\Framework\View\Asset\Repository $assetRepo,
                                \Magento\Framework\View\ConfigInterface $viewConfig,
                                Logger $logger)
    {
        parent::__construct($context, $productImageFactory, $assetRepo, $viewConfig);
        $this->logger = $logger;
    }

    public function getUrl()
    {
        try {
            $this->applyScheduledActions();

            $url = $this->_getModel()->getUrl();
        } catch (\Exception $e) {
            $this->logger->log($e->getMessage());
            $this->logger->log($e->getTraceAsString());

            $url = $this->getDefaultPlaceholderUrl();
        }

        $url = $this->removeProtocol($url);
        $url = $this->removeDoubleSlashes($url);

        return $url;
    }

    protected function initBaseFile()
    {
        $model = $this->_getModel();
        $baseFile = $model->getBaseFile();
        if (!$baseFile) {
            if ($this->getImageFile()) {
                $model->setBaseFile($this->getImageFile());
            } else {
                $model->setBaseFile($this->getProduct()->getImage());
            }
        }

        return $this;
    }

    public function removeProtocol($url)
    {
        return str_replace(['https://', 'http://'], '//', $url);
    }

    public function removeDoubleSlashes($url)
    {
        $url = str_replace('//', '/', $url);
        $url = '/'.$url;

        return $url;
    }
}
