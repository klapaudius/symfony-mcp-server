<?php

namespace KLP\KlpMcpServer\Services\ToolService\Schema;

enum PropertyType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case NUMBER = 'number';
    case OBJECT = 'object';
    case ARRAY = 'array';
    case BOOLEAN = 'boolean';
}
