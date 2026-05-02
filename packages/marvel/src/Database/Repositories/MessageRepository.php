<?php

namespace Marvel\Database\Repositories;

use App\Events\ReviewCreated;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Marvel\Database\Models\ChatAttachment;
use Marvel\Database\Models\Conversation;
use Marvel\Database\Models\Message;
use Marvel\Database\Models\Participant;
use Marvel\Events\MessageSent;
use Marvel\Exceptions\MarvelException;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Stevebauman\Purify\Facades\Purify;


class MessageRepository extends BaseRepository
{

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
        }
    }


    /**
     * Configure the Model
     **/
    public function model()
    {
        return Message::class;
    }

    /**
     * @param $request
     * @return LengthAwarePaginator|JsonResponse|Collection|mixed
     */
    public function storeMessage($request)
    {
        $type = '';
        $conversation_id = $request->conversation_id;
        try {
            $conversation = Conversation::findOrFail($conversation_id);
            $authorize = [
                'user' => false,
                'shop' => false
            ];
            if($request->user()->id == $conversation->user_id) {
                $authorize['user'] = true;
                $type =  "shop";
            }
            if(in_array($conversation->shop_id, $request->user()->shops()->pluck('id')->toArray()) ||
                $conversation->shop_id === $request->user()->shop_id) {
                $authorize['shop'] = true;
                $type =  "user";
            }
            if( false === $authorize['user'] && false === $authorize['shop']) {
                throw new MarvelException(NOT_AUTHORIZED);
            }

            $messageBody = $request->message ?? $request->body ?? '';
            
            $message = $this->create([
                'body'              => $messageBody,
                'conversation_id'   => $conversation_id,
                'user_id'           => $request->user()->id
            ]);

            // Handle attachments if provided
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('chat/attachments', 'public');
                    $fileType = $this->getFileType($file->getMimeType());

                    ChatAttachment::create([
                        'message_id' => $message->id,
                        'file_path' => $path,
                        'file_type' => $fileType,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            $message->conversation->update(['updated_at' => now()]);

            // Reload message with attachments
            $message->load(['user', 'chatAttachments']);

            event(new MessageSent($message, $conversation, $type));

            return $message;

        } catch (\Exception $e) {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    /**
     * Get file type from MIME type.
     */
    private function getFileType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } else {
            return 'file';
        }
    }

}
