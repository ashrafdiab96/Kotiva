<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

/**
 * An order was asked to move to a status it cannot reach from where it is.
 *
 * Refused loudly rather than ignored: each transition has side effects —
 * cancelling restores stock and emails the customer — so silently doing
 * nothing would leave the dashboard showing a change that never happened.
 */
final class InvalidOrderTransition extends RuntimeException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
        $allowed = array_map(
            static fn (OrderStatus $s): string => $s->label(),
            $from->allowedTransitions()
        );

        parent::__construct(sprintf(
            'An order that is %s cannot become %s. %s',
            $from->label(),
            $to->label(),
            $allowed === []
                ? sprintf('%s is final.', $from->label())
                : 'Allowed from here: '.implode(', ', $allowed).'.'
        ));
    }
}
