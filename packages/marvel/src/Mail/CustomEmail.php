<?php

namespace Marvel\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Marvel\Database\Models\User;

class CustomEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $subject;
    public $message;
    public $template;

    /**
     * Create a new message instance.
     *
     * @param User $user
     * @param string $subject
     * @param string $message
     * @param string $template
     */
    public function __construct(User $user, string $subject, string $message, string $template = 'emails.custom')
    {
        $this->user = $user;
        $this->subject = $subject;
        $this->message = $message;
        $this->template = $template;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->subject)
                    ->markdown($this->template, [
                        'user' => $this->user,
                        'message' => $this->message,
                        'subject' => $this->subject
                    ]);
    }
}


