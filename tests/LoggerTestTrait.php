<?php

namespace EnMarche\MailerBundle\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

trait LoggerTestTrait
{
    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    private function expectsLog(string $level, ?string $log, array $context = null)
    {
        if ($log) {
            $expect = $this->logger->expects($this->once())
                ->method($level)
            ;
            if (is_array($context)) {
                $expect->with($log, $context);
            } else {
                $expect->with($log);
            }
        } else {
            $this->logger->expects($this->never())
                ->method($level)
            ;
        }
    }
}
