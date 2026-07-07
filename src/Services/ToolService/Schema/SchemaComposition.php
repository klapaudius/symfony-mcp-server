<?php

namespace KLP\KlpMcpServer\Services\ToolService\Schema;

/**
 * JSON Schema composition (applicator) keywords supported for tool schemas.
 *
 * Each case maps to the exact JSON Schema keyword string that is emitted in the
 * generated schema and used to dispatch validation in ToolParamsValidator.
 *
 * @see https://json-schema.org/understanding-json-schema/reference/combining
 */
enum SchemaComposition: string
{
    case ONE_OF = 'oneOf';
    case ANY_OF = 'anyOf';
    case ALL_OF = 'allOf';
}
