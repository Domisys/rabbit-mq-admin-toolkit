<?php
namespace Bab\RabbitMq\HttpClient;

use Bab\RabbitMq\HttpClientInterface;
use Bab\RabbitMq\Response;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Request;
use GuzzleHttp;

class GuzzleClient implements HttpClientInterface
{
    private $scheme;
    private $host;
    private $port;
    private $user;
    private $pass;
    private $client;
    private $dryRunModeEnabled;

    public function __construct($scheme, $host, $port, $user, $pass)
    {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;

        $this->enableDryRun(false);

        $this->initializeClient();
    }

    private function initializeClient()
    {
        if (!empty($this->host)) {
            $this->client = new Client([
                'base_url' => $this->formatBaseUrl(),
                'defaults' => [
                    'auth' => [$this->user, $this->pass],
                    'headers' => ['Content-Type' => 'application/json'],
                ],
            ]);
        }
    }

    private function formatBaseUrl()
    {
        $scheme = $this->scheme;
        $host = trim($this->host);

        if (preg_match('~^(?<scheme>https?://).~', $host) === 0) {
            if (empty($scheme)) {
                $scheme = 'http';
            }
            $scheme = trim($scheme).'://';
        }

        return sprintf(
            '%s%s:%d',
            $scheme,
            $host,
            $this->port
        );
    }

    public function query($verb, $uri, array $parameters = null)
    {
        $this->ensureValidGuzzleClient();
        $this->ensureValidDryRunQuery($verb);

        if (!empty($parameters)) {
            $parameters = json_encode($parameters);
        }

        $request = $this->client->createRequest($verb, $uri, array('body' => $parameters));

        try {
            $response = $this->client->send($request);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
        }

        $response = new Response($response->getStatusCode(), $response->getBody());

        if ($this->dryRunModeEnabled === false && !$response->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'Receive code %d instead of 200, 201 or 204. Url: %s. Body: %s',
                $response->code,
                $uri,
                $response->body
            ));
        }

        return $response;
    }

    public function enableDryRun($enabled = false)
    {
        $this->dryRunModeEnabled = $enabled;
    }

    public function switchHost($host)
    {
        $this->host = $host;
        $this->initializeClient();

        return $this;
    }

    private function ensureValidGuzzleClient()
    {
        if (!$this->client instanceof GuzzleHttp\Client) {
            throw new \RuntimeException('Guzzle Client not initialize. Do you provide an host value ?');
        }
    }

    private function ensureValidDryRunQuery($verb)
    {
        if ($this->dryRunModeEnabled === true && $verb !== 'GET') {
            throw new \RuntimeException('Dry run mode must only accept GET requests');
        }
    }
}
