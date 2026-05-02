<?php

namespace Marvel\Events;

use Marvel\Database\Models\Question;

class QuestionCreated
{
    public $question;

    /**
     * Create a new event instance.
     *
     * @param Question $question
     */
    public function __construct(Question $question)
    {
        $this->question = $question;
    }
}
