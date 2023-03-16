<?php
/**
 * Elasticsearch PHP Client
 *
 * @link      https://github.com/elastic/elasticsearch-php
 * @copyright Copyright (c) Elasticsearch B.V (https://www.elastic.co)
 * @license   https://opensource.org/licenses/MIT MIT License
 *
 * Licensed to Elasticsearch B.V under one or more agreements.
 * Elasticsearch B.V licenses this file to you under the MIT License.
 * See the LICENSE file in the project root for more information.
 */
declare(strict_types = 1);

namespace Elastic\Elasticsearch\Helper\Iterators;

use Elastic\Elasticsearch\ClientInterface;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Iterator;

class SearchResponseIterator implements Iterator
{

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @var array
     */
    private array $params;

    /**
     * @var int
     */
    private int $currentKey = 0;

    /**
     * @var array
     */
    private array $currentScrolledResponse;

    /**
     * @var string|null
     */
    private ?string $scrollId;

    /**
     * @var string duration
     */
    private $scroll_ttl;

    /**
     * Constructor
     *
     * @param ClientInterface $client
     * @param array  $search_params Associative array of parameters
     * @see   ClientInterface::search()
     */
    public function __construct(ClientInterface $client, array $search_params)
    {
        $this->client = $client;
        $this->params = $search_params;

        if (isset($search_params['scroll'])) {
            $this->scroll_ttl = $search_params['scroll'];
        }
    }

    /**
     * Destructor
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function __destruct()
    {
        $this->clearScroll();
    }

    /**
     * Sets the time to live duration of a scroll window
     *
     * @param  string $time_to_live
     * @return $this
     */
    public function setScrollTimeout(string $time_to_live): SearchResponseIterator
    {
        $this->scroll_ttl = $time_to_live;
        return $this;
    }

    /**
     * Clears the current scroll window if there is a scroll_id stored
     *
     * @return void
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    private function clearScroll(): void
    {
        if (!empty($this->scrollId)) {
            $this->client->clearScroll(
                [
                    'body' => [
                        'scroll_id' => $this->scrollId
                    ],
                    'client' => [
                        'ignore' => 404
                    ]
                ]
            );
            $this->scrollId = null;
        }
    }

    /**
     * Rewinds the iterator by performing the initial search.
     *
     * @return void
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @see    Iterator::rewind()
     */
    public function rewind(): void
    {
        $this->clearScroll();
        $this->currentKey = 0;
        $this->currentScrolledResponse = $this->client->search($this->params);
        $this->scrollId = $this->currentScrolledResponse['_scroll_id'];
    }

    /**
     * Fetches every "page" after the first one using the lastest "scroll_id"
     *
     * @return void
     * @see    Iterator::next()
     */
    public function next(): void
    {
        $this->currentScrolledResponse = $this->client->scroll(
            [
                'body' => [
                    'scroll_id' => $this->scrollId,
                    'scroll'    => $this->scroll_ttl
                ]
            ]
        );
        $this->scrollId = $this->currentScrolledResponse['_scroll_id'];
        $this->currentKey++;
    }

    /**
     * Returns a boolean value indicating if the current page is valid or not
     *
     * @return bool
     * @see    Iterator::valid()
     */
    public function valid(): bool
    {
        return isset($this->currentScrolledResponse['hits']['hits'][0]);
    }

    /**
     * Returns the current "page"
     *
     * @return array
     * @see    Iterator::current()
     */
    public function current(): array
    {
        return $this->currentScrolledResponse;
    }

    /**
     * Returns the current "page number" of the current "page"
     *
     * @return int
     * @see    Iterator::key()
     */
    public function key(): int
    {
        return $this->currentKey;
    }
}