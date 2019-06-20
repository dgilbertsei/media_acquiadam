<?php

namespace Drupal\Tests\media_acquiadam\Traits;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

trait AcquiadamLoggerFactoryTrait {

  /**
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\Core\Logger\LoggerChannelFactoryInterface
   *   A mock LoggerChannelFactoryInstance with a media_acquiadam channel.
   */
  protected function getLoggerFactoryStub() {
    $logger_channel = $this->getMockBuilder(LoggerChannelInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $logger_factory = $this->getMockBuilder(LoggerChannelFactoryInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $logger_factory->method('get')
      ->with('media_acquiadam')
      ->willReturn($logger_channel);

    return $logger_factory;
  }

}
