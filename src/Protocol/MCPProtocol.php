<?php

namespace KLP\KlpMcpServer\Protocol;

use Exception;
use KLP\KlpMcpServer\Data\Requests\NotificationData;
use KLP\KlpMcpServer\Data\Requests\RequestData;
use KLP\KlpMcpServer\Data\Resources\JsonRpc\JsonRpcErrorResource;
use KLP\KlpMcpServer\Data\Resources\JsonRpc\JsonRpcResultResource;
use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Transports\TransportInterface;
use KLP\KlpMcpServer\Utils\DataUtil;

/**
 * MCPProtocol
 *
 * @see https://modelcontextprotocol.io/docs/concepts/architecture
 */
final class MCPProtocol implements MCPProtocolInterface
{
    public const PROTOCOL_VERSION = '2024-11-05';

    /**
     * @var RequestHandler[]
     */
    private array $requestHandlers = [];

    /**
     * @var NotificationHandler[]
     */
    private array $notificationHandlers = [];

    /**
     * @param  TransportInterface  $transport  The transport implementation to use for communication
     * @return void
     */
    public function __construct(private readonly TransportInterface $transport)
    {
        $this->transport->onMessage([$this, 'handleMessage']);
    }

    /**
     * Establishes a connection and processes incoming messages from the transport layer.
     *
     * The method starts the transport, continuously checks for incoming messages while connected,
     * handles each message appropriately, and disconnects when the connection ends.
     *
     * @throws Exception
     */
    public function connect(): void
    {
        $this->transport->start();

        while ($this->transport->isConnected()) {
            foreach ($this->transport->receive() as $message) {
                if ($message === null) {
                    continue;
                }

                $this->send(message: $message);
            }

            usleep(10000); // 10ms
        }

        $this->disconnect();
    }

    /**
     * Sends a message using the configured transport.
     *
     * @param  string|array  $message  The message to be sent, either as a string or an array.
     */
    public function send(string|array $message): void
    {
        $this->transport->send(message: $message);
    }

    /**
     * Disconnects the current transport by closing the connection.
     */
    public function disconnect(): void
    {
        $this->transport->close();
    }

    /**
     * Registers a request handler to manage incoming requests.
     *
     * @param  RequestHandler  $handler  The request handler instance to be registered.
     */
    public function registerRequestHandler(RequestHandler $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    /**
     * Registers a notification handler to handle incoming notifications.
     *
     * @param  NotificationHandler  $handler  The notification handler instance to be registered.
     */
    public function registerNotificationHandler(NotificationHandler $handler): void
    {
        $this->notificationHandlers[] = $handler;
    }

    /**
     * Handles an incoming JSON-RPC message from a client, processes it as either a request or notification,
     * and manages error responses when necessary.
     *
     * @param  string  $clientId  The unique identifier for the client sending the message
     * @param  array  $message  The JSON-RPC message data to process
     *
     * @throws JsonRpcErrorException
     */
    public function handleMessage(string $clientId, array $message): void
    {
        $messageId = $message['id'] ?? null;
        try {
            if (! isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0') {
                throw new JsonRpcErrorException(message: 'Invalid Request: Not a valid JSON-RPC 2.0 message', code: JsonRpcErrorCode::INVALID_REQUEST, data: $message);
            }

            $requestData = DataUtil::makeRequestData(message: $message);
            if ($requestData instanceof RequestData) {
                $this->handleRequestProcess(clientId: $clientId, requestData: $requestData);

                return;
            }
            if ($requestData instanceof NotificationData) {
                $this->handleNotificationProcess(clientId: $clientId, notificationData: $requestData);

                return;
            }

            throw new JsonRpcErrorException(message: 'Invalid Request: Message format not recognized', code: JsonRpcErrorCode::INVALID_REQUEST, data: $message);
        } catch (JsonRpcErrorException $e) {
            $this->pushMessage(clientId: $clientId, message: new JsonRpcErrorResource(exception: $e, id: $messageId));
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Handles incoming request messages.
     * Finds a matching request handler and executes it.
     * Sends the result or an error back to the client.
     *
     * @param  string  $clientId  The identifier of the client sending the request.
     * @param  RequestData  $requestData  The parsed request data object.
     */
    private function handleRequestProcess(string $clientId, RequestData $requestData): void
    {
        $messageId = $requestData->id;
        try {
            foreach ($this->requestHandlers as $handler) {
                if ($handler->isHandle(method: $requestData->method)) {
                    $result = $handler->execute(method: $requestData->method, params: $requestData->params);

                    $resultResource = new JsonRpcResultResource(id: $requestData->id, result: $result);
                    $this->pushMessage(clientId: $clientId, message: $resultResource);

                    return;
                }
            }

            throw new JsonRpcErrorException("Method not found: {$requestData->method}", JsonRpcErrorCode::METHOD_NOT_FOUND, data: $requestData->toArray());
        } catch (JsonRpcErrorException $e) {
            $this->pushMessage(clientId: $clientId, message: new JsonRpcErrorResource(exception: $e, id: $messageId));
        } catch (ToolParamsValidatorException $e) {
            $jsonRpcErrorException = new JsonRpcErrorException(message: $e->getMessage().' '.implode(',', $e->getErrors()), code: JsonRpcErrorCode::INVALID_PARAMS);
            $this->pushMessage(clientId: $clientId, message: new JsonRpcErrorResource(exception: $jsonRpcErrorException, id: $messageId));
        } catch (Exception $e) {
            $jsonRpcErrorException = new JsonRpcErrorException(message: $e->getMessage(), code: JsonRpcErrorCode::INTERNAL_ERROR);
            $this->pushMessage(clientId: $clientId, message: new JsonRpcErrorResource(exception: $jsonRpcErrorException, id: $messageId));
        }
    }

    /**
     * Handles incoming notification messages.
     * Finds a matching notification handler and executes it.
     * Does not send a response back to the client for notifications.
     *
     * @param  string  $clientId  The identifier of the client sending the notification.
     * @param  NotificationData  $notificationData  The parsed notification data object.
     */
    private function handleNotificationProcess(string $clientId, NotificationData $notificationData): void
    {
        try {
            foreach ($this->notificationHandlers as $handler) {
                if ($handler->isHandle(method: $notificationData->method)) {
                    $handler->execute(params: $notificationData->params);

                    return;
                }
            }

            throw new JsonRpcErrorException("Method not found: {$notificationData->method}", JsonRpcErrorCode::METHOD_NOT_FOUND, data: $notificationData->toArray());
        } catch (JsonRpcErrorException $e) {
            $this->pushMessage(clientId: $clientId, message: new JsonRpcErrorResource(exception: $e, id: null));
        } catch (Exception $e) {
            $jsonRpcErrorException = new JsonRpcErrorException(message: $e->getMessage(), code: JsonRpcErrorCode::INTERNAL_ERROR);
            $this->pushMessage(clientId: $clientId, message: new JsonRpcErrorResource(exception: $jsonRpcErrorException, id: null));
        }
    }

    /**
     * Pushes a message to a specified client.
     *
     * @param string $clientId The unique identifier of the client to push the message to.
     * @param array|JsonRpcResultResource|JsonRpcErrorResource $message The message to be pushed to the client, either as an array or an instance of JsonRpcResultResource/JsonRpcErrorResource.
     *
     * @throws Exception If transport is unable to push the message to client
     */
    private function pushMessage(string $clientId, array|JsonRpcResultResource|JsonRpcErrorResource $message): void
    {
        if ($message instanceof JsonRpcResultResource || $message instanceof JsonRpcErrorResource) {
            $this->transport->pushMessage(clientId: $clientId, message: $message->toResponse());

            return;
        }

        $this->transport->pushMessage(clientId: $clientId, message: $message);
    }

    /**
     * Process an incoming message from a specified client.
     *
     * @param string $clientId The unique identifier of the client.
     * @param array $message The message data to be processed.
     *
     * @throws Exception If the message cannot be processed
     */
    public function requestMessage(string $clientId, array $message): void
    {
        $this->transport->processMessage(clientId: $clientId, message: $message);
    }
}
