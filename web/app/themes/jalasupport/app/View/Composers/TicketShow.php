<?php

namespace App\View\Composers;

use Roots\Acorn\View\Composer;

class TicketShow extends Composer
{
    /**
     * @var array<int, string>
     */
    protected static $views = [
        'app.tickets.show',
    ];

    public function ticketId(): int
    {
        return absint(get_query_var('jalasupport_ticket'));
    }
}
