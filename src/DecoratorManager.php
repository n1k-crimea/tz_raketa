<?php

namespace src\Decorator;

use DateTime;
use DateTimeInterface;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use src\Integration\DataProviderInterface;

class DecoratorManager implements DataProviderInterface
{
    private DataProviderInterface $dataProvider;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;

    /**
     * @param DataProviderInterface $dataProvider
     * @param CacheItemPoolInterface $cache
     * @param LoggerInterface $logger
     */
    public function __construct(DataProviderInterface $dataProvider, CacheItemPoolInterface $cache, LoggerInterface $logger)
    {
        $this->dataProvider = $dataProvider;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * @param array $input
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function get(array $input): array
    {
        try {
            $cacheKey = $this->getCacheKey($input);
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }

            $result = $this->dataProvider->get($input);

            $cacheItem->set($result)->expiresAt(
                (new DateTime())->modify('+1 day')
            );
            $this->cache->save($cacheItem);

            return $result;
        } catch (Exception $e) {
            $this->logger->critical('Ошибка при получении ответа', [
                'exception' => $e,
                'input' => $input,
                'timestamp' => (new DateTime())->format(DateTimeInterface::ATOM)
            ]);
            throw $e;
        }
    }

    /**
     * @param array $input
     *
     * @return string
     */
    private function getCacheKey(array $input): string
    {
        return md5(json_encode($input));
    }
}
