<?php

namespace Messaging\sockets;

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
     * @var array
     */
    protected $subscribedTopics = [];

    public function __construct()
    {
        $this->connections = new \SplObjectStorage;
    }

    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
        $subscriptionInfo = explode('/', $topic->getId());

        if (3 !== count($subscriptionInfo)) {
            echo "Invalid subscription info";
            return;
        }

        $client = $subscriptionInfo[0];
        $userId = $subscriptionInfo[1];
        $auth = $subscriptionInfo[2];

        $this->subscribedTopics[$client][$userId] = $topic;
        Helpers::writeLine('User: ' . $userId . ' has subscribed to ' . $client);
    }

    /**
     * @param string JSON'ified string we'll receive from ZeroMQ
     */
    public function onInstantMessage($entry)
    {
        $entryData = json_decode($entry, true);

        if (!array_key_exists('client', $entryData)) {
            Helpers::writeLine('Client is not set in entry data');
        }

        if (!array_key_exists('subscribedUsers', $entryData)) {
            Helpers::writeLine('subscribedUsers is not set in entry data');
        }

        $client = $entryData['client'];
        $subscribedUsers = $entryData['subscribedUsers'];

        print_r($entryData);

        Helpers::writeLine('Message sent from ' . $client . ' to: ' . implode(', ', $subscribedUsers));

        if (!array_key_exists($client, $this->subscribedTopics)) {
            Helpers::writeLine('No one set up to view: ' . $client . ' message not sent');
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

    }

    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    function onOpen(ConnectionInterface $conn)
    {
        $this->connections->attach($conn);

        echo "Someone has opened a connection" . PHP_EOL;
        // TODO: Implement onOpen() method.
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    function onClose(ConnectionInterface $conn)
    {
        $this->connections->detach($conn);
        // TODO: Implement onClose() method.
        echo "Someone has closed a connection" . PHP_EOL;
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