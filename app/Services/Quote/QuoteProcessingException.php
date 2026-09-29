<?php

namespace App\Services\Quote;

use RuntimeException;

// Thrown for failures that retrying will not fix
class QuoteProcessingException extends RuntimeException {}
