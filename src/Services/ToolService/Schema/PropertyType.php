<?php

namespace KLP\KlpMcpServer\Services\ToolService\Schema;

enum PropertyType
{
    case STRING;
    case INTEGER;
    case NUMBER;
    case OBJECT;
    case ARRAY;
    case BOOLEAN;
}
