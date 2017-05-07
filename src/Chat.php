<?php

namespace Messaging\sockets;

use Dotenv\Dotenv;
use Guzzle\Http\Exception\CurlException;
use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\Topic;
use Ratchet\Wamp\WampServerInterface;

class Chat implements WampServerInterface
{
    /**
     * @var \SplObjectStorage
     */
    protected $connections;

    /**
     * @var Logger
     */
    protected $logger;

    protected $serverAuth = [];
    /**
     * @var array
     */
    protected $subscribedTopics = [];

    public function __construct()
    {
        $this->connections = new \SplObjectStorage;

        $dotenv = new Dotenv(__DIR__ . '/..');
        $dotenv->load();
        $dotenv->required(['LOG_PATH', 'DEBUG_LOG', 'INFO_LOG', 'ERROR_LOG']);

        $this->logger = new Logger('logger');
        $this->logger->pushHandler(new StreamHandler(getenv('LOG_PATH') . getenv('DEBUG_LOG'), Logger::DEBUG));
        $this->logger->pushHandler(new StreamHandler(getenv('LOG_PATH') . getenv('INFO_LOG'), Logger::INFO));
        $this->logger->pushHandler(new StreamHandler(getenv('LOG_PATH') . getenv('ERROR_LOG'), Logger::ERROR));

        $severAuth = file_get_contents(__DIR__ . '/../config/server_auth.json');

        if (false === $severAuth) {
            $this->logger->addError('No server_auth.json file found in config');
            throw new \RuntimeException('No server_auth.json file found in config');
        }

        $this->serverAuth = json_decode($severAuth);
        $this->logger->addDebug('Socket server started');


    }

    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
        try {
            $subscriptionInfo = explode('/', $topic->getId());

            if (3 !== count($subscriptionInfo)) {
                throw new \RuntimeException('Invalid subscription info: ' . json_encode($subscriptionInfo));
            }

            $client = $subscriptionInfo[0];
            $userId = $subscriptionInfo[1];
            $auth = $subscriptionInfo[2];

            if (!array_key_exists($client, $this->serverAuth)) {
                throw new \RuntimeException('Client ' . $client . ' does not exist in the list of authorised clients');
            }

            $this->logger->addDebug('User: ' . $userId . ' is trying to connect to ' . $client);

            $this->authorise($this->serverAuth->{$client}, $userId, $auth);

            $this->subscribedTopics[$client][$userId] = $topic;
            $this->logger->addDebug('User: ' . $userId . ' has subscribed to ' . $client);
        } catch (\Exception $exception) {
            $this->logger->addError($exception->getMessage());
        }
    }


    /**
     * @param \stdClass $client
     * @param integer $userId
     * @param string $authKey
     * @return bool
     */
    protected function authorise($client, $userId, $authKey)
    {
        $this->logger->addDebug('Attempting to authorise userId ' . $userId . ' on ' . $client->server_url);
        if (0 === $client->require_auth) {
            $this->logger->addDebug('Authentication turned off, automatically authorised');
            return true;
        }
        $authUrl = $client->server_url;

        $client = new Client(['base_uri' => $authUrl]);
        $response = $client->request('GET', '?userId=' . $userId . '&authKey=' . $authKey);
        if (404 === $response->getStatusCode()) {
            throw new CurlException('Url: ' . $authUrl . ' is not valid');
        }

        if (403 === $response->getStatusCode()) {
            throw new CurlException('Not authorised');
        }


        if (200 !== $response->getStatusCode()) {
            throw new CurlException('Invalid status code: ' . $response->getStatusCode());
        }

        $this->logger->addDebug('Authorisation successful');

        return true;


    }

    /**
     * @param string JSON'ified string we'll receive from ZeroMQ
     */
    public function onInstantMessage($entry)
    {
        try {
            $entryData = json_decode($entry, true);

            if (!array_key_exists('client', $entryData)) {
                throw new \RuntimeException('Client is not set in entry data');
            }

            if (!array_key_exists('subscribedUsers', $entryData)) {
                throw new \RuntimeException('subscribedUsers is not set in entry data');
            }

            $client = $entryData['client'];
            $subscribedUsers = $entryData['subscribedUsers'];

            $this->logger->addDebug('Message sent from ' . $client . ' to: ' . implode(', ', $subscribedUsers));

            if (!array_key_exists($client, $this->subscribedTopics)) {
                $this->logger->addDebug('No one set up to view: ' . $client . ' message not sent');
                return;
            }

            foreach ($subscribedUsers as $subscribedUser) {
                if (!array_key_exists($subscribedUser, $this->subscribedTopics[$client])) {
                    continue;
                }
                /** @var Topic $topic */
                $topic = $this->subscribedTopics[$client][$subscribedUser];
                $topic->broadcast($entryData);
            }
        } catch (\Exception $exception) {
            $this->logger->addError($exception->getMessage());
        }

    }

    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    function onOpen(ConnectionInterface $conn)
    {
        $this->connections->attach($conn);

        $this->logger->addDebug('Someone has opened a connection');
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    function onClose(ConnectionInterface $conn)
    {
        $this->connections->detach($conn);
        $this->logger->addDebug('Someone has closed a connection');
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  ConnectionInterface $conn
     * @param  \Exception $e
     * @throws \Exception
     */
    function onError(ConnectionInterface $conn, \Exception $e)
    {
        // TODO: Implement onError() method.
    }

    /**
     * An RPC call has been received
     * @param \Ratchet\ConnectionInterface $conn
     * @param string $id The unique ID of the RPC, required to respond to
     * @param string|Topic $topic The topic to execute the call against
     * @param array $params Call parameters received from the client
     */
    function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
        // TODO: Implement onCall() method.
    }

    /**
     * A request to unsubscribe from a topic has been made
     * @param \Ratchet\ConnectionInterface $conn
     * @param string|Topic $topic The topic to unsubscribe from
     */
    function onUnSubscribe(ConnectionInterface $conn, $topic)
    {
        // TODO: Implement onUnSubscribe() method.
        echo "Someone has unsubscribed" . PHP_EOL;
    }

    /**
     * A client is attempting to publish content to a subscribed connections on a URI
     * @param \Ratchet\ConnectionInterface $conn
     * @param string|Topic $topic The topic the user has attempted to publish to
     * @param string $event Payload of the publish
     * @param array $exclude A list of session IDs the message should be excluded from (blacklist)
     * @param array $eligible A list of session Ids the message should be send to (whitelist)
     */
    function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
        // TODO: Implement onPublish() method.
    }
}