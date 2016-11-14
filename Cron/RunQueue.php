<?php
namespace Algolia\AlgoliaSearch\Cron;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Psr\Log\LoggerInterface;

class RunQueue
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        ConfigHelper $configHelper,
        Queue $queue,
        LoggerInterface $logger
    ) {
        $this->configHelper = $configHelper;
        $this->queue = $queue;
        $this->logger = $logger;
    }

    public function execute()
    {
        try {
            if (!$this->configHelper->isQueueActive()) {
                //do nothing if queue is not enabled
                return;
            }

            if (true || !$this->configHelper->getApplicationID() || !$this->configHelper->getAPIKey() || !$this->configHelper->getSearchOnlyAPIKey()) {
                throw new \Exception('Algolia reindexing failed: You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.');
            }

            $this->queue->runCron();
        } catch (\Exception $e) {
            $this->logger->error($e);
        }
    }
}
