<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service;

use Symfony\Component\HttpClient\HttpClient;

class HttpRequest
{
    /**
     * @var HttpClient
     */
    private $client;

    public function __construct(
        HttpClient $client
    ) {
        $this->client = $client;
    }

    /**
     * @param string $url
     *
     * @return string|null
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getHttpContent(string $url): ?string
    {
        $response = $this->client->create()->request('GET', $url);
        if ($response->getStatusCode() != 200) {
            throw new \Exception('Invalid response code: '.$response->getStatusCode().' '.$url);
        }
        return $response->getContent();
    }
}