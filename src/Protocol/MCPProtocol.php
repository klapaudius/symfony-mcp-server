<?php

namespace KLP\KlpMcpServer\Protocol;

use Exception;
use KLP\KlpMcpServer\Data\Requests\NotificationData;
use KLP\KlpMcpServer\Data\Requests\RequestData;
use KLP\KlpMcpServer\Data\Requests\ResponseData;
use KLP\KlpMcpServer\Data\Resources\JsonRpc\JsonRpcErrorResource;
use KLP\KlpMcpServer\Data\Resources\JsonRpc\JsonRpcResultResource;
use KLP\KlpMcpServer\Exceptions\Enums\JsonRpcErrorCode;
use KLP\KlpMcpServer\Exceptions\JsonRpcErrorException;
use KLP\KlpMcpServer\Exceptions\ToolParamsValidatorException;
use KLP\KlpMcpServer\Protocol\Handlers\NotificationHandler;
use KLP\KlpMcpServer\Protocol\Handlers\RequestHandler;
use KLP\KlpMcpServer\Protocol\Handlers\ResponseHandler;
use KLP\KlpMcpServer\Server\Notification\InitializedHandler;
use KLP\KlpMcpServer\Server\Notification\PongHandler;
use KLP\KlpMcpServer\Server\Request\PingHandler;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryException;
use KLP\KlpMcpServer\Transports\Factory\TransportFactoryInterface;
use KLP\KlpMcpServer\Transports\SseTransportInterface;
use KLP\KlpMcpServer\Transports\StreamableHttpTransportInterface;
use KLP\KlpMcpServer\Transports\TransportInterface;
use KLP\KlpMcpServer\Utils\DataUtil;

/**
 * MCPProtocol
 *
 * @internal
 *
 * @see https://modelcontextprotocol.io/docs/concepts/architecture
 */
final class MCPProtocol implements MCPProtocolInterface
{
    /**
     * @var RequestHandler[]
     */
    private array $requestHandlers = [];

    /**
     * @var ResponseHandler[]
     */
    private array $responseHandlers = [];

    /**
     * @var NotificationHandler[]
     */
    private array $notificationHandlers = [];

    private ?TransportInterface $transport = null;

    /**
     * @param  TransportFactoryInterface  $transportFactory  The transport factory to use for creating transports.
     * @return void
     */
    public function __construct(private readonly TransportFactoryInterface $transportFactory) {}

    /**
     * Establishes a connection and processes incoming messages from the transport layer.
     *
     * The method starts the transport, continuously checks for incoming messages while connected,
     * handles each message appropriately, and disconnects when the connection ends.
     *
     * @throws Exception
     */
    public function connect(string $version): void
    {
        $this->initTransport($version);

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
     * Registers a response handler to manage incoming response.
     *
     * @param  ResponseHandler  $handler  The response handler instance to be registered.
     */
    public function registerResponseHandler(ResponseHandler $handler): void
    {
        $this->responseHandlers[] = $handler;
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

            $requestData = DataUtil::makeRequestData(clientId: $clientId, message: $message);
            if ($requestData instanceof RequestData) {
                $this->handleRequestProcess(clientId: $clientId, requestData: $requestData);

                return;
            }
            if ($requestData instanceof ResponseData) {
                $this->handleResponseProcess(clientId: $clientId, responseData: $requestData);

                return;
            }
            if ($requestData instanceof NotificationData) {
                $this->handleNotificationProcess(clientId: $clientId, notificationData: $requestData);

                return;
            }

            throw new JsonRpcErrorException(message: 'Invalid Request: Message format not recognized', code: JsonRpcErrorCode::INVALID_REQUEST, data: $message);
        } catch (JsonRpcErrorException $e) {
            $this->pushMessage(clientId: $clientId, message: new JsonRpcErrorResource(exception: $e, id: $messageId));
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
                    $result = $handler->execute(method: $requestData->method, clientId: $clientId, messageId: $requestData->id, params: $requestData->params);

                    $resultResource = new JsonRpcResultResource(id: $requestData->id, result: $result);
                    $this->pushMessage(clientId: $clientId, message: $resultResource);

                    return;
                }
            }

            throw new JsonRpcErrorException("Method not found: {$requestData->method}", JsonRpcErrorCode::METHOD_NOT_FOUND, data: $requestData->toArray());
        } catch (JsonRpcErrorException $e) {
            $jsonRpcErrorException = new JsonRpcErrorResource(exception: $e, id: $messageId);
            $this->pushMessage(clientId: $clientId, message: $jsonRpcErrorException);
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
     * Handles incoming response messages.
     * Finds a matching response handler and executes it.
     * Does not send a response back to the client for notifications.
     *
     * @param  string  $clientId  The identifier of the client sending the response.
     * @param  ResponseData  $responseData  The parsed response data object.
     */
    private function handleResponseProcess(string $clientId, ResponseData $responseData): void
    {
        try {
            foreach ($this->responseHandlers as $handler) {
                if ($handler->isHandle(messageId: $responseData->id)) {
                    $handler->execute(
                        clientId: $clientId,
                        messageId: $responseData->id,
                        result: $responseData->result ?? null,
                        error: $responseData->error ?? null
                    );

                    return;
                }
            }

            // If no handler is found, silently ignore the response
            // This is expected behavior for responses that don't match any pending requests
        } catch (JsonRpcErrorException $e) {
            $this->pushMessage(clientId: $clientId, message: new JsonRpcErrorResource(exception: $e, id: $responseData->id));
        } catch (Exception $e) {
            $jsonRpcErrorException = new JsonRpcErrorException(message: $e->getMessage(), code: JsonRpcErrorCode::INTERNAL_ERROR);
            $this->pushMessage(clientId: $clientId, message: new JsonRpcErrorResource(exception: $jsonRpcErrorException, id: $responseData->id));
        }
    }

    /**
     * Pushes a message to a specified client.
     *
     * @param  string  $clientId  The unique identifier of the client to push the message to.
     * @param  JsonRpcResultResource|JsonRpcErrorResource  $message  The message to be pushed to the client, either an instance of JsonRpcResultResource or JsonRpcErrorResource.
     *
     * @throws Exception If transport is unable to push the message to client
     */
    private function pushMessage(string $clientId, JsonRpcResultResource|JsonRpcErrorResource $message): void
    {
        $this->transport->pushMessage(clientId: $clientId, message: $message->toResponse());
    }

    /**
     * Process an incoming message from a specified client.
     *
     * @param  string  $clientId  The unique identifier of the client.
     * @param  array  $message  The message data to be processed.
     *
     * @throws Exception If the message cannot be processed
     */
    public function requestMessage(string $clientId, array $message): void
    {
        $this->transport->processMessage(clientId: $clientId, message: $message);
    }

    /**
     * Retrieves the client ID. If the client ID is not already set, generates a unique ID.
     *
     * @return string The client ID.
     */
    public function getClientId(): string
    {
        return $this->transport->getClientId();
    }

    public function getResponseResult(string $clientId): array
    {
        $result = [];
        foreach ($this->transport->receive() as $message) {
            if ($message !== null) {
                $result[] = json_decode($message);
            }
        }

        return $result;
    }

    public function setClientSamplingCapability(bool $hasSamplingCapability): void
    {
        $this->transport->setClientSamplingCapability($hasSamplingCapability);
    }

    public function setProtocolVersion(string $version): void
    {
        $this->initTransport($version);
    }

    private function initTransport(string $version)
    {
        if (! $this->transport instanceof TransportInterface) {
            try {
                $this->transport = $this->transportFactory->get();
            } catch (TransportFactoryException) {
                $this->transport = $this->transportFactory->create($version);
            }
            if ($this->transport instanceof StreamableHttpTransportInterface) {
                $this->transport->setConnected(true);
                $this->transport->sendHeaders();
            }

            $this->transport->onMessage([$this, 'handleMessage']);
            if ($this->transport instanceof SseTransportInterface) {
                $this->registerRequestHandler(new PingHandler($this->transport));
                $this->registerNotificationHandler(new PongHandler($this->transport->getAdapter()));
            }
            $this->registerNotificationHandler(new InitializedHandler);
        }
    }
}
