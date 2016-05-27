<?php

namespace App\Events;

use App\Board;
use Illuminate\Queue\SerializesModels;

class BoardWasModified extends Event
{
    use SerializesModels;

    /**
     * The board the event is being fired on.
     *
     * @var \App\Board
     */
    public $board;

    /**
     * Create a new event instance.
     */
    public function __construct(Board $board)
    {
        $this->board = $board;
    }
}
