<?php

namespace Raml\Validator;

use Exception;
use Negotiation\Negotiator;
use Psr\Http\Message\RequestInterface;
use Raml\Exception\ValidationException;
use Raml\NamedParameter;
use Raml\Types\TypeValidationError;

class RequestValidator
{
    /**
     * @var ValidatorSchemaHelper
     */
    private $schemaHelper;

    /**
     * @var Negotiator
     */
    private $negotiator;

    public function __construct(ValidatorSchemaHelper $schema, Negotiator $negotiator)
    {
        $this->schemaHelper = $schema;
        $this->negotiator = $negotiator;
    }

    /**
     * @throws Exception
     */
    public function validateRequest(RequestInterface $request): void
    {
        $this->assertMediaTypes($request);
        $this->assertNoMissingParameters($request);
        $this->assertValidParameters($request);

        if (!\in_array(\mb_strtolower($request->getMethod()), ['get', 'delete'], true)) {
            $this->assertValidBody($request);
        }
    }

    /**
     * @throws ValidatorRequestException
     */
    private function assertNoMissingParameters(RequestInterface $request): void
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $schemaParameters = $this->schemaHelper->getQueryParameters($method, $path, true);
        $requestParameters = $this->getRequestParameters($request);

        $missingParameters = \array_diff_key($schemaParameters, $requestParameters);
        if (\count($missingParameters) === 0) {
            return;
        }

        throw new ValidatorRequestException(\sprintf(
            'Missing request parameters required by the schema for `%s %s`: %s',
            \mb_strtoupper($method),
            $path,
            \implode(', ', \array_keys($missingParameters))
        ));
    }

    /**
     * @throws ValidatorRequestException
     */
    private function assertValidParameters(RequestInterface $request): void
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $schemaParameters = $this->schemaHelper->getQueryParameters($method, $path);
        $requestParameters = $this->getRequestParameters($request);

        /** @var NamedParameter $schemaParameter */
        foreach ($schemaParameters as $schemaParameter) {
            $key = $schemaParameter->getKey();

            if (!\array_key_exists($key, $requestParameters)) {
                continue;
            }

            try {
                $schemaParameter->validate($requestParameters[$key]);
            } catch (ValidationException $exception) {
                $message = \sprintf(
                    'Request parameter does not match schema for `%s %s`: %s',
                    \mb_strtoupper($method),
                    $path,
                    $exception->getMessage()
                );

                throw new ValidatorRequestException($message, 0, $exception);
            }
        }
    }

    private function assertValidBody(RequestInterface $request): void
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $contentType = \explode(';', $request->getHeaderLine('Content-Type'))[0];

        $schemaBody = $this->schemaHelper->getRequestBody($method, $path, $contentType);

        $body = ContentConverter::convertStringByContentType($request->getBody()->getContents(), $contentType);

        $schemaBody->getValidator()->validate($body);
        if ($schemaBody->getValidator()->getErrors()) {
            $message = \sprintf(
                'Request body for %s %s with content type %s does not match schema: %s',
                \mb_strtoupper($method),
                $path,
                $contentType,
                $this->getTypeValidationErrorsAsString($schemaBody->getValidator()->getErrors())
            );

            throw new ValidatorRequestException($message);
        }
    }

    /**
     * @return array
     */
    private function getRequestParameters(RequestInterface $request)
    {
        if (empty($request->getUri()->getQuery())) {
            return [];
        }

        \parse_str($request->getUri()->getQuery(), $requestParameters);

        return $requestParameters;
    }

    /**
     * @return string
     */
    private function getSchemaErrorsAsString(array $errors)
    {
        return \implode(', ', \array_map(static function (array $error) {
            return \sprintf('%s (%s)', $error['property'], $error['constraint']);
        }, $errors));
    }

    private function assertMediaTypes(RequestInterface $request): void
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $responseSchemas = $this->schemaHelper->getResponses(
            $method,
            $path
        );

        $priorities = [];
        foreach ($responseSchemas as $responseSchema) {
            $priorities = \array_merge($priorities, $responseSchema->getTypes());
        }

        if (!$priorities) {
            $priorities = $this->schemaHelper->getDefaultMediaTypes();
        }

        if (!$priorities) {
            return;
        }

        $acceptHeader = $request->getHeaderLine('Accept');
        $accept = $acceptHeader ? $this->negotiator->getBest($acceptHeader, $priorities) : null;

        if ($accept === null) {
            throw new ValidatorRequestException('Invalid Media type');
        }
    }

    private function getTypeValidationErrorsAsString(array $errors)
    {
        return \implode(', ', \array_map(static function (TypeValidationError $error) {
            return $error->__toString();
        }, $errors));
    }
}
