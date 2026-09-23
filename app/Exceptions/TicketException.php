<?php

namespace App\Exceptions;

use RuntimeException;

/** Regra de negócio violada; a mensagem é exibida ao usuário. */
class TicketException extends RuntimeException {}
