<?php

namespace App\Logging;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use PhpNexus\Cwh\Handler\CloudWatch;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Formatter\JsonFormatter;

class CloudWatchLoggerFactory
{
    /**
     * Create a custom Monolog instance.
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        $sdkParams = $config["sdk"];
        $tags = $config["tags"] ?? [];
        $name = $config["name"] ?? 'cloudwatch';

        // Instantiate AWS SDK CloudWatch Logs Client
        $client = new CloudWatchLogsClient($sdkParams);

        // Log group name, will be created if none
        $groupName = $config["group_name"];

        // Log stream name, will be created if none
        $streamName = $config["stream_name"];

        // Days to keep logs, 14 by default. Set to `null` to allow indefinite retention.
        $retentionDays = $config["retention"];

        $batchSize = $config["batch_size"];

        // Instantiate handler (tags are optional)
        $handler = new CloudWatch($client, $groupName, $streamName, $retentionDays, $batchSize, $tags);

        // Optionally set the JsonFormatter to be able to access your log messages in a structured way
        $handler->setFormatter(new JsonFormatter());

        // Create a log channel
        $logger = new Logger($name);

        // Set handler
        $logger->pushHandler($handler);

        // Add tenant processor to include tenant_id in all log records
        $logger->pushProcessor(new TenantProcessor());

        return $logger;
    }
}
