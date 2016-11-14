<?php
namespace Algolia\AlgoliaSearch\Cron;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Helper\Logger;

class RunQueue
{
    protected $configHelper;
    protected $queue;
    protected $logger;

    public function __construct(
        ConfigHelper $configHelper,
        Queue $queue,
        Logger $logger
    ) {
        $this->configHelper = $configHelper;
        $this->queue = $queue;
        $this->logger = $logger;
    }

    public function execute()
    {
        if (!$this->configHelper->isQueueActive()) {
            return;
        }

        if (!$this->configHelper->getApplicationID() || !$this->configHelper->getAPIKey() || !$this->configHelper->getSearchOnlyAPIKey()) {
            $errorMessage = 'Algolia reindexing failed: You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';
            $this->logger->log($errorMessage);
            return;
        }

        $this->queue->runCron();
    }
}
