<?php

declare(strict_types=1);

namespace App\Service;

use DomainException;

/**
 * The table does not exist or is inactive, so it cannot accept orders (R1).
 */
final class TableUnavailableException extends DomainException {}
